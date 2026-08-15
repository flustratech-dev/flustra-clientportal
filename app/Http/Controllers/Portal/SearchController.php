<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Services\ServiceCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Pencarian cepat (Ctrl+K).
 *
 * Dua sumber saja: pengajuan milik pengguna sendiri, dan kartu layanan yang
 * terbuka untuknya. Sengaja TIDAK menembak ERP — pencarian harus terasa
 * seketika, dan panggilan HTTP ke sistem lain di setiap ketikan akan membuatnya
 * tersendat sekaligus membanjiri ERP dengan permintaan yang sebagian besar
 * dibatalkan sebelum selesai.
 *
 * Isolasi datanya datang gratis: `Submission` punya global scope yang
 * membatasi setiap query ke pengguna yang sedang masuk, dan katalog layanan
 * disaring terhadap `account_type`-nya.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $kata = trim((string) $request->query('q'));

        if (mb_strlen($kata) < 2) {
            return response()->json(['hasil' => []]);
        }

        $user = Auth::user();

        $pengajuan = Submission::whereNot('status', 'draft')
            ->where(function ($q) use ($kata) {
                $q->where('reference_number', 'like', "%{$kata}%")
                  ->orWhere('title', 'like', "%{$kata}%")
                  ->orWhere('erp_reference', 'like', "%{$kata}%");
            })
            ->latest('submitted_at')
            ->limit(6)
            ->get()
            ->map(fn (Submission $s) => [
                'kelompok' => 'Pengajuan',
                'judul'    => $s->title,
                'ket'      => $s->reference_number.' · '.$s->status_label,
                'url'      => route('riwayat.show', $s),
            ]);

        $layanan = collect(ServiceCatalog::unlockedFor($user))
            ->filter(fn (array $i) => $i['route']
                && (str_contains(mb_strtolower($i['title']), mb_strtolower($kata))
                    || str_contains(mb_strtolower($i['desc']), mb_strtolower($kata))))
            ->take(5)
            ->map(fn (array $i) => [
                'kelompok' => 'Layanan',
                'judul'    => $i['title'],
                'ket'      => $i['desc'],
                'url'      => route($i['route']),
            ]);

        return response()->json([
            'hasil' => $layanan->concat($pengajuan)->values()->all(),
        ]);
    }
}
