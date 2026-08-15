<?php

namespace App\Services\Erp;

use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\PartnerLink;
use App\Models\Submission;
use App\Services\NotifikasiMitra;
use Illuminate\Support\Facades\DB;

/**
 * Menerapkan keputusan yang diambil di ERP ke sisi portal.
 *
 * Ada dua jalan masuk ke sini dan keduanya harus berakhir sama:
 *
 *  - webhook (WebhookController) — jalur cepat, ≤10 detik setelah staf menekan
 *    tombol di ERP;
 *  - perintah `portal:sync-status` — jalur cadangan, kalau webhook-nya tidak
 *    pernah sampai (portal sedang mati, jaringan putus, dsb).
 *
 * Karena itu logikanya tinggal di satu kelas, bukan disalin ke dua tempat:
 * dua salinan yang perlahan berbeda akan membuat hasil akhir bergantung pada
 * jalur mana yang kebetulan menang, dan itu jenis bug yang sulit dilihat.
 *
 * Semua metode di sini IDEMPOTEN. Webhook dan cron memang bisa membawa kabar
 * yang sama; yang kedua harus jadi operasi kosong, bukan notifikasi ganda.
 */
class ErpEventApplier
{
    /**
     * Klaim disetujui staf: akun naik kelas dan data mitra terbuka.
     *
     * @param  bool  $bolehMembukaYangDicabut  Hanya webhook `claim.verified`
     *   yang boleh memasang ini. Webhook berarti ada staf yang baru saja
     *   menekan tombol setuju di ERP; hasil polling hanya berarti "di ERP
     *   statusnya masih verified", dan itu tidak cukup untuk membuka kembali
     *   akses yang sudah dicabut.
     * @return bool  false bila kabar ini sudah pernah diterapkan.
     */
    public function claimVerified(
        PartnerLink $link,
        int $erpPartnerId,
        ?string $partnerCode = null,
        string $actorName = 'Tim Flustra',
        bool $bolehMembukaYangDicabut = false,
    ): bool {
        if ($link->status === 'verified' && (int) $link->erp_partner_id === $erpPartnerId) {
            return false;
        }

        if ($link->status === 'revoked' && ! $bolehMembukaYangDicabut) {
            return false;
        }

        $user = $link->user;

        if (! $user) {
            return false; // Akunnya sudah dihapus; tidak ada yang perlu dinaikkan.
        }

        // Dihitung SEBELUM link ini ikut jadi 'verified', supaya bisa dibedakan
        // "mitra pertama" dari "peran kedua pada akun yang sudah aktif".
        $peranAktifMasihSah = $user->active_link_id
            && $user->partnerLinks()
                ->where('id', $user->active_link_id)
                ->where('id', '!=', $link->id)
                ->where('status', 'verified')
                ->exists();

        $submission = $this->claimSubmission($link);

        DB::transaction(function () use ($link, $user, $erpPartnerId, $partnerCode, $actorName, $submission, $peranAktifMasihSah) {
            $link->forceFill([
                'status'           => 'verified',
                'erp_partner_id'   => $erpPartnerId,
                'erp_partner_code' => $partnerCode,
                'verified_at'      => now(),
                'verified_by_name' => $actorName,
                'rejected_reason'  => null,
            ])->save();

            // Peran yang sedang dipakai tidak digeser diam-diam. Kalau akun ini
            // sudah aktif sebagai pelanggan lalu klaim vendornya disetujui,
            // berpindah sendiri akan mengubah seluruh isi berandanya tanpa
            // diminta — pemilihnya ada di profil.
            if (! $peranAktifMasihSah) {
                $user->forceFill([
                    'account_type'   => $link->account_type,
                    'active_link_id' => $link->id,
                ])->save();
            }

            if ($submission) {
                $submission->forceFill([
                    'sync_state'    => 'synced',
                    'synced_at'     => $submission->synced_at ?? now(),
                    'erp_record_id' => $erpPartnerId,
                ])->save();

                if ($submission->status !== 'approved') {
                    $submission->transitionTo(
                        'approved',
                        'Pengajuan kerja sama Anda disetujui. Layanan '.$link->partner_type_label.' sudah terbuka.',
                        'erp',
                        $actorName,
                    );
                }
            }
        });

        NotifikasiMitra::kirim(
            $user,
            'Pengajuan kerja sama disetujui',
            'Akun Anda kini terhubung sebagai '.$link->partner_type_label.' untuk '.$link->company_name
                .'. Kartu layanan '.$link->partner_type_label.' sudah bisa dipakai.',
            'success',
            $submission ? route('riwayat.show', $submission) : route('beranda'),
            NotifikasiMitra::pesan(
                'Pengajuan kerja sama Anda *disetujui*.',
                'Akun Anda kini terhubung sebagai '.$link->partner_type_label.' untuk '.$link->company_name.'.',
                $submission?->reference_number,
            ),
        );

        ActivityLog::log(
            'partner_claim_verified',
            'Klaim '.$link->partner_type_label.' untuk '.$link->company_name.' disetujui tim Flustra.',
            $user->id,
        );

        return true;
    }

