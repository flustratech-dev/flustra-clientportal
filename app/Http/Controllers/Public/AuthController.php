<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Autentikasi portal.
 *
 * Perbedaan pokok dari flustra-erp, dan ini disengaja: di sini pendaftar
 * LANGSUNG MASUK. Tidak ada halaman "menunggu persetujuan admin", tidak ada
 * menu yang ditolak secara bawaan.
 *
 * Yang dijaga bukan akunnya, melainkan datanya: akun baru bertipe 'umum' dan
 * hanya bisa memakai layanan yang aman untuk siapa saja. Untuk membuka data
 * mitra, pengguna harus mengajukan klaim yang diperiksa staf di ERP.
 */
class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('beranda');
        }

        return view('auth.login', ['googleAktif' => GoogleAuthController::aktif()]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            ActivityLog::log('login_failed', 'Gagal masuk memakai email: '.$request->input('email'));

            return back()->withErrors([
                'email' => 'Email atau kata sandi tidak cocok dengan data kami.',
            ])->onlyInput('email');
        }

        if (Auth::user()->status !== 'active') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'Akun Anda sedang dinonaktifkan. Silakan hubungi kami untuk bantuan.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        Auth::user()->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        ActivityLog::log('login_success', 'Pengguna '.Auth::user()->name.' masuk ke portal.');

        return redirect()->intended(route('beranda'));
    }

    public function showRegister()
    {
        if (Auth::check()) {
            return redirect()->route('beranda');
        }

        if (! config('portal.registration_open')) {
            return redirect()->route('login')->withErrors([
                'email' => 'Pendaftaran sedang ditutup sementara.',
            ]);
        }

        return view('auth.login', ['googleAktif' => GoogleAuthController::aktif()]);
    }

    public function register(Request $request)
    {
        if (! config('portal.registration_open')) {
            return back()->withErrors(['email' => 'Pendaftaran sedang ditutup sementara.'])->withInput();
        }

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone'    => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(8)->uncompromised()],
            'terms'    => ['accepted'],
        ], [
            'terms.accepted'    => 'Anda harus menyetujui syarat & ketentuan.',
            'email.unique'      => 'Email ini sudah terdaftar. Silakan masuk.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $user = User::create([
            'name'         => $data['name'],
            'email'        => $data['email'],
            'phone'        => $data['phone'] ?? null,
            'password'     => Hash::make($data['password']),
            'account_type' => 'umum',
            'status'       => 'active',
        ]);

        // Langsung masuk. Inilah janji utama portal — tidak ada antrean
        // persetujuan seperti di ERP.
        Auth::login($user);
        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        Notification::send(
            $user->id,
            'Selamat datang di Portal Klien Flustra',
            'Akun Anda sudah aktif. Untuk membuka layanan pelanggan atau vendor, ajukan verifikasi kerja sama lewat kartu di Beranda.',
            'success',
            route('mitra.create')
        );

        ActivityLog::log('register_success', 'Pendaftar baru: '.$user->name.' ('.$user->email.').');

        return redirect()->route('beranda')
            ->with('success', 'Selamat datang, '.$user->name.'! Akun Anda sudah aktif.');
    }

    public function logout(Request $request)
    {
        if (Auth::check()) {
            ActivityLog::log('logout', 'Pengguna '.Auth::user()->name.' keluar dari portal.');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('welcome');
    }
}
