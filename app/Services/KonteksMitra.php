<?php

namespace App\Services;

use App\Models\PartnerLink;
use App\Models\User;
use Illuminate\Support\Facades\Session;

/**
 * Menentukan "sedang bertindak sebagai mitra siapa" untuk permintaan ini.
 *
 * Untuk mitra biasa jawabannya selalu sama: peran aktifnya sendiri. Tidak ada
 * jalan lain, dan itu inti pagar isolasi data portal.
 *
 * Untuk **admin portal** ada tambahan: ia bisa memilih melihat sebagai mitra
 * mana pun yang sudah terverifikasi, supaya bisa memeriksa keluhan tanpa
 * meminta sandi mitranya. Tiga batas yang menyertainya, dan ketiganya
 * disengaja:
 *
 * 1. **Hanya baca.** Selama melihat sebagai orang lain, admin tidak bisa
 *    mengirim pengajuan apa pun — lihat `TolakTulisSaatLihatSebagai`. Pengajuan
 *    atas nama orang lain akan mencemari riwayat mitra dan membuat jejak audit
 *    berbohong tentang siapa yang menekan tombolnya.
 *
 * 2. **Tercatat.** Setiap kali admin berpindah konteks, `activity_logs`
 *    mencatatnya. Melihat data mitra tanpa jejak adalah hal yang tidak boleh
 *    bisa dilakukan siapa pun.
 *
 * 3. **Terlihat.** Selama konteksnya aktif, ada bilah menyala di setiap
 *    halaman. Admin tidak boleh lupa sedang melihat data siapa.
 *
 * Catatan keamanan yang harus dipahami sebelum mengubah kelas ini: agar ERP
 * mau melayani permintaannya, portal mengirim `portal_user_id` **milik pemilik
 * link**, bukan milik admin. Artinya lapisan kedua di ERP
 * (`PortalPartnerResolver`) ditembus untuk admin. Itu pertukaran yang disadari
 * — admin portal adalah staf tepercaya, dan tanpa ini "akses penuh" hanya
 * berarti halaman terbuka yang isinya kosong. Yang menjaga agar ini tidak
 * disalahgunakan adalah ketiga batas di atas, bukan lapisan ERP-nya.
 */
class KonteksMitra
{
    public const KUNCI_SESI = 'admin_lihat_sebagai';

    /**
     * Link yang sedang dipakai untuk memanggil ERP.
     *
     * @param  string|null  $tipe  Batasi ke 'customer' atau 'vendor'.
     */
    public static function link(User $user, ?string $tipe = null): ?PartnerLink
    {
        if ($user->isAdmin()) {
            $link = self::pilihanAdmin();

            return ($link && ($tipe === null || $link->partner_type === $tipe)) ? $link : null;
        }

        $link = $user->activeLink();

        if (! $link || ! $link->isVerified()) {
            return null;
        }

        return ($tipe === null || $link->partner_type === $tipe) ? $link : null;
    }

    /**
     * Pemilik link — inilah yang dikenali ERP, bukan admin yang sedang melihat.
     */
    public static function pemilik(User $user, PartnerLink $link): User
    {
        return $link->user_id === $user->id ? $user : ($link->user ?? $user);
    }

    /** Sedang melihat sebagai orang lain? */
    public static function sedangLihatSebagai(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user?->isAdmin() && self::pilihanAdmin() !== null;
    }

    public static function pilihanAdmin(): ?PartnerLink
    {
        $id = Session::get(self::KUNCI_SESI);

        if (! $id) {
            return null;
        }

        $link = PartnerLink::with('user')->find($id);

        // Link yang aksesnya sudah dicabut tidak boleh tetap bisa dilihat hanya
        // karena masih tertinggal di sesi admin.
        return ($link && $link->isVerified()) ? $link : null;
    }

    public static function pilih(?int $linkId): void
    {
        $linkId ? Session::put(self::KUNCI_SESI, $linkId) : Session::forget(self::KUNCI_SESI);
    }
}
