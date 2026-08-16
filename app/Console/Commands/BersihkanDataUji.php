<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Kosongkan portal dari data uji coba, sisakan akun admin.
 *
 * Dibuat untuk membersihkan sisa pengujian sebelum portal dipakai sungguhan.
 * Ditulis sebagai perintah, bukan SQL sekali pakai, karena tiga alasan: bisa
 * ditinjau sebelum dijalankan, aman diulang, dan ikut menghapus BERKAS di disk
 * privat — bagian yang paling gampang tertinggal kalau pembersihannya lewat SQL
 * langsung, dan yang paling tidak enak kalau tertinggal (di situ ada bukti
 * transfer dan CV pelamar).
 *
 * Yang DIPERTAHANKAN:
 *   - akun ber-`role = 'admin'` (bisa lebih dari satu);
 *   - `portal_settings` — itu setelan banner pemeliharaan, bukan data uji;
 *   - `migrations`.
 *
 * Yang dihapus: seluruh akun mitra beserta klaim, pengajuan, lampiran, timeline,
 * notifikasi, dan log — plus antrean, sesi, dan cache yang menyertainya.
 *
 * Foreign key sudah menangani sebagian besar rantainya sendiri
 * (`partner_links`, `submissions`, `submission_attachments`,
 * `submission_status_histories`, dan `notifications` semuanya CASCADE dari
 * `users`), tapi berkas fisiknya tidak ikut — itu sebabnya path dikumpulkan
 * lebih dulu, sebelum barisnya hilang.
 */
class BersihkanDataUji extends Command
{
    protected $signature = 'portal:bersihkan-uji
                            {--force : Jalankan tanpa bertanya (untuk skrip)}';

    protected $description = 'Hapus seluruh data uji coba portal, sisakan akun admin.';

    public function handle(): int
    {
        $admin = User::where('role', 'admin')->pluck('email', 'id');

        if ($admin->isEmpty()) {
            $this->error('Tidak ada akun ber-role admin. Dibatalkan — menjalankan ini akan mengosongkan tabel users.');
            $this->line('Buat dulu adminnya: php artisan portal:admin');

            return self::FAILURE;
        }

        $ringkasan = $this->hitung();

        $this->newLine();
        $this->line('Akan DIPERTAHANKAN: '.$admin->implode(', '));
        $this->line('Akan DIHAPUS:');
        foreach ($ringkasan as $tabel => $jumlah) {
            if ($jumlah > 0) {
                $this->line('  '.str_pad($tabel, 32).$jumlah);
            }
        }
        $this->newLine();

        if (! $this->option('force') && ! $this->confirm('Lanjutkan? Penghapusan ini tidak bisa dibatalkan.')) {
            $this->line('Dibatalkan.');

            return self::SUCCESS;
        }

        $berkas = $this->hapusBerkas();

        DB::transaction(function () use ($admin) {
            // Dilepas lebih dulu: users.active_link_id menunjuk partner_links
            // tanpa foreign key, jadi tidak ada yang membersihkannya otomatis
            // dan admin bisa tertinggal menunjuk baris yang sudah hilang.
            User::whereIn('id', $admin->keys())->update(['active_link_id' => null, 'account_type' => 'umum']);

            // CASCADE mengurus partner_links, submissions, lampiran, timeline,
            // dan notifikasi milik akun-akun ini.
            User::withTrashed()->whereNotIn('id', $admin->keys())->forceDelete();

            // Sisa yang tidak ikut CASCADE: milik admin sendiri, atau memang
            // tidak terikat pengguna mana pun.
            foreach (['submissions', 'notifications', 'activity_logs', 'api_sync_logs',
                      'sessions', 'jobs', 'job_batches', 'failed_jobs', 'cache', 'cache_locks'] as $tabel) {
                DB::table($tabel)->delete();
            }
        });

        $this->newLine();
        $this->info('Selesai. '.$berkas.' berkas unggahan ikut dihapus dari disk privat.');
        $this->line('Tersisa: '.User::count().' pengguna ('.$admin->implode(', ').').');

        return self::SUCCESS;
    }

    /** @return array<string, int> */
    protected function hitung(): array
    {
        $adminIds = User::where('role', 'admin')->pluck('id');

        return [
            'users (selain admin)'        => User::withTrashed()->whereNotIn('id', $adminIds)->count(),
            'partner_links'               => DB::table('partner_links')->count(),
            'submissions'                 => DB::table('submissions')->count(),
            'submission_attachments'      => DB::table('submission_attachments')->count(),
            'submission_status_histories' => DB::table('submission_status_histories')->count(),
            'notifications'               => DB::table('notifications')->count(),
            'activity_logs'               => DB::table('activity_logs')->count(),
            'api_sync_logs'               => DB::table('api_sync_logs')->count(),
            'sessions'                    => DB::table('sessions')->count(),
            'failed_jobs'                 => DB::table('failed_jobs')->count(),
            'cache'                       => DB::table('cache')->count(),
        ];
    }

    /**
     * Hapus berkas unggahan dari disk privat.
     *
     * Dijalankan SEBELUM barisnya dihapus — setelah itu path-nya tidak bisa
     * ditemukan lagi, dan berkasnya akan menghuni disk selamanya tanpa ada yang
     * tahu ia milik siapa.
     */
    protected function hapusBerkas(): int
    {
        $n = 0;

        foreach (DB::table('submission_attachments')->select('disk', 'path')->get() as $a) {
            if ($a->path && Storage::disk($a->disk ?: 'private')->delete($a->path)) {
                $n++;
            }
        }

        foreach (DB::table('partner_links')->whereNotNull('evidence_file_path')->pluck('evidence_file_path') as $path) {
            if (Storage::disk('private')->delete($path)) {
                $n++;
            }
        }

        return $n;
    }
}
