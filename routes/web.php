<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\LihatSebagaiController;
use App\Http\Controllers\Admin\MaintenanceController as AdminMaintenanceController;
use App\Http\Controllers\Layanan\DataPerusahaanController;
use App\Http\Controllers\Layanan\KonfirmasiBayarController;
use App\Http\Controllers\Layanan\KontrakController;
use App\Http\Controllers\Layanan\KreditController;
use App\Http\Controllers\Layanan\PenawaranController;
use App\Http\Controllers\Layanan\PengirimanController;
use App\Http\Controllers\Layanan\PesananController;
use App\Http\Controllers\Layanan\ReturController;
use App\Http\Controllers\Layanan\TagihanController;
use App\Http\Controllers\Layanan\Umum\LowonganController;
use App\Http\Controllers\Layanan\Umum\PertanyaanController;
use App\Http\Controllers\Layanan\Umum\RfqController;
use App\Http\Controllers\Layanan\Vendor\DataVendorController;
use App\Http\Controllers\Layanan\Vendor\KontrakVendorController;
use App\Http\Controllers\Layanan\Vendor\PembayaranVendorController;
use App\Http\Controllers\Layanan\Vendor\PurchaseOrderController;
use App\Http\Controllers\Layanan\Vendor\ReturVendorController;
use App\Http\Controllers\Layanan\Vendor\SuratJalanController;
use App\Http\Controllers\Layanan\Vendor\TagihanVendorController;
use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\EvidenceController;
use App\Http\Controllers\Portal\HistoryController;
use App\Http\Controllers\Portal\NotificationController;
use App\Http\Controllers\Portal\PartnerClaimController;
use App\Http\Controllers\Portal\ProfileController;
use App\Http\Controllers\Portal\SearchController;
use App\Http\Controllers\Public\AkunController;
use App\Http\Controllers\Public\AuthController;
use App\Http\Controllers\Public\GoogleAuthController;
use App\Http\Controllers\Public\WelcomeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rute Portal Klien
|--------------------------------------------------------------------------
|
| Semua URL berbahasa Indonesia karena penggunanya pihak luar, bukan tim
| teknis. Rute bernama tetap ringkas supaya enak dipanggil dari blade.
|
*/

// --- PUBLIK ---------------------------------------------------------------
Route::get('/', [WelcomeController::class, 'index'])->name('welcome');
Route::get('/bantuan', [WelcomeController::class, 'help'])->name('bantuan');
Route::get('/syarat', [WelcomeController::class, 'terms'])->name('syarat');
Route::get('/privasi', [WelcomeController::class, 'privacy'])->name('privasi');

/*
 * Bukti klaim untuk staf ERP.
 *
 * Di luar middleware 'auth' dengan sengaja: yang membukanya adalah staf di
 * flustra-erp, yang tidak punya akun portal. Penjaganya tanda tangan pada URL
 * (dibuat saat klaim dikirim, berumur pendek), bukan sesi.
 */
Route::get('/berkas/bukti-klaim/{link}', [EvidenceController::class, 'claim'])
    ->middleware('signed')
    ->name('berkas.bukti-klaim');

Route::get('/berkas/lampiran/{attachment}', [EvidenceController::class, 'attachment'])
    ->middleware('signed')
    ->name('berkas.lampiran');

// --- TAMU -----------------------------------------------------------------
Route::middleware('guest')->group(function () {
    Route::get('/masuk', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/masuk', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::get('/daftar', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/daftar', [AuthController::class, 'register'])->middleware('throttle:3,10');

    /*
     * Masuk dengan Google.
     *
     * Rutenya selalu terdaftar, tapi controllernya membalas 404 selama
     * GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET masih kosong — jadi tidak perlu
     * ada percabangan di berkas rute ini, dan `route('google.redirect')` tetap
     * aman dipanggil view mana pun.
     */
    Route::get('/masuk/google', [GoogleAuthController::class, 'redirect'])
        ->middleware('throttle:10,1')->name('google.redirect');
    Route::get('/masuk/google/callback', [GoogleAuthController::class, 'callback'])
        ->middleware('throttle:10,1')->name('google.callback');
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);

    /*
     * Pemulihan kata sandi.
     *
     * Nama rutenya mengikuti konvensi Laravel (password.request / password.email
     * / password.reset / password.update) supaya notifikasi bawaan dan
     * `Password::reset()` menemukannya tanpa penyesuaian.
     */
    Route::get('/lupa-sandi', [AkunController::class, 'formLupa'])->name('password.request');
    Route::post('/lupa-sandi', [AkunController::class, 'kirimTautanSandi'])
        ->middleware('throttle:3,10')->name('password.email');

    Route::get('/sandi-baru/{token}', [AkunController::class, 'formSandiBaru'])->name('password.reset');
    Route::post('/sandi-baru', [AkunController::class, 'simpanSandiBaru'])
        ->middleware('throttle:6,10')->name('password.update');
});

