<?php

namespace App\Http\Controllers\Layanan\Vendor;

use App\Http\Controllers\Layanan\LayananVendorController;
use App\Jobs\SyncSubmissionToErp;
use App\Models\ActivityLog;
use App\Models\Submission;
use App\Services\Erp\ErpVendorApi;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Data & Rekening — halaman paling berisiko di seluruh portal.
 *
 * Vendor yang bisa mengganti nomor rekeningnya sendiri tanpa dilihat manusia
 * adalah jalur penipuan pembayaran yang paling umum: akun mitra dibajak,
 * rekening diganti, pembayaran berikutnya melayang ke rekening penipu. Karena
 * itu **tidak ada satu pun jalur di sini yang menulis langsung ke ERP** —
 * semuanya menjadi pengajuan yang harus disetujui staf.
 *
 * ERP menandai pengajuan yang menyentuh kolom rekening (`touches_bank_account`)
 * dan menampilkannya ke staf dengan peringatan tersendiri, lengkap dengan
 * anjuran memverifikasi lewat kontak resmi sebelum menerapkan.
 *
 * Halaman ini menyampaikan hal itu apa adanya ke vendor. Menyembunyikannya
 * hanya akan membuat mereka mengira pengajuannya gagal ketika sebenarnya
 * sedang diperiksa.
 */
class DataVendorController extends LayananVendorController
{
    public function edit(): View
    {
        $summary = $this->tarik(fn () => $this->erp->summary($this->user()), []);
        $link    = $this->erp->link($this->user());

        // ERP hanya mengembalikan sebagian kolom lewat /summary. Sisanya dari
        // partner_links — salinan yang dikirim vendor sendiri saat klaim.
        $nilai = [
            'company'        => $summary['company'] ?? $link?->company_name ?? '',
            'name'           => $summary['name'] ?? $link?->company_name ?? '',
            'npwp'           => $link?->npwp ?? '',
            'address'        => $link?->address ?? '',
            'contact_person' => $link?->contact_person ?? '',
            'phone'          => $link?->phone ?? '',
            'email'          => $link?->billing_email ?? '',
            'bank_name'      => null,
            'bank_account'   => null,
            'bank_holder'    => null,
        ];

        $tertunda = $link ? Submission::where('type', 'profile_change')
            ->whereIn('status', ['submitted', 'received', 'under_review'])
            ->latest('id')
            ->first() : null;

        return $this->halaman('layanan.vendor.data.edit', [
            'nilai'    => $nilai,
            'labels'   => ErpVendorApi::FIELD_LABELS,
            'rekening' => ErpVendorApi::FIELD_REKENING,
            'tertunda' => $tertunda,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company'        => ['nullable', 'string', 'max:255'],
            'name'           => ['nullable', 'string', 'max:255'],
            'npwp'           => ['nullable', 'string', 'max:50'],
            'address'        => ['nullable', 'string', 'max:1000'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone'          => ['nullable', 'string', 'max:30'],
            'email'          => ['nullable', 'email', 'max:255'],

            'bank_name'      => ['nullable', 'string', 'max:100'],
            'bank_account'   => ['nullable', 'string', 'max:50'],
            'bank_holder'    => ['nullable', 'string', 'max:255'],

            'reason'         => ['nullable', 'string', 'max:1000'],
        ]);

        $user   = $this->user();
        $reason = $data['reason'] ?? null;
        unset($data['reason']);

        $perubahan = array_filter($data, fn ($v) => $v !== null && $v !== '');

        if (empty($perubahan)) {
            return back()->withInput()->withErrors([
                'company' => 'Isi minimal satu kolom yang ingin Anda ubah.',
            ]);
        }

        $menyentuhRekening = (bool) array_intersect(array_keys($perubahan), ErpVendorApi::FIELD_REKENING);

        // Mengubah rekening tanpa menyebut alasan membuat staf harus menebak
        // saat memverifikasi lewat telepon. Diminta di sini, bukan di ERP,
        // supaya vendornya yang menjelaskan — bukan staf yang mengarang.
        if ($menyentuhRekening && ! $reason) {
            return back()->withInput()->withErrors([
                'reason' => 'Perubahan data rekening wajib disertai alasan. Tim kami akan menghubungi Anda lewat kontak resmi untuk memastikannya.',
            ]);
        }

        $submission = Submission::create([
            'user_id'          => $user->id,
            'partner_link_id'  => $this->erp->link($user)->id,
            'type'             => 'profile_change',
            'reference_number' => Submission::generateReference(),
            'title'            => $menyentuhRekening ? 'Perubahan data & rekening vendor' : 'Perubahan data vendor',
            'summary'          => 'Mengubah: '.implode(', ', array_map(
                fn ($f) => ErpVendorApi::FIELD_LABELS[$f] ?? $f,
                array_keys($perubahan)
            )),
            'erp_module'    => 'portal_change_requests',
            'payload'       => [
                'changes'           => $perubahan,
                'reason'            => $reason,
                'touches_bank'      => $menyentuhRekening,
            ],
            'status'         => 'submitted',
            'submitted_at'   => now(),
            'last_status_at' => now(),
            'sync_state'     => 'pending',
        ]);

        $submission->histories()->create([
            'to_status'  => 'submitted',
            'note'       => $menyentuhRekening
                ? 'Pengajuan diterima. Karena menyangkut data rekening, tim kami akan memverifikasinya lewat kontak resmi Anda sebelum menerapkannya.'
                : 'Pengajuan perubahan data diterima dan menunggu peninjauan tim kami.',
            'actor_type' => 'portal',
            'actor_name' => $user->name,
            'created_at' => now(),
        ]);

        ActivityLog::log(
            'vendor_change_submitted',
            'Mengajukan perubahan data vendor: '.implode(', ', array_keys($perubahan)).'.'
                .($menyentuhRekening ? ' TERMASUK DATA REKENING.' : '')
        );

        SyncSubmissionToErp::dispatch($submission->id);

        return redirect()->route('riwayat.show', $submission)->with(
            'success',
            'Pengajuan terkirim dengan nomor '.$submission->reference_number.'. '
                .($menyentuhRekening
                    ? 'Karena menyangkut rekening, tim kami akan menghubungi Anda lewat kontak resmi sebelum menerapkannya.'
                    : 'Perubahan berlaku setelah disetujui tim kami.')
        );
    }
}
