<?php

namespace App\Http\Middleware;

use App\Services\KonteksMitra;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penjaga seluruh layanan mitra. Dipakai `mitra:customer` dan `mitra:vendor`.
 *
 * Bukan sekadar memeriksa `account_type`. Yang menentukan boleh-tidaknya adalah
 * adanya `partner_links` berstatus verified dengan `erp_partner_id` terisi:
 * itulah satu-satunya hal yang membuktikan akun ini benar-benar terikat ke
 * mitra tertentu di ERP. `account_type` hanya cerminannya, dan cerminan bisa
 * tertinggal — misalnya setelah akses dicabut tapi baris pengguna belum sempat
 * diperbarui.
 *
 * Satu kelas untuk dua tipe, bukan dua kelas kembar: aturan aksesnya identik,
 * dan dua salinan yang perlahan berbeda akan membuat sisi vendor punya lubang
 * yang sudah lama ditutup di sisi pelanggan.
 *
 * Yang ditolak diarahkan ke Beranda dengan penjelasan, bukan 403 telanjang.
 * Pengguna portal bukan tim teknis; mereka perlu tahu langkah berikutnya.
 */
class EnsureMitra
{
    public function handle(Request $request, Closure $next, string $tipe): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $label = $tipe === 'customer' ? 'pelanggan' : 'vendor';

        /*
         * Admin portal punya akses penuh ke seluruh layanan.
         *
         * Tidak diarahkan ke halaman 'Lihat Sebagai' terlebih dahulu.
         * Admin dapat langsung mengakses halaman dan memilih/berganti mitra
         * secara instan langsung di bilah atas halaman tersebut.
         */
        if ($user->isAdmin()) {
            return $next($request);
        }

        $link = $user->activeLink();

        if ($link && $link->isVerified() && $link->partner_type === $tipe) {
            return $next($request);
        }

        // Akun yang punya peran lain (mis. vendor membuka layanan pelanggan)
        // diberi tahu bahwa jalannya adalah berpindah peran, bukan mendaftar
        // ulang — pesan "ajukan kerja sama" akan membingungkan mereka.
        $punyaPeranLain = $user->partnerLinks()
            ->where('status', 'verified')
            ->where('partner_type', '!=', $tipe)
            ->exists();

        return redirect()->route('beranda')->with('error', match (true) {
            $punyaPeranLain => 'Layanan ini khusus untuk peran '.$label.'. Ganti peran aktif Anda di halaman Profil bila punya keduanya.',
            $user->hasPendingClaim() => 'Pengajuan kerja sama Anda masih diperiksa tim kami. Layanan '.$label.' terbuka setelah verifikasi selesai.',
            default => 'Layanan ini terbuka setelah akun Anda terverifikasi sebagai '.$label.'. Silakan ajukan kerja sama terlebih dahulu.',
        });
    }
}
