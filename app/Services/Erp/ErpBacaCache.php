<?php

namespace App\Services\Erp;

use Illuminate\Support\Facades\Cache;

/**
 * Penggabung permintaan baca ke ERP.
 *
 * **Ini bukan salinan data ERP di portal.** Aturan di CLAUDE.md §"Fase 3" tetap
 * berlaku dan alasannya tetap benar: portal tidak boleh menyimpan replika data
 * transaksi, karena saldo tagihan yang basi lebih berbahaya daripada tidak ada
 * saldo sama sekali.
 *
 * Yang dikerjakan kelas ini adalah menggabungkan panggilan yang berdekatan.
 * Halaman layanan menembak ERP setiap kali dibuka, dan satu mitra yang membuka
 * Tagihan, menekan tombol kembali, lalu membukanya lagi menghasilkan tiga
 * panggilan HTTP dalam sepuluh detik untuk jawaban yang sama persis. Kalikan
 * dengan jumlah mitra yang membuka bersamaan, dan ERP-lah yang lebih dulu
 * menyerah — bukan portalnya.
 *
 * Tiga batas yang membuatnya tetap jujur:
 *
 *  1. **Pendek.** Bawaannya 30 detik. Angka yang dilihat mitra tidak pernah
 *     lebih tua dari itu.
 *  2. **Hanya baca.** Tidak ada POST yang pernah lewat sini.
 *  3. **Dibuang setiap kali mitra itu menulis.** Orang yang baru menekan
 *     "Kirim" harus melihat keadaan sesudahnya, bukan jawaban dari sebelum ia
 *     mengirim. Inilah yang membuat cache ini tidak pernah terasa oleh
 *     penggunanya.
 *
 * Kuncinya dipisah per mitra (`customer:12`, `vendor:7`). Tidak pernah ada satu
 * kunci pun yang bisa terbaca dua mitra berbeda — isolasi data tidak boleh
 * bocor lewat pintu belakang bernama cache.
 */
class ErpBacaCache
{
    /**
     * Ambil dari cache, atau panggil ERP lalu simpan.
     *
     * @template T
     *
     * @param  callable(): T  $panggil
     * @param  array<string, mixed>  $query
     * @return T
     */
    public static function ingat(string $mitra, string $path, array $query, callable $panggil): mixed
    {
        $detik = (int) config('portal.erp.read_cache_seconds', 30);

        if ($detik <= 0) {
            return $panggil();
        }

        return Cache::remember(self::kunci($mitra, $path, $query), $detik, $panggil);
    }

    /**
     * Buang seluruh cache baca milik satu mitra.
     *
     * Dipanggil setelah setiap tulis. Karena store bawaan portal adalah
     * `database` — yang tidak punya tag — penghapusannya lewat penanda versi:
     * angkanya dinaikkan, dan seluruh kunci lama otomatis tidak akan pernah
     * cocok lagi. Sisa barisnya dibersihkan sendiri oleh kedaluwarsa 30 detik.
     */
    public static function lupakan(string $mitra): void
    {
        Cache::forever(self::kunciVersi($mitra), self::versi($mitra) + 1);
    }

    // =====================================================================

    /** @param  array<string, mixed>  $query */
    protected static function kunci(string $mitra, string $path, array $query): string
    {
        // portal_user_id ikut berubah saat admin memakai "Lihat Sebagai", tapi
        // jawabannya identik dengan yang dilihat mitranya sendiri — jadi ia
        // sengaja tidak ikut jadi kunci, supaya admin tidak menghangatkan cache
        // kedua untuk data yang sama.
        unset($query['portal_user_id']);

        ksort($query);

        return 'erp:'.$mitra.':v'.self::versi($mitra).':'.md5($path.'?'.http_build_query($query));
    }

    protected static function kunciVersi(string $mitra): string
    {
        return 'erp:'.$mitra.':versi';
    }

    protected static function versi(string $mitra): int
    {
        return (int) Cache::get(self::kunciVersi($mitra), 1);
    }
}
