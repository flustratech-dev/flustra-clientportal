<?php

namespace App\Http\Controllers\Layanan;

use App\Jobs\SyncSubmissionToErp;
use App\Models\ActivityLog;
use App\Models\Submission;
use App\Services\Erp\ErpCustomerApi;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Perbarui Data Perusahaan.
 *
 * Perubahan di sini **tidak pernah** langsung menimpa ERP. Yang dibuat adalah
 * pengajuan yang harus disetujui staf lebih dulu. Untuk pelanggan ini soal
 * ketertiban data; untuk vendor (Fase 4) ini soal uang — mengizinkan nomor
 * rekening diubah tanpa dilihat manusia adalah jalur penipuan pembayaran yang
 * paling umum, dan aturannya dibuat sama untuk kedua tipe supaya tidak ada
 * pengecualian yang bisa disalahpahami.
 *
 * Nilai lama ditampilkan dari ERP, dan ERP membandingkan ulang saat menerapkan:
 * kalau staf lain sudah mengubah kolom itu duluan, penerapannya dibatalkan
 * alih-alih menimpa pekerjaan orang diam-diam.
 */
class DataPerusahaanController extends LayananPelangganController
{
    public function edit(): View
    {
        $summary = $this->tarik(fn () => $this->erp->summary($this->user()), []);
        $link    = $this->erp->link($this->user());

        // ERP hanya mengembalikan sebagian kolom lewat /summary. Sisanya diisi
        // dari partner_links — salinan yang dikirim pengguna sendiri saat klaim.
        $nilaiSekarang = [
            'company'        => $summary['company'] ?? $link->company_name,
            'name'           => $summary['name'] ?? $link->company_name,
            'npwp'           => $link->npwp,
            'address'        => $link->address,
            'contact_person' => $link->contact_person,
            'phone'          => $link->phone,
            'email'          => $link->billing_email,
        ];

        $tertunda = Submission::where('type', 'profile_change')
            ->whereIn('status', ['submitted', 'received', 'under_review'])
            ->latest('id')
            ->first();

        return $this->halaman('layanan.data.edit', [
            'nilai'    => $nilaiSekarang,
            'labels'   => ErpCustomerApi::FIELD_LABELS,
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
            'reason'         => ['nullable', 'string', 'max:1000'],
        ]);

        $user   = $this->user();
        $reason = $data['reason'] ?? null;
        unset($data['reason']);

        // Kirim hanya yang benar-benar diisi. ERP membuang lagi yang nilainya
        // sama dengan yang sekarang, jadi pengajuan kosong tidak akan lolos.
        $perubahan = array_filter($data, fn ($v) => $v !== null && $v !== '');

        if (empty($perubahan)) {
            return back()->withInput()->withErrors([
                'company' => 'Isi minimal satu kolom yang ingin Anda ubah.',
            ]);
        }

        $submission = Submission::create([
            'user_id'          => $user->id,
            'partner_link_id'  => $this->erp->link($user)->id,
            'type'             => 'profile_change',
            'reference_number' => Submission::generateReference(),
            'title'            => 'Perubahan data perusahaan',
            'summary'          => 'Mengubah: '.implode(', ', array_map(
                fn ($f) => ErpCustomerApi::FIELD_LABELS[$f] ?? $f,
                array_keys($perubahan)
            )),
            'erp_module'    => 'portal_change_requests',
            'payload'       => ['changes' => $perubahan, 'reason' => $reason],
            'status'        => 'submitted',
            'submitted_at'  => now(),
            'last_status_at' => now(),
            'sync_state'    => 'pending',
        ]);

        $submission->histories()->create([
            'to_status'  => 'submitted',
            'note'       => 'Pengajuan perubahan data diterima dan menunggu peninjauan tim kami.',
            'actor_type' => 'portal',
            'actor_name' => $user->name,
            'created_at' => now(),
        ]);

        ActivityLog::log('profile_change_submitted', 'Mengajukan perubahan data: '.implode(', ', array_keys($perubahan)).'.');

        SyncSubmissionToErp::dispatch($submission->id);

        return redirect()->route('riwayat.show', $submission)->with(
            'success',
            'Pengajuan perubahan data terkirim dengan nomor '.$submission->reference_number
                .'. Perubahan berlaku setelah disetujui tim kami.'
        );
    }
}
