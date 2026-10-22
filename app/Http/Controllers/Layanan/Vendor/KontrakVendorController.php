<?php

namespace App\Http\Controllers\Layanan\Vendor;

use App\Http\Controllers\Layanan\LayananVendorController;
use App\Jobs\SyncSubmissionToErp;
use App\Models\ActivityLog;
use App\Models\Submission;
use App\Services\Erp\ErpException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Kontrak & Dokumen — sisi vendor.
 *
 * Sama bentuknya dengan kontrak pelanggan, dan memang tabel yang sama di ERP:
 * `contracts` kini punya `vendor_id` di samping `customer_id`, dengan tepat
 * satu di antaranya terisi. Persetujuannya pun memakai kolom `customer_ack_*`
 * yang sama — nama kolomnya peninggalan sejarah, artinya "pihak lawan
 * menyetujui".
 */
class KontrakVendorController extends LayananVendorController
{
    public function index(): View
    {
        $contracts = $this->tarik(fn () => $this->erp->contracts($this->user()), []);

        return $this->halaman('layanan.vendor.kontrak.index', ['contracts' => $contracts]);
    }

    /** Alirkan ulang naskah PKS vendor (PDF) dari ERP — penjelasannya sama dengan sisi pelanggan. */
    public function pdf(int $contract): Response|RedirectResponse
    {
        try {
            $isi = $this->erp->contractPdf($this->user(), $contract);
        } catch (ErpException $e) {
            if ($e->statusCode === 404) {
                abort(404);
            }

            return redirect()
                ->route('vendor.kontrak.index')
                ->with('error', 'Naskah perjanjian sedang tidak bisa kami ambil. Coba lagi beberapa saat lagi.');
        }

        return response($isi, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="perjanjian-kerja-sama-'.$contract.'.pdf"',
        ]);
    }

    public function acknowledge(Request $request, int $contract): RedirectResponse
    {
        $data = $request->validate([
            'title'  => ['required', 'string', 'max:255'],
            'setuju' => ['accepted'],
        ], [
            'setuju.accepted' => 'Centang pernyataan persetujuan terlebih dahulu.',
        ]);

        $user = $this->user();

        $submission = Submission::create([
            'user_id'          => $user->id,
            'partner_link_id'  => $this->erp->link($user)->id,
            'type'             => 'contract_ack',
            'reference_number' => Submission::generateReference(),
            'title'            => 'Persetujuan kontrak: '.$data['title'],
            'summary'          => 'Disetujui atas nama '.$user->name.'.',
            'erp_module'       => 'contracts',
            'erp_record_id'    => $contract,
            'erp_reference'    => $data['title'],
            'payload'          => [
                'contract_id' => $contract,
                'actor_name'  => $user->name,
                'actor_ip'    => $request->ip(),
            ],
            'status'         => 'submitted',
            'submitted_at'   => now(),
            'last_status_at' => now(),
            'sync_state'     => 'pending',
        ]);

        $submission->histories()->create([
            'to_status'  => 'submitted',
            'note'       => 'Persetujuan Anda tercatat beserta waktu dan alamat IP.',
            'actor_type' => 'portal',
            'actor_name' => $user->name,
            'created_at' => now(),
        ]);

        ActivityLog::log('contract_acknowledged', 'Menyetujui kontrak "'.$data['title'].'".');

        SyncSubmissionToErp::dispatch($submission->id);

        return redirect()->route('vendor.kontrak.index')
            ->with('success', 'Persetujuan Anda atas kontrak "'.$data['title'].'" sudah tercatat.');
    }
}
