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
 * Retur & Selisih — retur pembelian, nota debit, dan sanggahan vendor.
 *
 * Sanggahan **tidak** membatalkan retur atau nota debitnya. Membatalkan nota
 * debit adalah keputusan akuntansi yang harus diambil manusia; yang dilakukan
 * halaman ini hanya memastikan keberatan vendor sampai ke orang yang
 * berwenang, lengkap dengan waktunya dan bukti pendukungnya.
 */
class ReturVendorController extends LayananVendorController
{
    public function index(Request $request): View
    {
        $hasil = $this->tarik(
            fn () => $this->erp->returns($this->user(), max(1, (int) $request->input('page', 1))),
            ['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0]],
        );

        // Sanggahan yang pernah dikirim, supaya vendor tidak mengirim dua kali
        // untuk retur yang sama dan tahu di mana posisinya.
        $sanggahan = Submission::where('type', 'return_dispute')
            ->get()
            ->keyBy(fn (Submission $s) => $s->erp_record_id);

        return $this->halaman('layanan.vendor.retur.index', [
            'returns'   => $hasil['data'],
            'meta'      => $hasil['meta'],
            'sanggahan' => $sanggahan,
        ]);
    }

    public function dispute(Request $request, int $purchaseReturn): RedirectResponse
    {
        $data = $request->validate([
            'number' => ['required', 'string', 'max:100'],
            'reason' => ['required', 'string', 'max:1000'],
            'bukti'  => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.config('portal.max_upload_kb')],
        ], [
            'reason.required' => 'Jelaskan keberatan Anda agar tim kami bisa menilainya.',
        ]);

        $user = $this->user();

        $sudahAda = Submission::where('type', 'return_dispute')
            ->where('erp_record_id', $purchaseReturn)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->exists();

        if ($sudahAda) {
            return back()->with('error', 'Anda sudah mengirim sanggahan untuk retur ini. Tim kami sedang meninjaunya.');
        }

        $file = $request->file('bukti');
        $path = $file?->store('sanggahan-retur', 'private');

        $submission = DB::transaction(function () use ($user, $data, $purchaseReturn, $path, $file) {
            $submission = Submission::create([
                'user_id'          => $user->id,
                'partner_link_id'  => $this->erp->link($user)->id,
                'type'             => 'return_dispute',
                'reference_number' => Submission::generateReference(),
                'title'            => 'Sanggahan atas retur '.$data['number'],
                'summary'          => \Illuminate\Support\Str::limit($data['reason'], 120),
                'erp_module'       => 'purchase_returns',
                'erp_record_id'    => $purchaseReturn,
                'erp_reference'    => $data['number'],
                'payload'          => [
                    'return_id' => $purchaseReturn,
                    'reason'    => $data['reason'],
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
                'note'       => 'Sanggahan Anda diterima dan sedang diteruskan ke tim kami.',
                'actor_type' => 'portal',
                'actor_name' => $user->name,
                'created_at' => now(),
            ]);

            return $submission;
        });

        ActivityLog::log('return_dispute_submitted', 'Menyanggah retur pembelian '.$data['number'].'.');

        SyncSubmissionToErp::dispatch($submission->id);

        return redirect()->route('vendor.retur.index')->with(
            'success',
            'Sanggahan Anda atas retur '.$data['number'].' terkirim dengan nomor '.$submission->reference_number
                .'. Tim kami akan meninjaunya dan mengabari hasilnya.'
        );
    }
}
