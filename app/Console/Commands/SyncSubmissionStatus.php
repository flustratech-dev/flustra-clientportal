<?php

namespace App\Console\Commands;

use App\Jobs\SyncSubmissionToErp;
use App\Models\Submission;
use App\Services\Erp\ErpClient;
use App\Services\Erp\ErpEventApplier;
use App\Services\Erp\ErpException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Rekonsiliasi cadangan portal ↔ ERP, dijadwalkan tiap 15 menit.
 *
 * Webhook adalah jalur utama dan hampir selalu cukup. Perintah ini menutup dua
 * lubang yang tersisa:
 *
 *  1. **Pengajuan yang tidak pernah terkirim.** Antreannya habis percobaan
 *     ketika ERP mati berjam-jam. Di sini job-nya diantre ulang, karena ERP
 *     idempoten terhadap portal_reference — kiriman kedua tidak akan
 *     menggandakan apa pun.
 *  2. **Keputusan yang webhook-nya hilang.** Staf sudah menyetujui di ERP, tapi
 *     portal sedang mati saat webhook dikirim. Statusnya ditarik dari sana.
 *
 * Sengaja tidak menyentuh pengajuan yang sudah final (approved/rejected/
 * cancelled): keputusannya sudah sampai ke pengguna, dan menariknya lagi tiap
 * 15 menit hanya membebani ERP tanpa hasil.
 */
class SyncSubmissionStatus extends Command
{
    protected $signature = 'portal:sync-status
                            {--limit=100 : Jumlah pengajuan maksimum yang diperiksa sekali jalan}';

    protected $description = 'Rekonsiliasi status pengajuan portal dengan flustra-erp (cadangan bila webhook gagal).';

    public function handle(ErpClient $erp, ErpEventApplier $applier): int
    {
        if (! config('portal.erp.token')) {
            $this->warn('ERP_API_TOKEN belum diisi. Rekonsiliasi dilewati.');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));

        $diantreUlang = $this->antreUlangYangBelumTerkirim($limit);
        $ditarik      = $this->tarikStatusTerbaru($erp, $applier, $limit);

        $this->info("Rekonsiliasi selesai: {$diantreUlang} pengajuan diantre ulang, {$ditarik} status ditarik dari ERP.");

