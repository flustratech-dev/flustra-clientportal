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

        // Query submissions otomatis terbatas ke pengguna ini lewat global scope.
        $stats = [
            'diproses' => Submission::whereIn('status', ['submitted', 'received', 'under_review'])->count(),
            'disetujui' => Submission::where('status', 'approved')
                ->where('last_status_at', '>=', now()->startOfMonth())->count(),
            'ditolak'  => Submission::where('status', 'rejected')->count(),
            'total'    => Submission::whereNot('status', 'draft')->count(),
        ];

        $recent = Submission::whereNot('status', 'draft')
            ->latest('submitted_at')
            ->limit(5)
            ->get();

        $pendingClaim = $user->partnerLinks()->where('status', 'pending')->latest()->first();
        $rejectedClaim = $user->partnerLinks()->where('status', 'rejected')->latest()->first();

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
