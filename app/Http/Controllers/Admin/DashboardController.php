<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncSubmissionToErp;
use App\Models\ApiSyncLog;
use App\Models\PartnerLink;
use App\Models\Submission;
use App\Models\User;
use App\Services\Erp\ErpClient;
use App\Services\Erp\ErpException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ruang pantau admin portal.
 *
 * Menjawab satu pertanyaan: **apakah portal ini sedang sehat?** Yang dianggap
 * tidak sehat: pengajuan yang gagal terkirim ke ERP, panggilan API yang
 * ditolak, dan antrean yang menumpuk.
 *
 * Sengaja TIDAK bisa menyetujui apa pun. Keputusan atas data mitra ada di ERP,
 * di tangan staf yang berwenang. Admin portal hanya melihat, mengantre ulang
 * kiriman yang gagal, dan memasang pengumuman.
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $tanpaScope = fn () => Submission::withoutGlobalScope('milik_sendiri');

        $ringkasan = [
            'pengguna'       => User::where('role', 'mitra')->count(),
            'mitra_terverif' => PartnerLink::where('status', 'verified')->count(),
            'klaim_menunggu' => PartnerLink::where('status', 'pending')->count(),
            'pengajuan'      => $tanpaScope()->count(),
            'gagal_sinkron'  => $tanpaScope()->where('sync_state', 'failed')->count(),
            'belum_terkirim' => $tanpaScope()->where('sync_state', 'pending')->whereNotNull('submitted_at')->count(),
            'antrean_job'    => DB::table('jobs')->count(),
            'job_gagal'      => DB::table('failed_jobs')->count(),
        ];

        // Panggilan ke ERP dalam 24 jam terakhir, dipisah berhasil/gagal.
        $sejak = now()->subDay();

        $lalulintas = [
            'keluar'       => ApiSyncLog::where('direction', 'outbound')->where('created_at', '>=', $sejak)->count(),
            'keluar_gagal' => ApiSyncLog::where('direction', 'outbound')->where('created_at', '>=', $sejak)
                ->where(fn ($q) => $q->whereNull('status_code')->orWhere('status_code', '>=', 400))->count(),
            'masuk'        => ApiSyncLog::where('direction', 'inbound')->where('created_at', '>=', $sejak)->count(),
            'masuk_ditolak' => ApiSyncLog::where('direction', 'inbound')->where('created_at', '>=', $sejak)
                ->where('status_code', '>=', 400)->count(),
        ];

        $gagal = $tanpaScope()
            ->with('user:id,name,email')
            ->where('sync_state', 'failed')
            ->latest('id')
            ->limit(20)
            ->get();

        $logTerakhir = ApiSyncLog::latest('id')->limit(20)->get();

        return view('admin.dashboard', compact('ringkasan', 'lalulintas', 'gagal', 'logTerakhir')
            + ['erpSehat' => $this->cekErp()]);
    }

    /**
     * Antre ulang satu pengajuan yang gagal.
     *
     * Tidak memaksa kirim langsung — tetap lewat antrean, dengan backoff yang
     * sama. Kalau ERP masih mati, memaksanya di dalam request hanya akan
     * membuat halaman admin ikut menggantung.
     */
    public function antreUlang(int $submission): RedirectResponse
    {
        $s = Submission::withoutGlobalScope('milik_sendiri')->findOrFail($submission);

        if ($s->sync_state === 'synced') {
            return back()->with('error', 'Pengajuan '.$s->reference_number.' sudah tersinkron.');
        }

        $s->forceFill(['sync_state' => 'pending', 'sync_error' => null])->save();

        SyncSubmissionToErp::dispatch($s->id);

        return back()->with('success', 'Pengajuan '.$s->reference_number.' diantre ulang.');
    }

    public function antreUlangSemua(): RedirectResponse
    {
        $gagal = Submission::withoutGlobalScope('milik_sendiri')
            ->where('sync_state', 'failed')
            ->get();

        foreach ($gagal as $s) {
            $s->forceFill(['sync_state' => 'pending', 'sync_error' => null])->save();
            SyncSubmissionToErp::dispatch($s->id);
        }

        return back()->with('success', $gagal->count().' pengajuan diantre ulang.');
    }

    /**
     * Ketuk ERP untuk memastikan sambungannya hidup.
     *
     * Memakai endpoint lowongan karena itu satu-satunya yang tidak butuh
     * konteks mitra — memanggil endpoint mitra hanya untuk cek kesehatan
     * berarti meminjam identitas orang.
     */
    protected function cekErp(): array
    {
        try {
            app(ErpClient::class)->get('/vacancies');

            return ['sehat' => true, 'pesan' => 'Sambungan ke sistem internal normal.'];
        } catch (ErpException $e) {
            return ['sehat' => false, 'pesan' => $e->getMessage()];
        }
    }
}