        return self::SUCCESS;
    }

    /**
     * Pengajuan yang belum pernah sampai ke ERP.
     *
     * Batas 15 menit mencegah perintah ini menabrak job yang antreannya memang
     * sedang menunggu jeda backoff berikutnya.
     */
    protected function antreUlangYangBelumTerkirim(int $limit): int
    {
        $kandidat = Submission::withoutGlobalScope('milik_sendiri')
            ->whereIn('sync_state', ['pending', 'failed'])
            ->whereNotNull('submitted_at')
            ->whereNotIn('status', ['draft', 'approved', 'rejected', 'cancelled'])
            ->where('updated_at', '<=', now()->subMinutes(15))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($kandidat as $submission) {
            // ShouldBeUnique pada job-nya yang menjaga agar satu pengajuan tidak
            // punya dua job berjalan bersamaan.
            SyncSubmissionToErp::dispatch($submission->id);

            $this->line('  antre ulang: '.$submission->reference_number.' ('.$submission->sync_state.')');
        }

        return $kandidat->count();
    }

    /**
     * Pengajuan yang sudah sampai ke ERP tapi keputusannya belum tercermin di
     * portal.
     */
    protected function tarikStatusTerbaru(ErpClient $erp, ErpEventApplier $applier, int $limit): int
    {
        $kandidat = Submission::withoutGlobalScope('milik_sendiri')
            ->with('partnerLink')
            ->where('sync_state', 'synced')
            ->whereNotIn('status', ['draft', 'approved', 'rejected', 'cancelled'])
            ->orderBy('last_status_at')
            ->limit($limit)
            ->get();

        $diterapkan = 0;

        foreach ($kandidat as $submission) {
            try {
                // Klaim mitra punya jalurnya sendiri: yang berubah bukan hanya
                // status pengajuannya, tapi juga `partner_links` dan tipe akun
                // penggunanya. Itu urusan ErpEventApplier::claimVerified, bukan
                // sekadar perpindahan status.
                $berubah = $submission->type === 'partner_claim'
                    ? $this->tarikKlaim($erp, $applier, $submission)
                    : $this->tarikStatusUmum($erp, $applier, $submission);

                if ($berubah) {
                    $diterapkan++;
                }
            } catch (ErpException $e) {
                // Satu pengajuan yang bermasalah tidak boleh menghentikan
                // pemeriksaan sisanya.
                $this->warn('  gagal menarik '.$submission->reference_number.': '.$e->getMessage());

                Log::warning('Rekonsiliasi status pengajuan gagal.', [
                    'reference_number' => $submission->reference_number,
                    'error'            => $e->getMessage(),
                ]);
            }
        }

        return $diterapkan;
    }

    /**
     * Jenis pengajuan selain klaim, lewat `GET /submissions/{ref}/status`.
     *
     * 404 dari ERP berarti pengajuannya tidak pernah benar-benar sampai ke sana
     * walau portal menandainya 'synced' — bisa terjadi kalau respons pertama
     * hilang di tengah jalan. Yang tepat adalah mengirimnya ulang, bukan
     * menunggu status yang tidak akan pernah ada. ERP idempoten terhadap
     * `portal_reference`, jadi kiriman kedua aman.
     */
    protected function tarikStatusUmum(ErpClient $erp, ErpEventApplier $applier, Submission $submission): bool
    {
        try {
            $data = $erp->get(
                '/submissions/'.rawurlencode($submission->reference_number).'/status',
                [],
                $submission->id,
            )['data'] ?? [];
        } catch (ErpException $e) {
            if ($e->statusCode === 404) {
                $this->warn('  belum ada di ERP, dikirim ulang: '.$submission->reference_number);

                $submission->forceFill(['sync_state' => 'pending'])->save();
                SyncSubmissionToErp::dispatch($submission->id);

                return true;
            }

            throw $e;
        }

        $status = $data['status'] ?? null;

        if (! $status || ! in_array($status, $applier->allowedStatuses(), true)) {
            return false;
        }

        if ($submission->status === $status) {
            return false;
        }

        $this->line('  status menyusul dari ERP: '.$submission->reference_number.' → '.$status);

        return $applier->submissionStatusChanged(
            $submission,
            $status,
            $data['reason'] ?? null,
            $data['erp_reference'] ?? null,
        );
    }

    protected function tarikKlaim(ErpClient $erp, ErpEventApplier $applier, Submission $submission): bool
    {
        $link = $submission->partnerLink;

        if (! $link) {
            return false;
        }

        // Akses yang sudah dicabut tidak dibuka kembali oleh hasil polling.
        // Membuka akses adalah keputusan manusia, dan jalurnya adalah webhook
        // `claim.verified` yang dipicu staf — bukan tarikan berkala.
        if ($link->status === 'revoked') {
            $this->warn('  dilewati (akses sudah dicabut): '.$submission->reference_number);

            return false;
        }

        $data = $erp->get('/claims/'.rawurlencode($submission->reference_number), [], $submission->id)['data'] ?? [];

        $status = $data['status'] ?? null;

        if ($status === 'verified' && ! empty($data['erp_partner_id'])) {
            $this->line('  disetujui di ERP: '.$submission->reference_number);

            return $applier->claimVerified($link, (int) $data['erp_partner_id']);
        }

        if ($status === 'rejected') {
            $this->line('  ditolak di ERP: '.$submission->reference_number);

            return $applier->claimRejected($link, ($data['rejected_reason'] ?? null) ?: 'Tanpa alasan tertulis.');
        }

        // Masih 'pending' di ERP: belum ada yang perlu diberitahukan, tapi
        // pastikan pengguna setidaknya melihat "Diterima Sistem".
        if ($submission->status === 'submitted') {
            $submission->transitionTo(
                'received',
                'Pengajuan sudah diterima sistem Flustra dan menunggu pemeriksaan tim kami.',
                'erp',
            );

            return true;
        }

        return false;
    }
}
