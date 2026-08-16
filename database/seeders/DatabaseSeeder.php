<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder portal. Mengikuti pola `flustra-erp/database/seeders/DatabaseSeeder`.
 *
 * Isinya hanya satu akun admin. Portal tidak punya data master untuk di-seed —
 * seluruh data transaksinya milik ERP, dan mitranya mendaftar sendiri.
 *
 * Akun yang sudah ada TIDAK ditimpa. Menjalankan ulang seeder saat deploy
 * tidak boleh mengembalikan sandi yang sudah diganti admin ke nilai .env —
 * itu cara paling sunyi untuk membuka kembali pintu yang sudah dikunci.
 * Untuk memaksa memperbarui, pakai `php artisan portal:admin`.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $email    = config('auth.portal_admin.email');
        $password = config('auth.portal_admin.password');

        if (! $email || ! $password) {
            $this->command?->warn('SUPER_ADMIN_EMAIL / SUPER_ADMIN_PASSWORD belum diisi di .env — akun admin dilewati.');

            return;
        }

        if (User::withTrashed()->where('email', $email)->exists()) {
            $this->command?->info('Akun admin sudah ada: '.$email.' (tidak ditimpa).');

            return;
        }

        User::create([
            'name'              => config('auth.portal_admin.name', 'Admin Portal'),
            'email'             => $email,
            'password'          => Hash::make($password),
            'phone'             => config('auth.portal_admin.phone'),
            'role'              => 'admin',
            'account_type'      => 'umum',
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);

        $this->command?->info('Akun admin portal dibuat: '.$email);
    }
}
