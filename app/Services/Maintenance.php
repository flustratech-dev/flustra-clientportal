<?php

namespace App\Services;

use App\Models\PortalSetting;

/**
 * Banner pemberitahuan pemeliharaan.
 *
 * Punya DUA sumber, dan itu disengaja:
 *
 * 1. **Dari ERP.** Ketika tim menyalakan banner maintenance di flustra-erp,
 *    ERP mendorongnya ke portal lewat webhook `maintenance.changed`. Alasannya:
 *    pemeliharaan ERP membuat separuh layanan portal berhenti bekerja — tagihan
 *    tidak bisa ditarik, pengajuan menumpuk di antrean. Mitra perlu tahu itu,
 *    dan yang tahu jadwalnya adalah tim yang mematikan ERP-nya.
 *
 * 2. **Dari portal sendiri.** Admin portal bisa menyalakan bannernya sendiri
 *    untuk hal yang tidak ada hubungannya dengan ERP — migrasi database portal,
 *    perubahan alamat, pengumuman libur.
 *
 * Keduanya disimpan terpisah supaya tidak saling menimpa: ERP mematikan
 * bannernya sendiri tidak boleh ikut mematikan pengumuman yang dipasang admin
 * portal. Kalau dua-duanya menyala, yang ditampilkan yang dari portal — itu
 * pesan yang lebih dekat dengan penggunanya.
 */
class Maintenance
{
    // Dipasang admin portal.
    public const LOKAL_AKTIF   = 'maintenance_active';
    public const LOKAL_JUDUL   = 'maintenance_title';
    public const LOKAL_PESAN   = 'maintenance_message';
    public const LOKAL_TINGKAT = 'maintenance_severity';

    // Didorong dari ERP.
    public const ERP_AKTIF   = 'erp_maintenance_active';
    public const ERP_JUDUL   = 'erp_maintenance_title';
    public const ERP_PESAN   = 'erp_maintenance_message';
    public const ERP_TINGKAT = 'erp_maintenance_severity';
    public const ERP_JADWAL  = 'erp_maintenance_scheduled_at';

    /**
     * Banner yang harus ditampilkan sekarang, atau null bila tidak ada.
     *
     * @return array{sumber: string, judul: string, pesan: string, tingkat: string, jadwal: ?string}|null
     */
    public static function aktif(): ?array
    {
        if (PortalSetting::ambil(self::LOKAL_AKTIF) === '1') {
            return [
                'sumber'  => 'portal',
                'judul'   => PortalSetting::ambil(self::LOKAL_JUDUL) ?: 'Pemberitahuan',
                'pesan'   => PortalSetting::ambil(self::LOKAL_PESAN) ?: '',
                'tingkat' => PortalSetting::ambil(self::LOKAL_TINGKAT) ?: 'info',
                'jadwal'  => null,
            ];
        }

        if (PortalSetting::ambil(self::ERP_AKTIF) === '1') {
            return [
                'sumber'  => 'erp',
                'judul'   => PortalSetting::ambil(self::ERP_JUDUL) ?: 'Pemeliharaan sistem',
                'pesan'   => PortalSetting::ambil(self::ERP_PESAN)
                    ?: 'Sebagian layanan mungkin belum bisa menampilkan data terbaru untuk sementara. '
                        .'Pengajuan yang Anda kirim tetap tersimpan dan akan diproses setelah pemeliharaan selesai.',
                'tingkat' => PortalSetting::ambil(self::ERP_TINGKAT) ?: 'warning',
                'jadwal'  => PortalSetting::ambil(self::ERP_JADWAL),
            ];
        }

        return null;
    }

    /** Kelas utilitas lengkap per tingkat — jangan dirangkai saat runtime. */
    public static function warna(string $tingkat): string
    {
        return match ($tingkat) {
            'critical', 'error' => 'border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/30 text-red-700 dark:text-red-400',
            'warning'           => 'border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/30 text-amber-700 dark:text-amber-400',
            default             => 'border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-950/30 text-blue-700 dark:text-blue-400',
        };
    }

    /**
     * Terapkan kabar pemeliharaan dari ERP.
     *
     * @param  array<string, mixed>  $data
     */
    public static function dariErp(array $data): void
    {
        PortalSetting::simpanBanyak([
            self::ERP_AKTIF   => ! empty($data['active']) ? '1' : '0',
            self::ERP_JUDUL   => $data['title'] ?? null,
            self::ERP_PESAN   => $data['message'] ?? null,
            self::ERP_TINGKAT => $data['severity'] ?? 'warning',
            self::ERP_JADWAL  => $data['scheduled_at'] ?? null,
        ]);
    }
}