/*
 * Verifikasi email lewat tautan bertanda tangan.
 *
 * Di luar grup 'guest' dan 'auth' dengan sengaja: tautannya sering dibuka di
 * perangkat lain — ponsel, sementara pendaftarannya di laptop — dan memaksa
 * masuk dulu hanya akan membuat verifikasinya gagal tanpa alasan yang jelas.
 * Penjaganya tanda tangan pada URL plus hash alamat emailnya.
 */
Route::get('/verifikasi-email/{id}/{hash}', [AkunController::class, 'verifikasi'])
    ->middleware('signed')
    ->whereNumber('id')
    ->name('verifikasi.proses');

// --- SUDAH MASUK ----------------------------------------------------------
Route::middleware('auth')->group(function () {
    Route::post('/keluar', [AuthController::class, 'logout'])->name('logout');

    Route::get('/beranda', [DashboardController::class, 'index'])->name('beranda');

    // Klaim mitra: pintu dari 'umum' ke 'pelanggan'/'vendor'.
    Route::get('/kerja-sama', [PartnerClaimController::class, 'create'])->name('mitra.create');
    Route::post('/kerja-sama', [PartnerClaimController::class, 'store'])
        ->middleware('throttle:5,60')
        ->name('mitra.store');

    Route::post('/verifikasi-email/kirim', [AkunController::class, 'kirimVerifikasi'])
        ->middleware('throttle:3,10')->name('verifikasi.kirim');

    /*
     * ── Layanan umum (Fase 5) ───────────────────────────────────────────
     *
     * Terbuka untuk semua akun portal, termasuk yang belum terverifikasi
     * sebagai mitra. Tidak ada data mitra yang bisa disentuh dari sini.
     */
    Route::prefix('umum')->name('umum.')->group(function () {
        Route::get('/lowongan', [LowonganController::class, 'index'])->name('lowongan.index');
        Route::post('/lowongan/{vacancy}/lamar', [LowonganController::class, 'apply'])
            ->whereNumber('vacancy')->middleware('throttle:5,60')->name('lowongan.apply');

        Route::get('/minta-penawaran', [RfqController::class, 'create'])->name('rfq.create');
        Route::post('/minta-penawaran', [RfqController::class, 'store'])
            ->middleware('throttle:5,60')->name('rfq.store');

        Route::post('/pertanyaan', [PertanyaanController::class, 'store'])
            ->middleware('throttle:10,60')->name('pertanyaan.store');
    });

    // Pencarian cepat (Ctrl+K). JSON, dipanggil dari layout.
    Route::get('/cari', SearchController::class)->name('cari');

    // Riwayat & status seluruh pengajuan.
    Route::get('/riwayat', [HistoryController::class, 'index'])->name('riwayat.index');
    Route::get('/riwayat/{submission}', [HistoryController::class, 'show'])->name('riwayat.show');

    // Notifikasi.
    Route::get('/notifikasi', [NotificationController::class, 'index'])->name('notifikasi.index');
    Route::get('/notifikasi/poll', [NotificationController::class, 'poll'])->name('notifikasi.poll');
    Route::post('/notifikasi/baca-semua', [NotificationController::class, 'readAll'])->name('notifikasi.read-all');
    Route::post('/notifikasi/{notification}/baca', [NotificationController::class, 'read'])->name('notifikasi.read');

    /*
     * ── Layanan pelanggan (Fase 3) ──────────────────────────────────────
     *
     * Semuanya di balik middleware 'mitra:customer', yang memeriksa adanya
     * partner_links terverifikasi — bukan sekadar account_type. Tidak ada satu
     * pun rute di sini yang menerima id mitra; id-nya selalu digali dari
     * activeLink milik pengguna di ErpCustomerApi.
     */
    Route::middleware('mitra:customer')->prefix('layanan')->name('layanan.')->group(function () {

        Route::get('/tagihan', [TagihanController::class, 'index'])->name('tagihan.index');
        Route::get('/tagihan/{invoice}', [TagihanController::class, 'show'])
            ->whereNumber('invoice')->name('tagihan.show');
        Route::get('/tagihan/{invoice}/pdf', [TagihanController::class, 'pdf'])
            ->whereNumber('invoice')->name('tagihan.pdf');

        Route::get('/pembayaran', [KonfirmasiBayarController::class, 'create'])->name('pembayaran.create');
        Route::post('/pembayaran', [KonfirmasiBayarController::class, 'store'])
            ->middleware('throttle:20,1')->name('pembayaran.store');

        Route::get('/penawaran', [PenawaranController::class, 'index'])->name('penawaran.index');
        Route::post('/penawaran/{quotation}/keputusan', [PenawaranController::class, 'decide'])
            ->whereNumber('quotation')->middleware('throttle:20,1')->name('penawaran.decide');

        Route::get('/pesanan', [PesananController::class, 'index'])->name('pesanan.index');
        Route::get('/pengiriman', [PengirimanController::class, 'index'])->name('pengiriman.index');

        Route::get('/retur', [ReturController::class, 'create'])->name('retur.create');
        Route::post('/retur', [ReturController::class, 'store'])
            ->middleware('throttle:20,1')->name('retur.store');

        Route::get('/kontrak', [KontrakController::class, 'index'])->name('kontrak.index');
        Route::post('/kontrak/{contract}/setujui', [KontrakController::class, 'acknowledge'])
            ->whereNumber('contract')->middleware('throttle:20,1')->name('kontrak.acknowledge');

        Route::get('/kredit', [KreditController::class, 'index'])->name('kredit.index');

        Route::get('/data-perusahaan', [DataPerusahaanController::class, 'edit'])->name('data.edit');
        Route::put('/data-perusahaan', [DataPerusahaanController::class, 'update'])
            ->middleware('throttle:10,60')->name('data.update');
    });

    /*
     * ── Layanan vendor (Fase 4) ─────────────────────────────────────────
     *
     * Penjaganya sama dengan sisi pelanggan, tipe mitranya saja yang berbeda.
     * Sama pula aturannya: tidak ada rute yang menerima id vendor — id-nya
     * selalu digali dari activeLink di ErpVendorApi.
     */
    Route::middleware('mitra:vendor')->prefix('vendor')->name('vendor.')->group(function () {

        Route::get('/purchase-order', [PurchaseOrderController::class, 'index'])->name('po.index');
        Route::post('/purchase-order/{purchaseOrder}/konfirmasi', [PurchaseOrderController::class, 'confirm'])
            ->whereNumber('purchaseOrder')->middleware('throttle:20,1')->name('po.confirm');

        Route::get('/tagihan', [TagihanVendorController::class, 'create'])->name('tagihan.create');
        Route::post('/tagihan', [TagihanVendorController::class, 'store'])
            ->middleware('throttle:20,1')->name('tagihan.store');

        Route::get('/pembayaran', [PembayaranVendorController::class, 'index'])->name('pembayaran.index');

        Route::get('/surat-jalan', [SuratJalanController::class, 'index'])->name('surat-jalan.index');
        Route::post('/surat-jalan', [SuratJalanController::class, 'store'])
            ->middleware('throttle:20,1')->name('surat-jalan.store');

        Route::get('/retur', [ReturVendorController::class, 'index'])->name('retur.index');
        Route::post('/retur/{purchaseReturn}/sanggah', [ReturVendorController::class, 'dispute'])
            ->whereNumber('purchaseReturn')->middleware('throttle:10,60')->name('retur.dispute');

        Route::get('/kontrak', [KontrakVendorController::class, 'index'])->name('kontrak.index');
        Route::post('/kontrak/{contract}/setujui', [KontrakVendorController::class, 'acknowledge'])
            ->whereNumber('contract')->middleware('throttle:20,1')->name('kontrak.acknowledge');

        // Perubahan data rekening lewat sini pun tetap jadi antrean yang
        // disetujui staf. Throttle-nya lebih ketat: pengajuan rekening
        // beruntun adalah pola penipuan, bukan pola pengguna normal.
        Route::get('/data', [DataVendorController::class, 'edit'])->name('data.edit');
        Route::put('/data', [DataVendorController::class, 'update'])
            ->middleware('throttle:5,60')->name('data.update');
    });

    /*
     * ── Ruang admin portal ──────────────────────────────────────────────
     *
     * Untuk memantau kondisi portal: pengajuan yang gagal terkirim ke ERP,
     * lalu lintas API, dan pengumuman. Sengaja TIDAK bisa menyetujui apa pun —
     * keputusan atas data mitra ada di ERP, di tangan staf yang berwenang.
     *
     * Yang bukan admin mendapat 404, bukan 403.
     */
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::post('/pengajuan/{submission}/antre-ulang', [AdminDashboardController::class, 'antreUlang'])
            ->whereNumber('submission')->name('antre-ulang');
        Route::post('/pengajuan/antre-ulang-semua', [AdminDashboardController::class, 'antreUlangSemua'])
            ->name('antre-ulang-semua');

        Route::get('/pengumuman', [AdminMaintenanceController::class, 'edit'])->name('maintenance');
        Route::put('/pengumuman', [AdminMaintenanceController::class, 'update'])->name('maintenance.update');

        // Lihat portal dari sudut pandang mitra tertentu. Hanya baca —
        // TolakTulisSaatLihatSebagai menolak seluruh aksi kirim selama aktif.
        Route::get('/lihat-sebagai', [LihatSebagaiController::class, 'index'])->name('lihat-sebagai');
        Route::post('/lihat-sebagai/{link}', [LihatSebagaiController::class, 'pilih'])
            ->whereNumber('link')->name('lihat-sebagai.pilih');
        Route::post('/lihat-sebagai/selesai', [LihatSebagaiController::class, 'selesai'])->name('lihat-sebagai.selesai');
    });

    // Profil.
    Route::get('/profil', [ProfileController::class, 'edit'])->name('profil.edit');
    Route::put('/profil/akun', [ProfileController::class, 'updateAccount'])->name('profil.akun');
    Route::put('/profil/sandi', [ProfileController::class, 'updatePassword'])->name('profil.sandi');
    Route::post('/profil/keluar-perangkat-lain', [ProfileController::class, 'logoutOtherDevices'])->name('profil.logout-others');
    Route::post('/profil/ganti-peran', [ProfileController::class, 'switchRole'])->name('profil.ganti-peran');
});
