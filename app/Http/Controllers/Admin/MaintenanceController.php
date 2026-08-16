<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PortalSetting;
use App\Services\Maintenance;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Banner pemberitahuan yang dipasang admin portal sendiri.
 *
 * Banner dari ERP tidak bisa diubah dari sini — ia datang lewat webhook dan
 * dimatikan dari ERP juga. Memberi admin portal tombol untuk mematikan
 * pengumuman pemeliharaan ERP akan membuat mitra tidak tahu ERP sedang mati,
 * padahal itu justru saat mereka paling perlu tahu.
 */
class MaintenanceController extends Controller
{
    public function edit(): View
    {
        return view('admin.maintenance', [
            'aktif'   => PortalSetting::ambil(Maintenance::LOKAL_AKTIF) === '1',
            'judul'   => PortalSetting::ambil(Maintenance::LOKAL_JUDUL),
            'pesan'   => PortalSetting::ambil(Maintenance::LOKAL_PESAN),
            'tingkat' => PortalSetting::ambil(Maintenance::LOKAL_TINGKAT) ?: 'info',
            'dariErp' => [
                'aktif'  => PortalSetting::ambil(Maintenance::ERP_AKTIF) === '1',
                'judul'  => PortalSetting::ambil(Maintenance::ERP_JUDUL),
                'pesan'  => PortalSetting::ambil(Maintenance::ERP_PESAN),
                'jadwal' => PortalSetting::ambil(Maintenance::ERP_JADWAL),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'aktif'   => ['nullable', 'boolean'],
            'judul'   => ['nullable', 'required_if:aktif,1', 'string', 'max:120'],
            'pesan'   => ['nullable', 'required_if:aktif,1', 'string', 'max:500'],
            'tingkat' => ['required', 'in:info,warning,critical'],
        ], [
            'judul.required_if' => 'Beri judul pengumumannya.',
            'pesan.required_if' => 'Tulis isi pengumuman yang akan dibaca mitra.',
        ]);

        $aktif = (bool) ($data['aktif'] ?? false);

        PortalSetting::simpanBanyak([
            Maintenance::LOKAL_AKTIF   => $aktif ? '1' : '0',
            Maintenance::LOKAL_JUDUL   => $data['judul'] ?? null,
            Maintenance::LOKAL_PESAN   => $data['pesan'] ?? null,
            Maintenance::LOKAL_TINGKAT => $data['tingkat'],
        ]);

        ActivityLog::log(
            'maintenance_banner_toggled',
            'Banner pemberitahuan portal '.($aktif ? 'dinyalakan: '.$data['judul'] : 'dimatikan').'.'
        );

        return back()->with('success', $aktif
            ? 'Pengumuman dinyalakan dan sekarang tampil di seluruh halaman portal.'
            : 'Pengumuman dimatikan.');
    }
}
