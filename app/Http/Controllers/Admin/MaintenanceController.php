<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\NotifikasiPortalMail;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\PortalSetting;
use App\Models\User;
use App\Services\Maintenance;
use App\Services\WhatsAppGateway;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Pusat Pengelolaan Pemeliharaan Sistem (Maintenance & Notifikasi).
 *
 * 100% Selaras dengan Flustra Office (`flustra-erp`):
 * - Konfigurasi Jadwal (Judul, Jadwal, Durasi, Urgensi, Deskripsi)
 * - Kontrol Notifikasi (Banner, Broadcast Email, Broadcast WA, Lockdown Akses)
 * - Akhiri Pemeliharaan (Turn off banner, kirim notifikasi selesai ke semua saluran)
 */
class MaintenanceController extends Controller
{
    public function edit(): View
    {
        $settings = PortalSetting::pluck('value', 'key')->toArray();

        return view('admin.maintenance', [
            'systemSettings' => $settings,
            'dariErp' => [
                'aktif'   => ($settings[Maintenance::ERP_AKTIF] ?? '0') === '1',
                'judul'   => $settings[Maintenance::ERP_JUDUL] ?? null,
                'pesan'   => $settings[Maintenance::ERP_PESAN] ?? null,
                'tingkat' => $settings[Maintenance::ERP_TINGKAT] ?? 'warning',
                'jadwal'  => $settings[Maintenance::ERP_JADWAL] ?? null,
                'durasi'  => $settings[Maintenance::ERP_DURASI] ?? null,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title'              => ['required', 'string', 'max:150'],
            'scheduled_at'       => ['required', 'string'],
            'estimated_duration' => ['required', 'string', 'max:100'],
            'severity'           => ['required', 'in:info,warning,critical'],
            'description'        => ['required', 'string', 'max:1000'],
        ], [
            'title.required'              => 'Judul maintenance wajib diisi.',
            'scheduled_at.required'       => 'Jadwal pelaksanaan wajib diisi.',
            'estimated_duration.required' => 'Estimasi durasi wajib diisi.',
            'severity.required'           => 'Tingkat urgensi wajib dipilih.',
            'description.required'        => 'Deskripsi pekerjaan wajib diisi.',
        ]);

        PortalSetting::simpanBanyak([
            Maintenance::LOKAL_JUDUL         => $validated['title'],
            Maintenance::LOKAL_JADWAL        => $validated['scheduled_at'],
            Maintenance::LOKAL_DURASI        => $validated['estimated_duration'],
            Maintenance::LOKAL_TINGKAT       => $validated['severity'],
            Maintenance::LOKAL_PESAN         => $validated['description'],
            Maintenance::LOKAL_PESAN_LEGACY  => $validated['description'],
        ]);

        ActivityLog::log('maintenance_settings_updated', 'Pengaturan jadwal maintenance portal diperbarui.');

        return redirect()->route('admin.maintenance')->with('success', 'Konfigurasi maintenance berhasil disimpan!');
    }

    public function toggleBanner(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $isActive = (bool) $validated['is_active'];

        PortalSetting::simpanBanyak([
            Maintenance::LOKAL_AKTIF        => $isActive ? '1' : '0',
            Maintenance::LOKAL_AKTIF_LEGACY => $isActive ? '1' : '0',
        ]);

        if (! $isActive) {
            PortalSetting::simpanBanyak([
                Maintenance::LOKAL_EMAIL_SENT => '0',
                Maintenance::LOKAL_WA_SENT    => '0',
            ]);
        }

        ActivityLog::log(
            'maintenance_banner_toggled',
            'Pop-up banner maintenance portal '.($isActive ? 'diaktifkan.' : 'dinonaktifkan.')
        );

        return response()->json(['success' => true]);
    }

    public function toggleLockdown(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $isActive = (bool) $validated['is_active'];

        PortalSetting::simpan(Maintenance::LOKAL_LOCKDOWN, $isActive ? '1' : '0');

        ActivityLog::log(
            'maintenance_lockdown_toggled',
            'Lockdown akses login portal '.($isActive ? 'diaktifkan.' : 'dinonaktifkan.')
        );

        return response()->json(['success' => true]);
    }

    public function sendEmail(Request $request): JsonResponse
    {
        $settings = PortalSetting::pluck('value', 'key')->toArray();
        $title = $settings[Maintenance::LOKAL_JUDUL] ?? '';
        $description = $settings[Maintenance::LOKAL_PESAN] ?? '';

        if (empty($title) || empty($description)) {
            return response()->json(['error' => 'Judul dan deskripsi maintenance tidak boleh kosong. Harap simpan konfigurasi terlebih dahulu.'], 422);
        }

        $scheduledAt = $settings[Maintenance::LOKAL_JADWAL] ?? null;
        $duration = $settings[Maintenance::LOKAL_DURASI] ?? '';
        $severity = $settings[Maintenance::LOKAL_TINGKAT] ?? 'info';
        $jadwalStr = $scheduledAt ? Carbon::parse($scheduledAt)->timezone('Asia/Jakarta')->translatedFormat('d F Y, H:i').' WIB' : 'Segera';

        $users = User::where('status', 'active')->get();
        $count = 0;

        foreach ($users as $user) {
            try {
                $mailable = new NotifikasiPortalMail(
                    namaPenerima: $user->name,
                    judul: '⚠️ Pemberitahuan Pemeliharaan Sistem: '.$title,
                    isi: "Yth. Mitra Flustra,\n\nKami menginformasikan bahwa sistem Flustra Client Portal akan melakukan pemeliharaan layanan dengan rincian sebagai berikut:\n\n• Jadwal: {$jadwalStr}\n• Estimasi Durasi: {$duration}\n• Keterangan: {$description}\n\nSelama proses pemeliharaan, seluruh data dan pengajuan Anda tetap tersimpan aman.",
                    tipe: $severity === 'critical' ? 'danger' : ($severity === 'warning' ? 'warning' : 'info'),
                    actionUrl: config('app.url').'/dashboard',
                    actionText: 'Buka Portal Klien',
                    nomorReferensi: 'MAINT-'.now()->format('YmdHi'),
                    namaPerusahaan: 'Flustra Client Portal'
                );

                Mail::to($user->email)->queue($mailable);
                $count++;
            } catch (\Throwable $e) {
                Log::error('Gagal kirim broadcast email maintenance ke '.$user->email.': '.$e->getMessage());
            }
        }

        // In-App Notification (Lonceng)
        foreach ($users as $user) {
            Notification::create([
                'user_id' => $user->id,
                'title'   => '⚠️ Pemeliharaan Sistem: '.$title,
                'body'    => 'Jadwal: '.$jadwalStr.' ('.$duration.'). '.$description,
                'type'    => $severity === 'critical' ? 'danger' : ($severity === 'warning' ? 'warning' : 'info'),
                'url'     => '/dashboard',
            ]);
        }

        PortalSetting::simpanBanyak([
            Maintenance::LOKAL_EMAIL_SENT    => '1',
            Maintenance::LOKAL_EMAIL_SENT_AT => now()->toIso8601String(),
        ]);

        ActivityLog::log('maintenance_email_sent', 'Email broadcast maintenance dikirim ke '.$count.' pengguna.');

        return response()->json([
            'success' => true,
            'count'   => $count,
        ]);
    }

    public function sendWA(Request $request): JsonResponse
    {
        $settings = PortalSetting::pluck('value', 'key')->toArray();
        $title = $settings[Maintenance::LOKAL_JUDUL] ?? '';
        $description = $settings[Maintenance::LOKAL_PESAN] ?? '';
        $scheduledAt = $settings[Maintenance::LOKAL_JADWAL] ?? '';
        $duration = $settings[Maintenance::LOKAL_DURASI] ?? '';

        if (empty($title) || empty($description) || empty($scheduledAt)) {
            return response()->json(['error' => 'Judul, jadwal, dan deskripsi maintenance tidak boleh kosong. Harap simpan konfigurasi terlebih dahulu.'], 422);
        }

        $jadwalStr = Carbon::parse($scheduledAt)->timezone('Asia/Jakarta')->translatedFormat('d F Y, H:i').' WIB';
        $users = User::where('status', 'active')->whereNotNull('phone')->where('phone', '!=', '')->get();
        $count = 0;

        foreach ($users as $user) {
            $message = "*⚠️ PEMBERITAHUAN PEMELIHARAAN SISTEM*\n\n"
                ."Yth. {$user->name},\n\n"
                ."Kami menginformasikan bahwa sistem *Flustra Client Portal* akan melakukan pemeliharaan dengan rincian sbb:\n"
                ."• *Judul:* {$title}\n"
                ."• *Jadwal:* {$jadwalStr}\n"
                ."• *Estimasi:* {$duration}\n"
                ."• *Keterangan:* {$description}\n\n"
                ."Data pengajuan Anda tetap tersimpan aman di sistem. Mohon maaf atas ketidaknyamanannya.\n\n"
                ."_Flustra Client Portal_";

            try {
                if (WhatsAppGateway::send($user->phone, $message)) {
                    $count++;
                }
            } catch (\Throwable $e) {
                Log::error('Gagal kirim broadcast WA maintenance ke '.$user->phone.': '.$e->getMessage());
            }
        }

        PortalSetting::simpanBanyak([
            Maintenance::LOKAL_WA_SENT    => '1',
            Maintenance::LOKAL_WA_SENT_AT => now()->toIso8601String(),
        ]);

        ActivityLog::log('maintenance_wa_sent', 'WhatsApp broadcast maintenance dikirim ke '.$count.' pengguna.');

        return response()->json([
            'success' => true,
            'count'   => $count,
        ]);
    }

    public function complete(Request $request): JsonResponse
    {
        $settings = PortalSetting::pluck('value', 'key')->toArray();
        $emailSent = $settings[Maintenance::LOKAL_EMAIL_SENT] ?? '0';
        $waSent = $settings[Maintenance::LOKAL_WA_SENT] ?? '0';
        $title = $settings[Maintenance::LOKAL_JUDUL] ?? 'Pemeliharaan Sistem';
        $description = $settings[Maintenance::LOKAL_PESAN] ?? 'Pemeliharaan sistem portal telah selesai dilakukan.';
        $completedAt = Carbon::now()->timezone('Asia/Jakarta')->translatedFormat('d F Y, H:i').' WIB';

        $users = User::where('status', 'active')->get();
        $emailCount = 0;
        $waCount = 0;

        foreach ($users as $user) {
            // Send Email Completion jika sebelumnya dikirim
            if ($emailSent === '1') {
                try {
                    $mailable = new NotifikasiPortalMail(
                        namaPenerima: $user->name,
                        judul: '✅ Pemeliharaan Sistem Telah Selesai',
                        isi: "Yth. {$user->name},\n\nPemeliharaan sistem Flustra Client Portal ({$title}) telah selesai pada {$completedAt}. Seluruh layanan dan fitur portal kini telah beroperasi normal kembali.\n\nTerima kasih atas kesabaran dan kerja sama Anda.",
                        tipe: 'success',
                        actionUrl: config('app.url').'/dashboard',
                        actionText: 'Masuk ke Portal Klien',
                        nomorReferensi: 'COMPLETED-'.now()->format('YmdHi'),
                        namaPerusahaan: 'Flustra Client Portal'
                    );

                    Mail::to($user->email)->queue($mailable);
                    $emailCount++;
                } catch (\Throwable $e) {
                    Log::error('Gagal kirim email maintenance completed ke '.$user->email.': '.$e->getMessage());
                }
            }

            // Send WA Completion jika sebelumnya dikirim
            if ($waSent === '1' && ! empty($user->phone)) {
                $waMessage = "*✅ PEMELIHARAAN SISTEM SELESAI*\n\n"
                    ."Yth. {$user->name},\n\n"
                    ."Pemeliharaan sistem *Flustra Client Portal* ({$title}) telah selesai pada {$completedAt}. Seluruh layanan dan fitur portal kini telah kembali beroperasi normal.\n\n"
                    ."Terima kasih atas kesabaran dan kerja sama Anda.\n\n"
                    ."_Flustra Client Portal_";

                try {
                    if (WhatsAppGateway::send($user->phone, $waMessage)) {
                        $waCount++;
                    }
                } catch (\Throwable $e) {
                    Log::error('Gagal kirim WA maintenance completed ke '.$user->phone.': '.$e->getMessage());
                }
            }

            // In-App Notification (Bel)
            Notification::create([
                'user_id' => $user->id,
                'title'   => '✅ Pemeliharaan Selesai',
                'body'    => 'Sistem portal telah beroperasi normal kembali.',
                'type'    => 'success',
                'url'     => '/dashboard',
            ]);
        }

        // Reset Settings and Turn off Banner
        PortalSetting::simpanBanyak([
            Maintenance::LOKAL_AKTIF          => '0',
            Maintenance::LOKAL_AKTIF_LEGACY   => '0',
            Maintenance::LOKAL_EMAIL_SENT     => '0',
            Maintenance::LOKAL_WA_SENT        => '0',
            Maintenance::LOKAL_LOCKDOWN       => '0',
            Maintenance::LOKAL_COMPLETED_HASH => md5(uniqid((string) rand(), true)),
        ]);

        ActivityLog::log('maintenance_completed', 'Pemeliharaan portal selesai. Notifikasi selesai dikirim ke pengguna.');

        return response()->json([
            'success'     => true,
            'email_count' => $emailCount,
            'wa_count'    => $waCount,
        ]);
    }
}
