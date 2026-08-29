<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

/**
 * Masuk dengan Google.
 *
 * Dua hal yang diuji di sini, dan keduanya soal batas — bukan soal jalur
 * bahagianya:
 *
 *  1. **Tanpa kredensial, fiturnya mati bersih.** Portal harus tetap bisa
 *     dipasang di lingkungan yang OAuth client-nya belum didaftarkan tanpa
 *     halaman masuknya memamerkan tombol yang sudah pasti gagal.
 *  2. **Google tidak memberi hak apa pun.** Akun yang masuk lewat sini tetap
 *     `account_type = 'umum'`. Membuka data mitra tetap lewat klaim yang
 *     diperiksa staf di ERP — persis seperti pendaftar biasa.
 */
class LoginGoogleTest extends TestCase
{
    use RefreshDatabase;

    protected function nyalakan(): void
    {
        config([
            'services.google.client_id'     => 'uji-client-id.apps.googleusercontent.com',
            'services.google.client_secret' => 'uji-client-secret',
            'services.google.redirect'      => 'http://localhost:8008/masuk/google/callback',
        ]);
    }

    protected function akunGoogle(string $email, string $id = '1122334455', bool $terverifikasi = true): SocialiteUser
    {
        $akun = new SocialiteUser();

        $akun->map([
            'id'    => $id,
            'name'  => 'Pengguna Google',
            'email' => $email,
        ]);

        $akun->user = ['email_verified' => $terverifikasi];

        return $akun;
    }

    protected function pura(SocialiteUser $akun): void
    {
        Socialite::shouldReceive('driver->user')->andReturn($akun);
    }

    // =====================================================================
    // Fitur mati saat kredensial kosong
    // =====================================================================

    public function test_tanpa_kredensial_rutenya_404_bukan_halaman_galat(): void
    {
        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

        $this->get('/masuk/google')->assertNotFound();
        $this->get('/masuk/google/callback')->assertNotFound();
    }

    public function test_tanpa_kredensial_tombolnya_tidak_dirender(): void
    {
        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('Masuk dengan Google');
    }

    public function test_dengan_kredensial_tombolnya_muncul_dan_mengarah_ke_google(): void
    {
        $this->nyalakan();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Masuk dengan Google');

        $this->get('/masuk/google')
            ->assertRedirectContains('accounts.google.com');
    }

    // =====================================================================
    // Callback
    // =====================================================================

    public function test_akun_google_belum_terdaftar_diarahkan_ke_halaman_register_dengan_data_terisi(): void
    {
        $this->nyalakan();
        $this->pura($this->akunGoogle('pendatang@contoh.test', '1122334455'));

        $response = $this->get('/masuk/google/callback');
        $response->assertRedirect(route('register'));
        $response->assertSessionHas('google_email', 'pendatang@contoh.test');
        $response->assertSessionHas('google_name', 'Pengguna Google');
        $response->assertSessionHas('google_id', '1122334455');
        $response->assertSessionHas('info');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'pendatang@contoh.test']);
    }

    public function test_pendaftaran_dengan_google_id_langsung_terverifikasi(): void
    {
        $response = $this->post(route('register'), [
            'name'                  => 'Pengguna Google',
            'email'                 => 'baru@contoh.test',
            'password'              => 'PasswordKuat#2026x',
            'password_confirmation' => 'PasswordKuat#2026x',
            'google_id'             => '1122334455',
            'terms'                 => '1',
        ]);

        $response->assertRedirect(route('beranda'));

        $user = User::where('email', 'baru@contoh.test')->first();
        $this->assertNotNull($user);
        $this->assertSame('1122334455', $user->google_id);
        $this->assertSame('umum', $user->account_type);
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_akun_lama_tersambung_tanpa_kehilangan_kata_sandinya(): void
    {
        $this->nyalakan();

        $lama = User::create([
            'name'         => 'Sudah Terdaftar',
            'email'        => 'lama@contoh.test',
            'password'     => Hash::make('KataSandiLama#2026x'),
            'account_type' => 'pelanggan',
            'status'       => 'active',
        ]);

        $this->pura($this->akunGoogle('lama@contoh.test', '9988776655'));

        $this->get('/masuk/google/callback')->assertRedirect(route('beranda'));

        $lama->refresh();

        $this->assertAuthenticatedAs($lama);
        $this->assertSame('9988776655', $lama->google_id);

        // Dua jalan masuk ke satu akun, bukan penggantian: kata sandi lamanya
        // harus tetap berfungsi.
        $this->assertTrue(Hash::check('KataSandiLama#2026x', $lama->password));

        // Peran yang sudah ada tidak boleh ikut turun jadi 'umum'.
        $this->assertSame('pelanggan', $lama->account_type);
    }

    public function test_email_google_yang_belum_terverifikasi_ditolak(): void
    {
        $this->nyalakan();
        $this->pura($this->akunGoogle('belum@contoh.test', '5544332211', terverifikasi: false));

        $this->get('/masuk/google/callback')->assertRedirect(route('login'));

        // Kalau ini diterima, siapa pun yang bisa membuat akun Google memakai
        // alamat orang lain bisa masuk sebagai orang itu — karena alamat inilah
        // yang dipakai menyambung ke akun portal yang sudah ada.
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'belum@contoh.test']);
    }

    public function test_akun_nonaktif_tetap_ditolak_lewat_google(): void
    {
        $this->nyalakan();

        User::create([
            'name'         => 'Dinonaktifkan',
            'email'        => 'nonaktif@contoh.test',
            'password'     => Hash::make('KataSandiUji#2026x'),
            'account_type' => 'umum',
            'status'       => 'suspended',
        ]);

        $this->pura($this->akunGoogle('nonaktif@contoh.test'));

        $this->get('/masuk/google/callback')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_pendaftaran_tertutup_juga_menutup_jalur_google(): void
    {
        $this->nyalakan();
        config(['portal.registration_open' => false]);

        $this->pura($this->akunGoogle('ditolak@contoh.test'));

        $this->get('/masuk/google/callback')->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'ditolak@contoh.test']);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
