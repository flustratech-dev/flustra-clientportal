<?php

namespace App\Http\Controllers\Layanan;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Erp\ErpException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Induk seluruh controller layanan pelanggan.
 *
 * Isinya satu gagasan: **halaman tidak boleh mati hanya karena ERP mati.**
 *
 * Halaman-halaman ini membaca langsung dari ERP — tidak ada salinannya di
 * portal, dan memang tidak boleh ada, karena saldo tagihan yang basi lebih
 * berbahaya daripada tidak ada saldo sama sekali. Tapi "membaca langsung"
 * tidak berarti "ikut mati". Ketika ERP tidak menjawab, halamannya tetap
 * terbuka dengan daftar kosong dan satu kalimat jujur; pengguna tahu ini
 * gangguan sementara, bukan datanya yang hilang.
 *
 * Bandingkan dengan sisi tulis: di sana pengajuan disimpan dulu di portal lalu
 * diantre (lihat SyncSubmissionToErp). Dua sisi, dua strategi, alasan yang
 * sama — kegagalan ERP tidak boleh jadi kegagalan pengguna.
 *
 * Kelas ini tidak menyuntik klien ERP mana pun; itu urusan dua turunannya,
 * `LayananPelangganController` dan `LayananVendorController`. Aturan ketahanan
 * di sini identik untuk kedua sisi, dan menyalinnya jadi dua akan membuat sisi
 * vendor perlahan berbeda dari sisi pelanggan tanpa ada yang berniat begitu.
 */
abstract class LayananController extends Controller
{
    /** Terisi bila panggilan ke ERP gagal; ikut dikirim ke setiap view. */
    protected ?string $erpError = null;

    protected function user(): User
    {
        return Auth::user();
    }

    /**
     * Jalankan panggilan ke ERP, dan kembalikan $fallback bila gagal.
     *
     * Sengaja hanya menangkap ErpException. Galat lain (bug di portal sendiri)
     * tetap naik ke atas dan muncul sebagai galat — menyamarkannya sebagai
     * "ERP sedang gangguan" akan membuat bug kita sendiri tak pernah ketahuan.
     *
     * @template T
     *
     * @param  callable(): T  $panggil
     * @param  T  $fallback
     * @return T
     */
    protected function tarik(callable $panggil, mixed $fallback = [])
    {
        try {
            return $panggil();
        } catch (ErpException $e) {
            Log::warning('Gagal membaca data dari ERP.', [
                'user_id' => $this->user()->id,
                'error'   => $e->getMessage(),
            ]);

            $this->erpError = $e->statusCode === null
                ? 'Data terbaru sedang tidak bisa kami ambil dari sistem internal. Ini gangguan sementara — data Anda aman. Coba muat ulang beberapa saat lagi.'
                : 'Sistem kami menolak permintaan ini. Tim kami sudah menerima catatannya; silakan hubungi kami lewat halaman Bantuan bila terus berulang.';

            return $fallback;
        }
    }

    /**
     * Versi `tarik()` untuk halaman detail satu dokumen.
     *
     * Bedanya: 404 dari ERP diteruskan sebagai 404 di portal. ERP membalas 404
     * — bukan 403 — untuk dokumen milik mitra lain, justru supaya penebak tidak
     * bisa membedakan "tidak boleh" dari "tidak ada". Menerjemahkannya jadi
     * "sistem sedang gangguan" akan membuang perbedaan itu dan, lebih buruk,
     * memberi kesan datanya ada tapi sedang tidak bisa dibuka.
     *
     * @param  callable(): array<string, mixed>  $panggil
     * @return array<string, mixed>
     */
    protected function tarikSatu(callable $panggil): array
    {
        try {
            return $panggil();
        } catch (ErpException $e) {
            if ($e->statusCode === 404) {
                abort(404);
            }

            return $this->tarik(fn () => throw $e, []);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function halaman(string $view, array $data = []): View
    {
        return view($view, $data + ['erpError' => $this->erpError]);
    }
}
