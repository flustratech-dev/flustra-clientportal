<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penjaga agar pilihan mode gelap tidak pernah lagi terpisah antar-halaman.
 *
 * Portal punya lima layout dengan tag <html> sendiri-sendiri: app, public,
 * welcome, auth/login, dan errors/layout. Dulu masing-masing menyimpan salinan
 * skrip temanya sendiri, dan gejala yang muncul justru bukan "temanya tidak
 * tersimpan" — pilihannya tersimpan dengan benar, tapi tab yang SUDAH terbuka
 * tidak pernah diberi tahu. Buka beranda di satu tab dan halaman layanan di
 * tab sebelah, ganti tema di salah satunya, dan tab satunya tertinggal di tema
 * lama. Itu alur pemakaian biasa, bukan kasus pinggiran.
 *
 * Uji ini tidak memeriksa tampilan — ia memeriksa bahwa setiap layout benar-
 * benar memuat penyambungnya. Menambah layout keenam tanpa @include('partials.tema')
 * akan menjatuhkan uji ini, bukan diam-diam lolos sampai ada yang mengeluh.
 */
class TemaGelapNyambungTest extends TestCase
{
    use RefreshDatabase;

    /** Penanda yang hanya ada di partials/tema.blade.php. */
    private const PENDENGAR_TAB_LAIN = "addEventListener('storage'";

    private const PENDENGAR_BFCACHE = "addEventListener('pageshow'";

    /** Jembatan ke state Alpine; halaman error sengaja tidak punya ini. */
    private const SAMBUNGAN_ALPINE = 'tema-luar';

    public function test_halaman_publik_ikut_perubahan_dari_tab_lain(): void
    {
        // Satu per layout publik: welcome berdiri sendiri, /masuk memakai
        // auth/login, /bantuan memakai layouts.public.
        foreach (['/', '/masuk', '/bantuan'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString(self::PENDENGAR_TAB_LAIN, $html, $url);
            $this->assertStringContainsString(self::PENDENGAR_BFCACHE, $html, $url);
            $this->assertStringContainsString(self::SAMBUNGAN_ALPINE, $html, $url);
        }
    }

    public function test_dashboard_ikut_perubahan_dari_tab_lain(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get('/beranda')->assertOk()->getContent();

        $this->assertStringContainsString(self::PENDENGAR_TAB_LAIN, $html);
        $this->assertStringContainsString(self::PENDENGAR_BFCACHE, $html);
        $this->assertStringContainsString(self::SAMBUNGAN_ALPINE, $html);
    }

    /**
     * Halaman error berdiri tanpa Alpine — lihat catatan di errors/layout.blade.php.
     * Yang wajib ada di sana cuma pendengarnya; jembatan Alpine tidak relevan.
     */
    public function test_halaman_error_ikut_perubahan_dari_tab_lain(): void
    {
        $html = $this->get('/rute-yang-tidak-pernah-ada')->assertNotFound()->getContent();

        $this->assertStringContainsString(self::PENDENGAR_TAB_LAIN, $html);
        $this->assertStringContainsString(self::PENDENGAR_BFCACHE, $html);
    }

    /**
     * Halaman error dulu memakai @media (prefers-color-scheme), yang membuatnya
     * satu-satunya halaman portal yang mengabaikan pilihan pengguna: sudah
     * memilih terang, tapi 404-nya tetap gelap karena OS-nya gelap.
     */
    public function test_halaman_error_tidak_ikut_setelan_os(): void
    {
        $html = $this->get('/rute-yang-tidak-pernah-ada')->assertNotFound()->getContent();

        $this->assertStringNotContainsString('prefers-color-scheme', $html);
    }
}
