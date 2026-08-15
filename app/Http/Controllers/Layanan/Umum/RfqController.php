<?php

namespace App\Http\Controllers\Layanan\Umum;

use App\Jobs\SyncSubmissionToErp;
use App\Models\ActivityLog;
use App\Models\Submission;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Minta Penawaran (RFQ).
 *
 * Masuk ke pipeline CRM sebagai prospek, **bukan** langsung jadi pelanggan
 * aktif. Perbedaan itu penting: pelanggan aktif punya plafon kredit dan bisa
 * melihat data transaksi; prospek belum apa-apa selain nama yang tertarik.
 *
 * ERP memakai email sebagai kunci supaya satu calon pelanggan yang mengirim
 * tiga RFQ tidak beranak-pinak jadi tiga baris di CRM.
 */
class RfqController extends LayananUmumController
{
    public function create(): View
    {
        $user = $this->user();

        $riwayat = Submission::where('type', 'rfq')->latest('id')->limit(5)->get();

        return $this->halaman('layanan.umum.rfq.create', [
            'user'    => $user,
            'riwayat' => $riwayat,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_name'    => ['required', 'string', 'max:255'],
            'contact_name'    => ['required', 'string', 'max:255'],
            'email'           => ['required', 'email', 'max:255'],
            'phone'           => ['nullable', 'string', 'max:30'],
            'needs'           => ['required', 'string', 'max:2000'],
            'estimated_value' => ['nullable', 'numeric', 'min:0'],
        ], [
            'needs.required' => 'Ceritakan kebutuhan Anda agar tim kami bisa menyiapkan penawaran yang tepat.',
        ]);

        $user = $this->user();

        $submission = Submission::create([
            'user_id'          => $user->id,
            'type'             => 'rfq',
            'reference_number' => Submission::generateReference(),
            'title'            => 'Permintaan penawaran — '.$data['company_name'],
            'summary'          => \Illuminate\Support\Str::limit($data['needs'], 120),
            'amount'           => $data['estimated_value'] ?? null,
            'erp_module'       => 'sales_deals',
            'payload'          => $data,
            'status'           => 'submitted',
            'submitted_at'     => now(),
            'last_status_at'   => now(),
            'sync_state'       => 'pending',
        ]);

        $submission->histories()->create([
            'to_status'  => 'submitted',
            'note'       => 'Permintaan Anda diterima dan sedang diteruskan ke tim penjualan kami.',
            'actor_type' => 'portal',
            'actor_name' => $user->name,
            'created_at' => now(),
        ]);

        ActivityLog::log('rfq_submitted', 'Meminta penawaran untuk '.$data['company_name'].'.');

        SyncSubmissionToErp::dispatch($submission->id);

        return redirect()->route('riwayat.show', $submission)->with(
            'success',
            'Permintaan penawaran terkirim dengan nomor '.$submission->reference_number
                .'. Tim penjualan kami akan menghubungi Anda.'
        );
    }
}
