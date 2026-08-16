<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Services\ServiceCatalog;
use Illuminate\Support\Facades\Auth;

/**
 * Beranda: grid kartu layanan sesuai tipe akun, plus ringkasan singkat.
 */
class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $services = collect(ServiceCatalog::forUser($user))->groupBy('group');

        // Empat angka, satu query. Dulu masing-masing punya SELECT COUNT(*)
        // sendiri — empat perjalanan ke basis data untuk memindai tabel yang
        // sama persis, di halaman yang dibuka setiap mitra setiap kali masuk.
        // Query submissions tetap otomatis terbatas ke pengguna ini lewat
        // global scope 'milik_sendiri'.
        $hitung = Submission::selectRaw(
            "SUM(CASE WHEN status IN ('submitted','received','under_review') THEN 1 ELSE 0 END) AS diproses,
             SUM(CASE WHEN status = 'approved' AND last_status_at >= ? THEN 1 ELSE 0 END) AS disetujui,
             SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS ditolak,
             SUM(CASE WHEN status <> 'draft' THEN 1 ELSE 0 END) AS total",
            [now()->startOfMonth()]
        )->first();

        $stats = [
            'diproses'  => (int) ($hitung->diproses ?? 0),
            'disetujui' => (int) ($hitung->disetujui ?? 0),
            'ditolak'   => (int) ($hitung->ditolak ?? 0),
            'total'     => (int) ($hitung->total ?? 0),
        ];

        $recent = Submission::whereNot('status', 'draft')
            ->latest('submitted_at')
            ->limit(5)
            ->get();

        // Dua klaim terakhir dari satu query, bukan dua. Jumlah partner_links
        // per akun selalu kecil (satu peran, kadang dua), jadi menyaringnya di
        // PHP lebih murah daripada perjalanan kedua ke basis data.
        $klaim = $user->partnerLinks()
            ->whereIn('status', ['pending', 'rejected'])
            ->latest()
            ->get();

        $pendingClaim  = $klaim->firstWhere('status', 'pending');
        $rejectedClaim = $klaim->firstWhere('status', 'rejected');

        // $u dikirim dari sini, bukan diambil lewat @php di blade. Blade punya
        // jebakan halus: mencampur bentuk satu-baris @php(...) dengan blok
        // @php…@endphp di satu berkas membuatnya berhenti mengompilasi
        // diam-diam — directive di antaranya tertinggal mentah dan halaman
        // meledak saat dirender, padahal `view:cache` tetap melapor sukses.
        // Menyiapkan variabel di controller menghindari seluruh masalah itu.
        return view('portal.dashboard', [
            'u'             => $user,
            'services'      => $services,
            'stats'         => $stats,
            'recent'        => $recent,
            'pendingClaim'  => $pendingClaim,
            'rejectedClaim' => $rejectedClaim,
        ]);
    }
}
