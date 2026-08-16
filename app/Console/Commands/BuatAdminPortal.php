<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Membuat atau memperbarui akun admin portal.
 *
 * Bukan seeder: akun ini dibuat sekali saat deploy dan sandinya tidak boleh
 * ikut ter-commit. Nilainya diambil dari .env, dan bila `PORTAL_ADMIN_PASSWORD`
 * kosong, perintah ini membangkitkan sandi acak lalu menampilkannya sekali —
 * lebih aman daripada sandi bawaan yang semua orang tahu.
 */
class BuatAdminPortal extends Command
{
    protected $signature = 'portal:admin
                            {--email= : Alamat email admin (bawaan: SUPER_ADMIN_EMAIL)}
                            {--name= : Nama admin (bawaan: SUPER_ADMIN_NAME)}
                            {--password= : Kata sandi (bawaan: SUPER_ADMIN_PASSWORD, atau diacak)}';

    protected $description = 'Buat atau perbarui akun admin portal untuk memantau kondisi web.';

    public function handle(): int
    {
        $email = $this->option('email') ?: config('auth.portal_admin.email');
        $nama  = $this->option('name') ?: config('auth.portal_admin.name', 'Admin Portal');

        if (! $email) {
            $this->error('Alamat email admin belum ditentukan. Isi SUPER_ADMIN_EMAIL di .env atau pakai --email.');

            return self::FAILURE;
        }

        $sandi  = $this->option('password') ?: config('auth.portal_admin.password');
        $diacak = false;

        if (! $sandi) {
            $sandi  = Str::password(16);
            $diacak = true;
        }

        $user = User::withTrashed()->firstWhere('email', $email);

        if ($user) {
            $user->restore();
            $user->forceFill([
                'name'              => $nama,
                'password'          => Hash::make($sandi),
                'role'              => 'admin',
                'status'            => 'active',
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            $this->info('Akun admin diperbarui: '.$email);
        } else {
            User::create([
                'name'              => $nama,
                'email'             => $email,
                'password'          => Hash::make($sandi),
                'role'              => 'admin',
                'account_type'      => 'umum',
                'status'            => 'active',
                'email_verified_at' => now(),
            ]);

            $this->info('Akun admin dibuat: '.$email);
        }

        if ($diacak) {
            $this->newLine();
            $this->warn('Kata sandi dibangkitkan acak dan hanya ditampilkan sekali:');
            $this->line('  '.$sandi);
            $this->newLine();
            $this->comment('Simpan sekarang, lalu ganti lewat halaman Profil setelah masuk.');
        }

        $this->line('Masuk lewat halaman /masuk seperti biasa, lalu buka /admin.');

        return self::SUCCESS;
    }
}
