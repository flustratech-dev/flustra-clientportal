<?php

namespace App\Services;

use App\Models\User;

/**
 * Katalog kartu layanan di Beranda.
 *
 * Ditaruh di satu tempat, bukan disebar di blade, karena daftar yang sama
 * dipakai tiga kali: grid Beranda, penjaga rute layanan, dan filter di halaman
 * Riwayat. Menyalinnya ke tiga tempat berarti tiga kesempatan untuk lupa
 * menyinkronkan.
 *
 * `route` yang bernilai null berarti layanannya belum dibangun (Fase 3–5).
 * Kartunya tetap tampil sebagai "segera hadir" supaya mitra tahu apa yang
 * sedang disiapkan, bukan disembunyikan diam-diam.
 */
class ServiceCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forUser(User $user): array
    {
        return array_map(
            fn (array $item) => $item + ['locked' => ! self::isUnlocked($item, $user)],
            self::all()
        );
    }

    /** Kartu yang boleh dipakai pengguna ini. */
    public static function unlockedFor(User $user): array
    {
        return array_values(array_filter(self::forUser($user), fn ($i) => ! $i['locked']));
    }

    public static function isUnlocked(array $item, User $user): bool
    {
        return in_array($user->account_type, $item['for'], true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            // ── Untuk semua, termasuk akun 'umum' ───────────────────────
            [
                'key'   => 'partner_claim',
                'group' => 'Kerja Sama',
                'title' => 'Ajukan Kerja Sama',
                'desc'  => 'Daftarkan perusahaan Anda sebagai pelanggan atau vendor.',
                'icon'  => 'handshake',
                'route' => 'mitra.create',
                'for'   => ['umum', 'pelanggan', 'vendor'],
            ],
            [
                'key'   => 'rfq',
                'group' => 'Kerja Sama',
                'title' => 'Minta Penawaran',
                'desc'  => 'Ceritakan kebutuhan Anda, tim kami menyiapkan penawarannya.',
                'icon'  => 'document',
                'route' => 'umum.rfq.create',
                'for'   => ['umum', 'pelanggan', 'vendor'],
            ],
            [
                'key'   => 'job_application',
                'group' => 'Kerja Sama',
                'title' => 'Lowongan & Lamaran',
                'desc'  => 'Lihat posisi yang sedang dibuka dan kirim lamaran Anda.',
                'icon'  => 'briefcase',
                'route' => 'umum.lowongan.index',
                'for'   => ['umum', 'pelanggan', 'vendor'],
            ],

            // ── Pelanggan ────────────────────────────────────────────────
            [
                'key'   => 'payment_confirmation',
                'group' => 'Pelanggan',
                'title' => 'Konfirmasi Pembayaran',
                'desc'  => 'Unggah bukti transfer agar tagihan Anda segera diverifikasi.',
                'icon'  => 'receipt',
                'route' => 'layanan.pembayaran.create',
                'for'   => ['pelanggan'],
            ],
            [
                'key'   => 'invoices',
                'group' => 'Pelanggan',
                'title' => 'Tagihan Saya',
                'desc'  => 'Daftar invoice, sisa tagihan, dan jatuh temponya.',
                'icon'  => 'wallet',
                'route' => 'layanan.tagihan.index',
                'for'   => ['pelanggan'],
            ],
            [
                'key'   => 'quotations',
                'group' => 'Pelanggan',
                'title' => 'Penawaran',
                'desc'  => 'Tinjau penawaran yang kami kirim, lalu setujui atau tolak.',
                'icon'  => 'document',
                'route' => 'layanan.penawaran.index',
                'for'   => ['pelanggan'],
            ],
            [
                'key'   => 'sales_orders',
                'group' => 'Pelanggan',
                'title' => 'Pesanan Saya',
                'desc'  => 'Status pesanan yang sedang berjalan.',
                'icon'  => 'box',
                'route' => 'layanan.pesanan.index',
                'for'   => ['pelanggan'],
            ],
            [
                'key'   => 'deliveries',
                'group' => 'Pelanggan',
                'title' => 'Lacak Pengiriman',
                'desc'  => 'Kurir, nomor resi, dan posisi pengiriman terakhir.',
                'icon'  => 'truck',
                'route' => 'layanan.pengiriman.index',
                'for'   => ['pelanggan'],
            ],
            [
                'key'   => 'sales_return',
                'group' => 'Pelanggan',
                'title' => 'Ajukan Retur',
                'desc'  => 'Ajukan pengembalian barang beserta alasan dan fotonya.',
                'icon'  => 'return',
                'route' => 'layanan.retur.create',
                'for'   => ['pelanggan'],
            ],
            [
                'key'   => 'contracts',
                'group' => 'Pelanggan',
                'title' => 'Kontrak Kerja Sama',
                'desc'  => 'Lihat, unduh, dan setujui kontrak Anda.',
                'icon'  => 'shield',
                'route' => 'layanan.kontrak.index',
                'for'   => ['pelanggan'],
            ],
            [
                'key'   => 'credit',
                'group' => 'Pelanggan',
                'title' => 'Kredit & Plafon',
                'desc'  => 'Limit kredit Anda, yang sudah terpakai, dan sisanya.',
                'icon'  => 'wallet',
                'route' => 'layanan.kredit.index',
                'for'   => ['pelanggan'],
            ],

            // ── Vendor ───────────────────────────────────────────────────
            [
                'key'   => 'purchase_orders',
                'group' => 'Vendor',
                'title' => 'Purchase Order Masuk',
                'desc'  => 'Konfirmasi kesanggupan dan tanggal kirim Anda.',
                'icon'  => 'clipboard',
                'route' => 'vendor.po.index',
                'for'   => ['vendor'],
            ],
            [
                'key'   => 'vendor_bill',
                'group' => 'Vendor',
                'title' => 'Kirim Tagihan',
                'desc'  => 'Kirim faktur Anda beserta rincian dan dokumennya.',
                'icon'  => 'receipt',
                'route' => 'vendor.tagihan.create',
                'for'   => ['vendor'],
            ],
            [
                'key'   => 'vendor_payments',
                'group' => 'Vendor',
                'title' => 'Status Pembayaran',
                'desc'  => 'Tagihan mana yang sudah dibayar dan mana yang masih diproses.',
                'icon'  => 'wallet',
                'route' => 'vendor.pembayaran.index',
                'for'   => ['vendor'],
            ],
            [
                'key'   => 'shipping_doc',
                'group' => 'Vendor',
                'title' => 'Dokumen Pengiriman',
                'desc'  => 'Unggah surat jalan sebelum barang tiba di gudang kami.',
                'icon'  => 'truck',
                'route' => 'vendor.surat-jalan.index',
                'for'   => ['vendor'],
            ],
            [
                'key'   => 'vendor_returns',
                'group' => 'Vendor',
                'title' => 'Retur & Selisih',
                'desc'  => 'Retur pembelian dan nota debit atas kiriman Anda.',
                'icon'  => 'return',
                'route' => 'vendor.retur.index',
                'for'   => ['vendor'],
            ],
            [
                'key'   => 'vendor_contracts',
                'group' => 'Vendor',
                'title' => 'Kontrak & Dokumen',
                'desc'  => 'Kontrak kerja sama dan dokumen legalitas Anda.',
                'icon'  => 'shield',
                'route' => 'vendor.kontrak.index',
                'for'   => ['vendor'],
            ],

            // ── Data mitra ───────────────────────────────────────────────
            // Dua kartu terpisah, bukan satu yang bercabang: versi vendor
            // memuat data rekening dan karena itu punya peringatan, aturan
            // alasan wajib, dan throttle sendiri.
            [
                'key'   => 'profile_change',
                'group' => 'Data Perusahaan',
                'title' => 'Perbarui Data Perusahaan',
                'desc'  => 'Ajukan perubahan alamat, NPWP, atau kontak perusahaan Anda.',
                'icon'  => 'building',
                'route' => 'layanan.data.edit',
                'for'   => ['pelanggan'],
            ],
            [
                'key'   => 'vendor_profile_change',
                'group' => 'Data Perusahaan',
                'title' => 'Data & Rekening',
                'desc'  => 'Ajukan perubahan data perusahaan atau rekening penerimaan pembayaran.',
                'icon'  => 'building',
                'route' => 'vendor.data.edit',
                'for'   => ['vendor'],
            ],
        ];
    }
}
