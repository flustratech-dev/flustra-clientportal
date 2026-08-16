<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Portal di belakang reverse proxy.
 *
 * Di produksi, TLS diputus di proxy Coolify dan diteruskan ke container sebagai
 * HTTP biasa. Kalau Laravel tidak diberi tahu, ia percaya dirinya diakses lewat
 * http — dan itu merusak dua hal yang tidak terlihat berhubungan:
 *
 *  1. **Aset.** Seluruh URL dibuat `http://…`, lalu diblokir peramban sebagai
 *     mixed content. Halaman terbuka tapi tanpa satu pun CSS. Ini yang terjadi
 *     pada deploy pertama, 16 Agustus 2026.
 *  2. **Alamat IP.** `$request->ip()` mengembalikan IP proxy, bukan IP
 *     pengunjung. `throttle` jadi menghitung semua orang sebagai satu alamat,
 *     dan `actor_ip` pada persetujuan kontrak — bentuk final pengganti tanda
 *     tangan (CLAUDE.md §3 no.5) — kehilangan artinya.
 *
 * Keduanya diuji di sini karena keduanya diam-diam: tidak ada galat, tidak ada
 * log, hanya halaman polos dan kolom IP yang isinya sama untuk semua orang.
 */
class DiBelakangProxyTest extends TestCase
{
    use RefreshDatabase;

    public function test_proxy_https_membuat_url_aset_ikut_https(): void
    {
        $respons = $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.5'])
            ->get('/', ['X-Forwarded-Proto' => 'https']);

        $respons->assertOk();

        // Yang mematikan bukan ketiadaan https, melainkan adanya http:// pada
        // halaman yang disajikan lewat https — persis itu yang diblokir.
        $respons->assertDontSee('http://localhost/build', escape: false);
    }

    public function test_ip_pengunjung_dibaca_dari_x_forwarded_for(): void
    {
        $ip = null;

        \Illuminate\Support\Facades\Route::middleware('web')->get('/uji-ip', function (\Illuminate\Http\Request $r) use (&$ip) {
            $ip = $r->ip();

            return 'ok';
        });

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.5'])
            ->get('/uji-ip', [
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-For'   => '203.0.113.9',
            ])
            ->assertOk();

        // 10.0.1.5 adalah alamat container proxy di jaringan Docker. Kalau yang
        // muncul itu, seluruh pengguna portal tercatat dengan IP yang sama.
        $this->assertSame('203.0.113.9', $ip);
    }
}