    /**
     * Klaim ditolak. Akun tetap hidup sebagai 'umum' dan boleh mengajukan lagi —
     * penolakan berlaku atas bukti kali itu, bukan atas akunnya.
     */
    public function claimRejected(PartnerLink $link, string $reason, string $actorName = 'Tim Flustra'): bool
    {
        if ($link->status === 'rejected') {
            return false;
        }

        $user = $link->user;
        $submission = $this->claimSubmission($link);

        DB::transaction(function () use ($link, $reason, $submission, $actorName) {
            $link->forceFill([
                'status'          => 'rejected',
                'rejected_reason' => $reason,
            ])->save();

            if ($submission) {
                $submission->forceFill([
                    'sync_state' => 'synced',
                    'synced_at'  => $submission->synced_at ?? now(),
                ])->save();

                if ($submission->status !== 'rejected') {
                    $submission->transitionTo('rejected', $reason, 'erp', $actorName);
                }
            }
        });

        if ($user) {
            NotifikasiMitra::kirim(
                $user,
                'Pengajuan kerja sama ditolak',
                $reason.' Anda bisa memperbaiki data dan mengajukan kembali.',
                'error',
                $submission ? route('riwayat.show', $submission) : route('mitra.create'),
                NotifikasiMitra::pesan(
                    'Pengajuan kerja sama Anda belum bisa kami setujui.',
                    $reason.' Anda bisa memperbaiki datanya dan mengajukan kembali lewat portal.',
                    $submission?->reference_number,
                ),
            );

            ActivityLog::log(
                'partner_claim_rejected',
                'Klaim '.$link->partner_type_label.' untuk '.$link->company_name.' ditolak. Alasan: '.$reason,
                $user->id,
            );
        }

        return true;
    }

    /**
     * Kerja sama berakhir: akses data mitra dicabut.
     */
    public function partnerRevoked(PartnerLink $link, ?string $reason = null): bool
    {
        if ($link->status === 'revoked') {
            return false;
        }

        $user = $link->user;

        DB::transaction(function () use ($link, $reason, $user) {
            $link->forceFill([
                'status'          => 'revoked',
                'rejected_reason' => $reason,
            ])->save();

            if (! $user) {
                return;
            }

            // Turunkan hanya bila peran inilah yang sedang dipakai. Akun dengan
            // dua peran tidak boleh kehilangan peran yang satunya.
            $penggantinya = $user->partnerLinks()
                ->where('status', 'verified')
                ->where('id', '!=', $link->id)
                ->orderBy('id')
                ->first();

            if ($penggantinya) {
                if ((int) $user->active_link_id === (int) $link->id) {
                    $user->forceFill([
                        'account_type'   => $penggantinya->account_type,
                        'active_link_id' => $penggantinya->id,
                    ])->save();
                }

                return;
            }

            $user->forceFill([
                'account_type'   => 'umum',
                'active_link_id' => null,
            ])->save();
        });

        if ($user) {
            Notification::send(
                $user->id,
                'Akses mitra dicabut',
                'Akses '.$link->partner_type_label.' untuk '.$link->company_name.' telah dicabut.'
                    .($reason ? ' Alasan: '.$reason : '')
                    .' Hubungi tim kami bila ini di luar dugaan Anda.',
                'warning',
                route('beranda'),
            );

            ActivityLog::log(
                'partner_link_revoked',
                'Akses '.$link->partner_type_label.' untuk '.$link->company_name.' dicabut.'
                    .($reason ? ' Alasan: '.$reason : ''),
                $user->id,
            );
        }

        return true;
    }

    /**
     * Status pengajuan berubah di ERP.
     *
     * @param  string  $status  submitted|received|under_review|approved|rejected|cancelled
     */
    public function submissionStatusChanged(
        Submission $submission,
        string $status,
        ?string $reason = null,
        ?string $erpReference = null,
        string $actorName = 'Tim Flustra',
    ): bool {
        $jejak = [];

        if ($erpReference && $submission->erp_reference !== $erpReference) {
            $jejak['erp_reference'] = $erpReference;
        }

        // ERP mengabari statusnya, jadi pengajuan ini jelas sudah sampai sana.
        if ($submission->sync_state !== 'synced') {
            $jejak['sync_state'] = 'synced';
            $jejak['synced_at']  = $submission->synced_at ?? now();
            $jejak['sync_error'] = null;
        }

        if ($jejak) {
            $submission->forceFill($jejak)->save();
        }

        if ($submission->status === $status) {
            return false;
        }

        $submission->transitionTo($status, $reason, 'erp', $actorName);

        Notification::send(
            $submission->user_id,
            $submission->type_label.' '.mb_strtolower($submission->status_label),
            'Pengajuan '.$submission->reference_number.' kini berstatus "'.$submission->status_label.'".'
                .($reason ? ' '.$reason : ''),
            match ($status) {
                'approved' => 'success',
                'rejected' => 'error',
                default    => 'info',
            },
            route('riwayat.show', $submission),
        );

        return true;
    }

    // =====================================================================

    /**
     * Pengajuan portal yang melahirkan klaim ini.
     *
     * Global scope 'milik_sendiri' dilepas dengan sengaja: kode ini berjalan
     * dari webhook dan cron, di mana tidak ada pengguna yang sedang masuk.
     */
    public function claimSubmission(PartnerLink $link): ?Submission
    {
        return Submission::withoutGlobalScope('milik_sendiri')
            ->where('partner_link_id', $link->id)
            ->where('type', 'partner_claim')
            ->latest('id')
            ->first();
    }

    public function findLink(int $linkId): ?PartnerLink
    {
        return PartnerLink::with('user')->find($linkId);
    }

    public function findSubmission(string $reference): ?Submission
    {
        return Submission::withoutGlobalScope('milik_sendiri')
            ->where('reference_number', $reference)
            ->first();
    }

    /** @return array<int, string> */
    public function allowedStatuses(): array
    {
        return ['submitted', 'received', 'under_review', 'approved', 'rejected', 'cancelled'];
    }
}
