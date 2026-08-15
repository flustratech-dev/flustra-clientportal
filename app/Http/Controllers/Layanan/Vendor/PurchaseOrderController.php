<?php

namespace App\Http\Controllers\Layanan\Vendor;

use App\Http\Controllers\Layanan\LayananVendorController;
use App\Jobs\SyncSubmissionToErp;
use App\Models\ActivityLog;
use App\Models\Submission;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Purchase Order Masuk — dan konfirmasi kesanggupan vendor.
 *
 * Konfirmasi kesanggupan ini **belum pernah ada** di ERP sebelum portal:
 * PO dikirim lewat email atau WhatsApp, dan tim pembelian baru tahu vendornya
 * sanggup atau tidak ketika menelepon. Kolom `vendor_confirmation_*` di ERP
 * dibuat khusus untuk halaman ini.
 *
 * PO berstatus draft dan pending_approval tidak pernah sampai ke sini; ERP
 * menyaringnya — vendor tidak boleh melihat pesanan yang belum disetujui
 * internal.
 */
class PurchaseOrderController extends LayananVendorController
{
    public function index(Request $request): View
    {
        $hasil = $this->tarik(
            fn () => $this->erp->purchaseOrders($this->user(), max(1, (int) $request->input('page', 1))),
            ['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0]],
        );

        return $this->halaman('layanan.vendor.po.index', [
            'orders' => $hasil['data'],
            'meta'   => $hasil['meta'],
        ]);
    }

    /**
     * Sanggup atau tidak, beserta tanggal kirim yang dijanjikan.
     *
     * Disimpan sebagai Submission dulu lalu diantre, sama seperti seluruh sisi
     * tulis portal: janji kirim yang hilang karena ERP sedang mati akan
     * ditagih ke vendor yang sudah merasa memberitahu.
     */
    public function confirm(Request $request, int $purchaseOrder): RedirectResponse
    {
        $data = $request->validate([
            'status'        => ['required', 'in:accepted,rejected'],
            'promised_date' => ['nullable', 'required_if:status,accepted', 'date', 'after_or_equal:today'],
            'notes'         => ['nullable', 'string', 'max:1000'],
            'number'        => ['required', 'string', 'max:100'],
        ], [
            'promised_date.required_if'      => 'Sebutkan tanggal kirim yang Anda sanggupi.',
            'promised_date.after_or_equal'   => 'Tanggal kirim tidak boleh di masa lalu.',
        ]);

        $user   = $this->user();
        $sanggup = $data['status'] === 'accepted';

        $submission = Submission::create([
            'user_id'          => $user->id,
            'partner_link_id'  => $this->erp->link($user)->id,
            'type'             => 'po_confirmation',
            'reference_number' => Submission::generateReference(),
            'title'            => ($sanggup ? 'Menyanggupi' : 'Menolak').' purchase order '.$data['number'],
            'summary'          => $sanggup && $data['promised_date']
                ? 'Janji kirim '.$data['promised_date']
                : ($data['notes'] ?: null),
            'erp_module'    => 'purchase_orders',
            'erp_record_id' => $purchaseOrder,
            'erp_reference' => $data['number'],
            'payload'       => [
                'purchase_order_id' => $purchaseOrder,
                'status'            => $data['status'],
                'promised_date'     => $data['promised_date'] ?? null,
                'notes'             => $data['notes'] ?? null,
            ],
            'status'         => 'submitted',
            'submitted_at'   => now(),
            'last_status_at' => now(),
            'sync_state'     => 'pending',
        ]);

        $submission->histories()->create([
            'to_status'  => 'submitted',
            'note'       => 'Konfirmasi Anda tercatat dan sedang diteruskan ke tim pembelian kami.',
            'actor_type' => 'portal',
            'actor_name' => $user->name,
            'created_at' => now(),
        ]);

        ActivityLog::log(
            'po_confirmation',
            'Vendor '.($sanggup ? 'menyanggupi' : 'menolak').' PO '.$data['number'].'.'
        );

        SyncSubmissionToErp::dispatch($submission->id);

        return redirect()->route('vendor.po.index')->with(
            'success',
            $sanggup
                ? 'Terima kasih. Kesanggupan Anda atas '.$data['number'].' sudah kami catat beserta tanggal kirimnya.'
                : 'Penolakan Anda atas '.$data['number'].' sudah kami catat. Tim pembelian akan menghubungi Anda.'
        );
    }
}
