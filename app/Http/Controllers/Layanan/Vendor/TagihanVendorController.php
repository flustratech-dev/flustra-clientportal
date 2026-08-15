<?php

namespace App\Http\Controllers\Layanan\Vendor;

use App\Http\Controllers\Layanan\LayananVendorController;
use App\Jobs\SyncSubmissionToErp;
use App\Models\ActivityLog;
use App\Models\Submission;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Kirim Tagihan — vendor menagihkan PO yang barangnya sudah dikirim.
 *
 * Sebelum ini fakturnya diketik ulang staf finance dari PDF yang dikirim lewat
 * email. Sejak halaman ini ada, vendor mengisi sendiri rincian dan nominalnya,
 * dan ERP langsung membandingkannya dengan nilai PO — selisih ditandai sejak
 * awal (`has_discrepancy`), bukan ketahuan saat mau dibayar.
 *
 * Faktur aslinya wajib diunggah: rincian yang diketik vendor adalah data,
 * dokumen aslinya adalah bukti. Keduanya diperlukan.
 */
class TagihanVendorController extends LayananVendorController
{
    public function create(Request $request): View
    {
        $hasil = $this->tarik(
            fn () => $this->erp->purchaseOrders($this->user(), 1),
            ['data' => []],
        );

        // Hanya PO yang sudah disanggupi vendor yang boleh ditagihkan.
        // Menagih PO yang belum dikonfirmasi hampir selalu berarti salah pilih,
        // dan itu berakhir jadi pekerjaan pembatalan di sisi finance.
        $orders = array_values(array_filter(
            $hasil['data'],
            fn (array $po) => ($po['vendor_confirmation_status'] ?? null) === 'accepted'
        ));

        return $this->halaman('layanan.vendor.tagihan.create', [
            'orders'   => $orders,
            'terpilih' => (int) $request->input('po'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'purchase_order_id' => ['required', 'integer', 'min:1'],
            'po_number'         => ['required', 'string', 'max:100'],
            'bill_number'       => ['required', 'string', 'max:100'],
            'bill_date'         => ['required', 'date', 'before_or_equal:today'],
            'due_date'          => ['nullable', 'date', 'after_or_equal:bill_date'],
            'amount'            => ['required', 'numeric', 'min:1'],
            'document'          => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.config('portal.max_upload_kb')],

            'items'               => ['required', 'array', 'min:1'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity'    => ['required', 'numeric', 'min:0.01'],
            'items.*.price'       => ['required', 'numeric', 'min:0'],
        ], [
            'document.required'          => 'Faktur asli wajib diunggah sebagai bukti tagihan.',
            'items.required'             => 'Isi minimal satu baris rincian tagihan.',
            'bill_date.before_or_equal'  => 'Tanggal faktur tidak boleh di masa depan.',
        ]);

        $user = $this->user();
        $file = $request->file('document');
        $path = $file->store('faktur-vendor', 'private');

        $submission = DB::transaction(function () use ($user, $data, $path, $file) {
            $submission = Submission::create([
                'user_id'          => $user->id,
                'partner_link_id'  => $this->erp->link($user)->id,
                'type'             => 'vendor_bill',
                'reference_number' => Submission::generateReference(),
                'title'            => 'Tagihan '.$data['bill_number'].' atas '.$data['po_number'],
                'summary'          => count($data['items']).' baris rincian',
                'amount'           => $data['amount'],
                'erp_module'       => 'purchase_bills',
                'erp_reference'    => $data['bill_number'],
                'payload'          => [
                    'purchase_order_id' => $data['purchase_order_id'],
                    'po_number'         => $data['po_number'],
                    'bill_number'       => $data['bill_number'],
                    'bill_date'         => $data['bill_date'],
                    'due_date'          => $data['due_date'] ?? null,
                    'amount'            => $data['amount'],
                    'items'             => array_values($data['items']),
                ],
                'status'         => 'submitted',
                'submitted_at'   => now(),
                'last_status_at' => now(),
                'sync_state'     => 'pending',
            ]);

            $submission->attachments()->create([
                'disk'          => 'private',
                'path'          => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime'          => $file->getClientMimeType(),
                'size'          => $file->getSize(),
            ]);

            $submission->histories()->create([
                'to_status'  => 'submitted',
                'note'       => 'Tagihan Anda diterima dan sedang diteruskan ke tim kami.',
                'actor_type' => 'portal',
                'actor_name' => $user->name,
                'created_at' => now(),
            ]);

            return $submission;
        });

        ActivityLog::log(
            'vendor_bill_submitted',
            'Mengirim tagihan '.$data['bill_number'].' senilai Rp '
                .number_format((float) $data['amount'], 0, ',', '.').' atas '.$data['po_number'].'.'
        );

        SyncSubmissionToErp::dispatch($submission->id);

        return redirect()->route('riwayat.show', $submission)->with(
            'success',
            'Tagihan terkirim dengan nomor pengajuan '.$submission->reference_number
                .'. Tim kami akan meninjau dan mengabari Anda bila ada yang perlu dikonfirmasi.'
        );
    }
}
