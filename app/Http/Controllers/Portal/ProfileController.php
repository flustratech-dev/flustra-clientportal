<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

/**
 * Profil adaptif: tab "Akun" sama untuk semua, tab "Data Mitra" berubah isinya
 * mengikuti tipe akun.
 *
 * Catatan penting: perubahan Data Mitra TIDAK ditulis di sini. Itu menjadi
 * pengajuan yang harus disetujui staf (Fase 3–4) — khususnya untuk rekening
 * bank vendor, karena mengizinkan penggantian rekening tanpa dilihat manusia
 * adalah jalur penipuan pembayaran yang paling umum.
 */
class ProfileController extends Controller
{
    public function edit()
    {
        $user  = Auth::user();
        $links = $user->partnerLinks()->latest()->get();

        return view('portal.profile', compact('user', 'links'));
    }

    public function updateAccount(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'name'   => ['required', 'string', 'max:255'],
            'phone'  => ['nullable', 'string', 'max:30'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar && ! str_starts_with($user->avatar, 'http')) {
                Storage::disk('public')->delete($user->avatar);
            }

            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($data);

        ActivityLog::log('profile_updated', 'Memperbarui data akun.');

        return back()->with('success', 'Data akun berhasil diperbarui.');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required'],
            'password'         => ['required', 'confirmed', Password::min(8)->uncompromised()],
        ], [
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        if (! Hash::check($request->input('current_password'), Auth::user()->password)) {
            return back()->withErrors(['current_password' => 'Kata sandi saat ini salah.']);
        }

        Auth::user()->update(['password' => Hash::make($request->input('password'))]);

        ActivityLog::log('password_changed', 'Mengganti kata sandi.');

        return back()->with('success', 'Kata sandi berhasil diganti.');
    }

    /**
     * Keluarkan sesi di perangkat lain. Berguna kalau mitra pernah login di
     * komputer bersama.
     */
    public function logoutOtherDevices(Request $request)
    {
        $request->validate(['password' => ['required']]);

        if (! Hash::check($request->input('password'), Auth::user()->password)) {
            return back()->withErrors(['password' => 'Kata sandi salah.']);
        }

        Auth::logoutOtherDevices($request->input('password'));

        ActivityLog::log('logout_other_devices', 'Mengeluarkan sesi di perangkat lain.');

        return back()->with('success', 'Sesi di perangkat lain sudah dikeluarkan.');
    }

    /**
     * Ganti peran aktif bagi akun yang terverifikasi sebagai pelanggan sekaligus
     * vendor.
     */
    public function switchRole(Request $request)
    {
        $data = $request->validate([
            'link_id' => ['required', 'integer'],
        ]);

        $user = Auth::user();

        $link = $user->partnerLinks()
            ->where('id', $data['link_id'])
            ->where('status', 'verified')
            ->first();

        abort_unless($link, 404);

        $user->update([
            'active_link_id' => $link->id,
            'account_type'   => $link->account_type,
        ]);

        return back()->with('success', 'Tampilan dialihkan ke '.$link->partner_type_label.'.');
    }
}
