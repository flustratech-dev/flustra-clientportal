<?php

namespace App\Http\Controllers\Layanan;

use App\Services\Erp\ErpException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Tagihan Saya — daftar invoice milik pelanggan ini beserta sisa tagihannya.
 *
 * Tidak ada satu pun angka di sini yang disimpan portal. Semuanya ditarik
 * langsung dari ERP setiap kali halaman dibuka, karena saldo tagihan yang
 * basi lebih berbahaya daripada tidak ada saldo sama sekali: pelanggan yang
 * melihat "lunas" padahal belum akan berhenti membayar.
 *
 * ERP sudah menyaring invoice berstatus draft dan pending_approval — tagihan
 * yang belum resmi dikirim tidak boleh terlihat pelanggan.
 */
class TagihanController extends LayananPelangganController
{
    public function index(Request $request): View
    {
        $filter = $request->filled('status') ? ['status' => $request->string('status')->toString()] : [];

        $hasil = $this->tarik(
            fn () => $this->erp->invoices($this->user(), $filter, max(1, (int) $request->input('page', 1))),
            ['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0]],
        );

        return $this->halaman('layanan.tagihan.index', [
            'invoices' => $hasil['data'],
            'meta'     => $hasil['meta'],
        ]);
    }

    public function show(int $invoice): View
    {
        // Invoice milik pelanggan lain dibalas 404 oleh ERP, dan tarikSatu()
        // meneruskannya apa adanya. Jangan pernah 403 — itu memberitahu penebak
        // bahwa nomor yang dicobanya benar.
        $data = $this->tarikSatu(fn () => $this->erp->invoice($this->user(), $invoice));

        return $this->halaman('layanan.tagihan.show', ['invoice' => $data]);
    }

    /**
     * Alirkan ulang PDF dari ERP.
     *
     * Portal tidak membuat PDF-nya sendiri dan tidak menyimpan salinannya —
     * dokumen yang diterima pelanggan harus persis sama dengan yang dipegang
     * tim internal. Kalau ERP sedang mati, ini satu-satunya halaman layanan
     * yang memang tidak bisa memberi apa-apa; pengguna dikembalikan dengan
     * pesan jujur alih-alih mengunduh berkas kosong.
     */
    public function pdf(int $invoice): Response|RedirectResponse
    {
        try {
            $isi = $this->erp->invoicePdf($this->user(), $invoice);
        } catch (ErpException $e) {
            if ($e->statusCode === 404) {
                abort(404);
            }

            return redirect()
                ->route('layanan.tagihan.show', $invoice)
                ->with('error', 'Berkas PDF sedang tidak bisa kami ambil. Coba lagi beberapa saat lagi.');
        }

        return response($isi, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="tagihan-'.$invoice.'.pdf"',
        ]);
    }
}
