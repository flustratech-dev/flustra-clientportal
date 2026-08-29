<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PartnerLink;
use App\Services\KonteksMitra;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Lihat Sebagai" — admin memeriksa portal dari sudut pandang mitra tertentu.
 *
 * Gunanya nyata: mitra menelepon bilang tagihannya tidak muncul, dan staf perlu
 * melihat persis apa yang dia lihat tanpa meminta sandinya. Tanpa ini, satu
 * satunya cara memeriksa adalah menebak dari log.
 *
 * Batasnya dijaga di tempat lain dan sengaja tidak bisa dimatikan dari sini:
 * aksi tulis ditolak `TolakTulisSaatLihatSebagai`, perpindahannya dicatat ke
 * `activity_logs`, dan bilah penanda menyala di setiap halaman selama konteks
 * itu aktif.
 */
class LihatSebagaiController extends Controller
{
    public function index(Request $request): View
    {
        $query = PartnerLink::with('user:id,name,email')
            ->where('status', 'verified')
            ->whereNotNull('erp_partner_id');

        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where(fn ($sub) => $sub->where('company_name', 'like', "%{$q}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")));
        }

        if ($request->filled('tipe')) {
            $query->where('partner_type', $request->string('tipe'));
        }

        return view('admin.lihat-sebagai', [
            'links'    => $query->orderBy('company_name')->paginate(20)->withQueryString(),
            'terpilih' => KonteksMitra::pilihanAdmin(),
        ]);
    }

    public function pilih(Request $request, PartnerLink $link): RedirectResponse
    {
        if (! $link->isVerified()) {
            return back()->with('error', 'Mitra itu belum terverifikasi, jadi tidak ada data yang bisa dilihat.');
        }

        KonteksMitra::pilih($link->id);

        // Melihat data mitra tanpa jejak adalah hal yang tidak boleh bisa
        // dilakukan siapa pun, termasuk admin.
        ActivityLog::log(
            'admin_lihat_sebagai',
            'Mulai melihat portal sebagai '.$link->partner_type_label.' "'.$link->company_name
                .'" (akun '.($link->user->email ?? '—').').'
        );

        return redirect()->route('beranda')->with(
            'success',
            'Anda sekarang melihat portal sebagai '.$link->company_name.'. Aksi kirim dinonaktifkan selama ini.'
        );
    }

    public function pilihInline(Request $request): RedirectResponse
    {
        $linkId = $request->input('partner_link_id');

        if (! $linkId) {
            KonteksMitra::pilih(null);

            return back()->with('success', 'Konteks mitra dinonaktifkan.');
        }

        $link = PartnerLink::with('user')->find($linkId);

        if (! $link || ! $link->isVerified()) {
            return back()->with('error', 'Mitra itu belum terverifikasi, jadi tidak ada data yang bisa dilihat.');
        }

        KonteksMitra::pilih($link->id);

        ActivityLog::log(
            'admin_lihat_sebagai',
            'Mulai melihat portal sebagai '.$link->partner_type_label.' "'.$link->company_name
                .'" (akun '.($link->user->email ?? '—').').'
        );

        return back()->with(
            'success',
            'Anda sekarang melihat portal sebagai '.$link->company_name.'. Aksi kirim dinonaktifkan.'
        );
    }

    public function selesai(Request $request): RedirectResponse
    {
        $link = KonteksMitra::pilihanAdmin();

        KonteksMitra::pilih(null);

        if ($link) {
            ActivityLog::log(
                'admin_lihat_sebagai_selesai',
                'Berhenti melihat portal sebagai "'.$link->company_name.'".'
            );
        }

        if ($request->headers->get('referer')) {
            return back()->with('success', 'Kembali ke tampilan standar admin.');
        }

        return redirect()->route('admin.dashboard')->with('success', 'Kembali ke tampilan admin.');
    }
}
