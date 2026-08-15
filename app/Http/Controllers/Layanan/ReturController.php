<?php

namespace App\Http\Controllers\Layanan;

use App\Jobs\SyncSubmissionToErp;
use App\Models\ActivityLog;
use App\Models\Submission;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ajukan Retur — pengembalian barang beserta alasan dan fotonya.
 *
 * Barang yang bisa diretur diambil dari rincian invoice-nya sendiri, bukan
 * diketik bebas: pelanggan memilih dari daftar yang memang pernah dia beli.
 * Baris invoice tanpa `product_id` (deskripsi bebas, mis. biaya kirim) tidak
 * muncul di pilihan — ERP butuh produk yang nyata untuk mencatat returnya.
 *
 * `sales_returns.created_by` di ERP sudah dibuat nullable untuk ini: retur dari
 * pelanggan tidak punya staf internal yang membuatnya.
 */
class ReturController extends LayananPelangganController
{
    /** Alasan yang tersedia. Ditulis dengan bahasa pelanggan, bukan istilah gudang. */
    public const ALASAN = [
        'damaged'    => 'Barang rusak atau cacat',
        'wrong_item' => 'Barang tidak sesuai pesanan',
        'quantity'   => 'Jumlah tidak sesuai',
        'quality'    => 'Kualitas tidak sesuai harapan',
        'other'      => 'Lainnya',
    ];

    public function create(Request $request): View
    {
        $invoiceId = (int) $request->input('invoice');
        $invoice   = [];

        if ($invoiceId > 0) {
            $invoice = $this->tarikSatu(fn () => $this->erp->invoice($this->user(), $invoiceId));
        }

        $hasil = $this->tarik(fn () => $this->erp->invoices($this->user(), [], 1), ['data' => []]);

        return $this->halaman('layanan.retur.create', [
            'invoices' => $hasil['data'],
            'invoice'  => $invoice,
            'alasan'   => self::ALASAN,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'invoice_id'     => ['required', 'integer', 'min:1'],
            'invoice_number' => ['required', 'string', 'max:100'],
            'product_id'     => ['required', 'integer', 'min:1'],
            'product_name'   => ['required', 'string', 'max:255'],
            'qty'            => ['required', 'numeric', 'min:0.01'],
            'reason_type'    => ['required', 'in:'.implode(',', array_keys(self::ALASAN))],
            'reason'         => ['required', 'string', 'max:1000'],
            'photo'          => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.config('portal.max_upload_kb')],
        ], [
            'reason.required'     => 'Ceritakan singkat kondisi barangnya agar tim kami bisa menilai.',
            'product_id.required' => 'Pilih barang yang ingin Anda kembalikan.',
        ]);

        $user = $this->user();
        $file = $request->file('photo');
        $path = $file?->store('bukti-retur', 'private');

        $submission = DB::transaction(function () use ($user, $data, $path, $file) {
            $submission = Submission::create([
                'user_id'          => $user->id,
                'partner_link_id'  => $this->erp->link($user)->id,
                'type'             => 'sales_return',
                'reference_number' => Submission::generateReference(),
                'title'            => 'Retur '.$data['product_name'].' dari tagihan '.$data['invoice_number'],
                'summary'          => self::ALASAN[$data['reason_type']],
                'erp_module'       => 'sales_returns',
                'erp_reference'    => $data['invoice_number'],
                'payload'          => [
                    'invoice_id'   => $data['invoice_id'],
                    'product_id'   => $data['product_id'],
                    'product_name' => $data['product_name'],
                    'qty'          => $data['qty'],
                    'reason_type'  => $data['reason_type'],
                    'reason'       => $data['reason'],
                ],
                'status'         => 'submitted',
                'submitted_at'   => now(),
                'last_status_at' => now(),
                'sync_state'     => 'pending',
            ]);

            if ($path && $file) {
                $submission->attachments()->create([
                    'disk'          => 'private',
                    'path'          => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime'          => $file->getClientMimeType(),
                    'size'          => $file->getSize(),
                ]);
            }

            $submission->histories()->create([
                'to_status'  => 'submitted',
                'note'       => 'Pengajuan retur diterima dan sedang diteruskan ke tim kami.',
                'actor_type' => 'portal',
                'actor_name' => $user->name,
                'created_at' => now(),
            ]);

            return $submission;
        });

        ActivityLog::log(
            'sales_return_submitted',
            'Mengajukan retur '.$data['qty'].' '.$data['product_name'].' atas tagihan '.$data['invoice_number'].'.'
        );

        SyncSubmissionToErp::dispatch($submission->id);

        return redirect()->route('riwayat.show', $submission)->with(
            'success',
            'Pengajuan retur terkirim dengan nomor '.$submission->reference_number
                .'. Tim kami akan memeriksanya dan mengabari Anda.'
        );
    }
}
