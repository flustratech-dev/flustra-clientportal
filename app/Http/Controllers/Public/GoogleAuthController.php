<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * Masuk dengan akun Google.
 *
 * Tetap **tanpa SSO** (CLAUDE.md §3 nomor 1): ini bukan `flustra-auth`, dan
 * pengguna yang masuk lewat sini tetap jadi baris di tabel `users` portal
 * sendiri, dengan `account_type = 'umum'` seperti pendaftar biasa. Yang
 * dipinjam dari Google hanya pembuktian "email ini benar milik orang yang
 * sedang duduk di depan layar" — bukan identitas mitra, dan bukan hak akses
 * apa pun. Membuka data mitra tetap lewat klaim yang diperiksa staf di ERP.
 *
 * **Kredensial kosong berarti fitur mati, bukan rusak.** Tanpa
 * `GOOGLE_CLIENT_ID`, rute ini membalas 404 dan tombolnya tidak pernah
 * dirender. Portal harus tetap bisa dipasang di lingkungan yang OAuth
 * client-nya belum didaftarkan, tanpa halaman masuknya memamerkan tombol yang
 * sudah pasti gagal.
 */
class GoogleAuthController extends Controller
{
    /** Antar pengguna ke halaman izin Google. */
    public function redirect()
    {
        $this->pastikanAktif();

        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    /** Kembali dari Google. */
    public function callback(Request $request)
    {
        $this->pastikanAktif();

        try {
            $akunGoogle = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            // Pengguna menekan "Batal", state-nya kedaluwarsa, atau jaringan
            // putus di tengah jalan. Tidak satu pun perlu ditampilkan sebagai
            // galat teknis — yang bisa dilakukan pengguna sama saja: coba lagi.
            ActivityLog::log('google_login_failed', 'Proses masuk dengan Google tidak selesai: '.$e->getMessage());

            return redirect()->route('login')->withErrors([
                'email' => 'Proses masuk dengan Google tidak selesai. Silakan coba lagi.',
            ]);
        }

        $email = $akunGoogle->getEmail();

        if (! $email) {
            return redirect()->route('login')->withErrors([
                'email' => 'Akun Google Anda tidak membagikan alamat email, jadi kami tidak bisa mencocokkannya.',
            ]);
        }

        // Google boleh saja menyerahkan alamat yang belum ia verifikasi sendiri.
        // Menerimanya berarti siapa pun yang bisa membuat akun Google dengan
        // alamat orang lain bisa masuk sebagai orang itu — dan di bawah, alamat
        // inilah yang dipakai untuk menyambung ke akun portal yang sudah ada.
        if (isset($akunGoogle->user['email_verified']) && ! $akunGoogle->user['email_verified']) {
            return redirect()->route('login')->withErrors([
                'email' => 'Alamat email pada akun Google Anda belum terverifikasi oleh Google.',
            ]);
        }

        $user = User::where('google_id', $akunGoogle->getId())->first()
            ?? User::where('email', $email)->first();

        $baru = false;

        if (! $user) {
            if (! config('portal.registration_open')) {
                return redirect()->route('login')->withErrors([
                    'email' => 'Pendaftaran sedang ditutup sementara.',
                ]);
            }

            $user  = $this->buatAkun($akunGoogle, $email);
            $baru  = true;
        }

        if ($user->status !== 'active') {
            return redirect()->route('login')->withErrors([
                'email' => 'Akun Anda sedang dinonaktifkan. Silakan hubungi kami untuk bantuan.',
            ]);
        }

        $this->sambungkan($user, $akunGoogle);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        if ($baru) {
            Notification::send(
                $user->id,
                'Selamat datang di Portal Klien Flustra',
                'Akun Anda sudah aktif. Untuk membuka layanan pelanggan atau vendor, ajukan verifikasi kerja sama lewat kartu di Beranda.',
                'success',
                route('mitra.create')
            );

            ActivityLog::log('register_success', 'Pendaftar baru lewat Google: '.$user->name.' ('.$user->email.').');

            return redirect()->route('beranda')
                ->with('success', 'Selamat datang, '.$user->name.'! Akun Anda sudah aktif.');
        }

        ActivityLog::log('login_success', 'Pengguna '.$user->name.' masuk ke portal lewat Google.');

        return redirect()->intended(route('beranda'));
    }

    // =====================================================================

    /**
     * Akun baru dari Google.
     *
     * Kata sandinya acak dan tidak pernah diberitahukan kepada siapa pun: kolom
     * `password` tidak boleh null, tapi pemilik akun ini masuk lewat Google.
     * Kalau suatu saat ia ingin punya kata sandi sendiri, jalurnya lewat
     * "Lupa Sandi" — yang mengirim tautan ke alamat email yang sama.
     */
    protected function buatAkun(object $akunGoogle, string $email): User
    {
        return User::create([
            'name'              => $akunGoogle->getName() ?: Str::before($email, '@'),
            'email'             => $email,
            'password'          => Hash::make(Str::random(48)),
            'google_id'         => $akunGoogle->getId(),
            'account_type'      => 'umum',
            'status'            => 'active',

            // Google sudah membuktikan kepemilikan alamatnya. Memaksa pengguna
            // memverifikasi ulang lewat surel hanya mengulang pekerjaan yang
            // baru saja selesai.
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Tautkan akun portal yang sudah ada ke akun Google ini.
     *
     * Terjadi saat seseorang mendaftar dengan kata sandi lebih dulu, lalu
     * belakangan menekan "Masuk dengan Google" memakai alamat yang sama.
     * Kata sandi lamanya sengaja TIDAK dihapus — dua jalan masuk ke satu akun
     * yang sama, bukan penggantian.
     */
    protected function sambungkan(User $user, object $akunGoogle): void
    {
        $ubah = [];

        if (! $user->google_id) {
            $ubah['google_id'] = $akunGoogle->getId();
        }

        if (! $user->email_verified_at) {
            $ubah['email_verified_at'] = now();
        }

        if ($ubah) {
            $user->forceFill($ubah)->save();
        }
    }

    /**
     * 404, bukan 503 atau halaman galat: kalau OAuth client-nya belum ada,
     * alamat ini memang bukan bagian dari portal ini.
     */
    protected function pastikanAktif(): void
    {
        abort_unless(self::aktif(), 404);
    }

    /** Dipakai juga oleh view untuk memutuskan menampilkan tombolnya atau tidak. */
    public static function aktif(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }
}
