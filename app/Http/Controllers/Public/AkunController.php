<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Verifikasi email dan pemulihan kata sandi.
 *
 * Dua aturan yang membentuk seluruh berkas ini:
 *
 * 1. **Verifikasi email tidak memblokir login.** Portal ini berjanji "daftar
 *    langsung masuk", dan halaman verifikasi yang menghadang akan mengingkari
 *    janji itu. Yang diminta verifikasi hanyalah satu hal: mengajukan klaim
 *    mitra — karena di situlah identitas mulai berarti.
 *
 * 2. **Lupa sandi tidak pernah membocorkan apakah email itu terdaftar.**
 *    Jawabannya selalu sama, terdaftar atau tidak. Membedakannya memberi
 *    penebak daftar email pengguna secara cuma-cuma.
 */
class AkunController extends Controller
{
    // =====================================================================
    // Verifikasi email
    // =====================================================================

    /**
     * Kirim ulang tautan verifikasi.
     *
     * Tautannya bertanda tangan dan berumur 60 menit. Tidak memakai
     * MustVerifyEmail bawaan Laravel karena notifikasi bawaannya berbahasa
     * Inggris dan seluruh antarmuka portal ini berbahasa Indonesia.
     */
    public function kirimVerifikasi(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->email_verified_at) {
            return back()->with('success', 'Email Anda sudah terverifikasi.');
        }

        $tautan = URL::temporarySignedRoute(
            'verifikasi.proses',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        Mail::raw(
            "Halo {$user->name},\n\n"
            ."Klik tautan berikut untuk memverifikasi alamat email Anda di Portal Klien Flustra:\n\n"
            ."{$tautan}\n\n"
            ."Tautan ini berlaku 60 menit. Bila Anda tidak merasa mendaftar, abaikan saja email ini.\n\n"
            .'— Tim Flustra',
            fn ($m) => $m->to($user->email)->subject('Verifikasi email Portal Klien Flustra')
        );

        ActivityLog::log('email_verification_sent', 'Tautan verifikasi email dikirim ke '.$user->email.'.');

        return back()->with('success', 'Tautan verifikasi sudah kami kirim ke '.$user->email.'. Cek juga folder spam.');
    }

    /**
     * Klik dari email.
     *
     * Hash email ikut diperiksa supaya tautan lama tidak bisa dipakai setelah
     * pengguna mengganti alamat emailnya.
     */
    public function verifikasi(Request $request, int $id, string $hash): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403, 'Tautan verifikasi sudah kedaluwarsa.');

        $user = User::findOrFail($id);

        abort_unless(hash_equals(sha1($user->email), $hash), 403);

        if ($user->email_verified_at) {
            return redirect()->route('beranda')->with('success', 'Email Anda sudah terverifikasi sebelumnya.');
        }

        $user->forceFill(['email_verified_at' => now()])->save();

        ActivityLog::log('email_verified', 'Email '.$user->email.' terverifikasi.', $user->id);

        \App\Services\NotifikasiMitra::kirim(
            user: $user,
            judul: 'Verifikasi Email Berhasil',
            isi: 'Alamat email Anda telah berhasil diverifikasi. Anda kini dapat mengajukan verifikasi kemitraan resmi sebagai Pelanggan atau Vendor.',
            tipe: 'success',
            url: route('mitra.create'),
            kirimEmail: false,
        );

        // Sengaja tidak memaksa login: tautan bisa dibuka di perangkat lain.
        return redirect()->route(Auth::check() ? 'beranda' : 'login')
            ->with('success', 'Email Anda berhasil diverifikasi.');
    }

    // =====================================================================
    // Lupa kata sandi
    // =====================================================================

    public function formLupa(): View
    {
        return view('auth.lupa-sandi');
    }

    public function kirimTautanSandi(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        ActivityLog::log('password_reset_requested', 'Permintaan reset sandi untuk '.$request->input('email').'.');

        // Jawaban yang sama apa pun hasilnya — lihat catatan di docblock kelas.
        return back()->with(
            'success',
            'Bila email itu terdaftar, kami sudah mengirimkan tautan pengaturan ulang kata sandi. '
                .'Tautannya berlaku 60 menit.'
        )->with('status_internal', $status);
    }

    public function formSandiBaru(Request $request, string $token): View
    {
        return view('auth.sandi-baru', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function simpanSandiBaru(Request $request): RedirectResponse
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->uncompromised()],
        ], [
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));

                // Pemberitahuan keamanan resmi lintas kanal
                \App\Services\NotifikasiMitra::kirim(
                    user: $user,
                    judul: 'Pemberitahuan Keamanan: Pembaruan Kata Sandi Akun',
                    isi: 'Kata sandi akun Flustra Client Portal Anda telah berhasil diperbarui. Jika Anda tidak merasa melakukan tindakan ini, harap segera hubungi tim helpdesk keamanan Flustra.',
                    tipe: 'warning',
                    url: route('beranda'),
                    waPesan: \App\Services\NotifikasiMitra::pesan(
                        'Pemberitahuan Keamanan: Pembaruan Kata Sandi',
                        'Kata sandi akun Portal Flustra Anda telah diperbarui. Jika Anda tidak merasa melakukan perubahan ini, segera hubungi tim helpdesk Flustra.',
                    ),
                    kirimEmail: true,
                );
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'Tautan pengaturan ulang sudah tidak berlaku. Silakan minta tautan baru.',
            ]);
        }

        ActivityLog::log('password_reset_success', 'Kata sandi berhasil diatur ulang untuk '.$request->input('email').'.');

        return redirect()->route('login')->with('success', 'Kata sandi berhasil diubah. Silakan masuk dengan kata sandi baru Anda.');
    }
}
