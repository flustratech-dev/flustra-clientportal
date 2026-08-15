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
 * Penawaran — pelanggan meninjau lalu menyetujui atau menolaknya sendiri.
 *
 * Selama ini staf yang mengklik `accepted`/`rejected` atas nama pelanggan.
 * Sejak halaman ini ada, keputusannya datang dari pemiliknya, lengkap dengan
 * nama, waktu, dan IP — jejak yang sah bila kelak dipertanyakan.
 *
 * Penawaran berstatus `draft` tidak pernah sampai ke sini; ERP menyaringnya.
 */
class PenawaranController extends LayananPelangganController
{
    public function index(Request $request): View
    {
        $hasil = $this->tarik(
            fn () => $this->erp->quotations($this->user(), max(1, (int) $request->input('page', 1))),
            ['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0]],
        );

        return $this->halaman('layanan.penawaran.index', [
            'quotations' => $hasil['data'],
            'meta'       => $hasil['meta'],
        ]);
    }

    /**
     * Keputusan pelanggan.
     *
     * Disimpan sebagai Submission dulu, baru diantre ke ERP. Kalau ERP sedang
     * mati, keputusan tetap tercatat dan tetap terkirim nanti — pelanggan tidak
     * perlu mengklik dua kali dan tidak perlu tahu apa pun soal ini.
     */
    public function decide(Request $request, int $quotation): RedirectResponse
    {
        // `number` dan `amount` hanya untuk judul & nominal di halaman Riwayat
        // pengguna sendiri — bukan bagian dari keputusan. Yang menentukan
        // penawaran mana yang diputuskan adalah $quotation dari URL, dan ERP
        // memvalidasinya terhadap customer_id pemiliknya. Nilai sebenarnya
        // ditimpa oleh jawaban ERP setelah tersinkron.
        $data = $request->validate([
            'decision' => ['required', 'in:accepted,rejected'],
            'note'     => ['nullable', 'string', 'max:1000'],
            'number'   => ['required', 'string', 'max:100'],
            'amount'   => ['nullable', 'numeric'],
        ], [
            'decision.required' => 'Pilih setuju atau tolak terlebih dahulu.',
        ]);

        $user     = $this->user();
        $setuju   = $data['decision'] === 'accepted';
        $keputusan = $setuju ? 'menyetujui' : 'menolak';

        $submission = DB::transaction(fn () => Submission::create([
            'user_id'          => $user->id,
            'partner_link_id'  => $this->erp->link($user)->id,
            'type'             => 'quotation_decision',
            'reference_number' => Submission::generateReference(),
            'title'            => ($setuju ? 'Menyetujui' : 'Menolak').' penawaran '.$data['number'],
            'summary'          => ($data['note'] ?? null) ?: null,
            'amount'           => $data['amount'] ?? null,
            'erp_module'       => 'quotations',
            'erp_record_id'    => $quotation,
            'erp_reference'    => $data['number'],
            'payload'          => [
                'quotation_id' => $quotation,
                'decision'     => $data['decision'],
                'note'         => $data['note'] ?? null,
                'actor_name'   => $user->name,
                'actor_ip'     => $request->ip(),
            ],
            'status'         => 'submitted',
            'submitted_at'   => now(),
            'last_status_at' => now(),
            'sync_state'     => 'pending',
        ]));

        $submission->histories()->create([
            'to_status'  => 'submitted',
            'note'       => 'Keputusan Anda tercatat dan sedang diteruskan ke tim kami.',
            'actor_type' => 'portal',
            'actor_name' => $user->name,
            'created_at' => now(),
        ]);

        ActivityLog::log('quotation_decision', 'Pelanggan '.$keputusan.' penawaran '.$data['number'].'.');

        SyncSubmissionToErp::dispatch($submission->id);

        return redirect()->route('layanan.penawaran.index')->with(
            'success',
            'Terima kasih. Keputusan Anda untuk penawaran '.$data['number'].' sudah kami catat'
                .($setuju ? ' dan tim kami akan menindaklanjuti pesanannya.' : '.')
        );
    }
}
