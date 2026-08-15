<?php

namespace App\Http\Controllers\Layanan;

use App\Jobs\SyncSubmissionToErp;
use App\Models\ActivityLog;
use App\Models\Submission;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Kontrak Kerja Sama — lihat dan setujui.
 *
 * Persetujuan di sini adalah *acknowledgement*: nama, waktu, IP. Ini jembatan,
 * bukan pengganti permanen — pemilik produk sudah memutuskan kontrak butuh
 * tanda tangan digital bersertifikat (Privy/VIDA/Digisign/Peruri), dan kolom
 * `signature_*` di ERP sudah menunggu penyedianya. Sampai integrasi itu ada di
 * Fase 5, halaman ini jujur menyebut dirinya persetujuan, bukan tanda tangan.
 */
class KontrakController extends LayananPelangganController
{
    public function index(): View
    {
        $contracts = $this->tarik(fn () => $this->erp->contracts($this->user()), []);

        return $this->halaman('layanan.kontrak.index', ['contracts' => $contracts]);
    }

    public function acknowledge(Request $request, int $contract): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
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

        return redirect()->route('layanan.kontrak.index')
            ->with('success', 'Persetujuan Anda atas kontrak "'.$data['title'].'" sudah tercatat.');
    }
}
