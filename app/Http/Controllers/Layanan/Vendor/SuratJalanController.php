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
 * Surat Jalan — dikirim vendor SEBELUM barangnya tiba.
 *
 * Gunanya memberi tahu gudang apa yang sedang dalam perjalanan, supaya mereka
 * bisa menyiapkan tempat dan tenaga. Ini bukan penerimaan barang: pencatatan
 * penerimaan tetap dilakukan staf gudang saat barangnya benar-benar sampai
 * dan dihitung — yang datang lebih dulu tidak boleh dianggap sudah tiba.
 */
class SuratJalanController extends LayananVendorController
{
    public function index(Request $request): View
    {
        $riwayat = $this->tarik(
            fn () => $this->erp->shippingDocuments($this->user(), max(1, (int) $request->input('page', 1))),
            ['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0]],
        );

        $po = $this->tarik(fn () => $this->erp->purchaseOrders($this->user(), 1), ['data' => []]);

        return $this->halaman('layanan.vendor.surat-jalan.index', [
            'documents' => $riwayat['data'],
            'meta'      => $riwayat['meta'],
            'orders'    => $po['data'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'purchase_order_id' => ['nullable', 'integer', 'min:1'],
            'po_number'         => ['nullable', 'string', 'max:100'],
            'document_number'   => ['required', 'string', 'max:100'],
            'shipped_date'      => ['required', 'date', 'before_or_equal:today'],
            'estimated_arrival' => ['nullable', 'date', 'after_or_equal:shipped_date'],
            'courier'           => ['nullable', 'string', 'max:255'],
            'tracking_number'   => ['nullable', 'string', 'max:255'],
            'notes'             => ['nullable', 'string', 'max:1000'],
            'document'          => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.config('portal.max_upload_kb')],
        ], [
            'shipped_date.before_or_equal'    => 'Tanggal kirim tidak boleh di masa depan.',
            'estimated_arrival.after_or_equal' => 'Perkiraan tiba tidak boleh sebelum tanggal kirim.',
        ]);

        $user = $this->user();
        $file = $request->file('document');
        $path = $file?->store('surat-jalan', 'private');

        $submission = DB::transaction(function () use ($user, $data, $path, $file) {
            $submission = Submission::create([
                'user_id'          => $user->id,
                'partner_link_id'  => $this->erp->link($user)->id,
                'type'             => 'shipping_doc',
                'reference_number' => Submission::generateReference(),
                'title'            => 'Surat jalan '.$data['document_number']
                    .(($data['po_number'] ?? null) ? ' untuk '.$data['po_number'] : ''),
                'summary'          => ($data['courier'] ?? null) ? 'Kurir '.$data['courier'] : null,
                'erp_module'       => 'portal_shipping_documents',
                'erp_reference'    => $data['document_number'],
                'payload'          => [
                    'purchase_order_id' => $data['purchase_order_id'] ?? null,
                    'document_number'   => $data['document_number'],
                    'shipped_date'      => $data['shipped_date'],
                    'estimated_arrival' => $data['estimated_arrival'] ?? null,
                    'courier'           => $data['courier'] ?? null,
                    'tracking_number'   => $data['tracking_number'] ?? null,
                    'notes'             => $data['notes'] ?? null,
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
                'note'       => 'Surat jalan diterima dan sedang diteruskan ke tim gudang kami.',
                'actor_type' => 'portal',
                'actor_name' => $user->name,
                'created_at' => now(),
            ]);

            return $submission;
        });

        ActivityLog::log('shipping_doc_submitted', 'Mengirim surat jalan '.$data['document_number'].'.');

        SyncSubmissionToErp::dispatch($submission->id);

        return redirect()->route('vendor.surat-jalan.index')->with(
            'success',
            'Surat jalan '.$data['document_number'].' terkirim. Tim gudang kami sudah diberi tahu.'
        );
    }
}
