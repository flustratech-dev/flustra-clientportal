<?php

namespace App\Http\Controllers\Layanan;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Lacak Pengiriman — kurir, nomor resi, dan posisi terakhir.
 *
 * Ini pertanyaan yang paling sering masuk lewat WhatsApp ("barang saya sudah
 * dikirim belum?"). Setiap kali dijawab sendiri di sini, itu satu percakapan
 * yang tidak perlu terjadi.
 *
 * Catatan penyaringan: `delivery_orders` di ERP menyimpan nama pelanggan
 * sebagai teks bebas, bukan customer_id. ERP menyaringnya lewat sales_order_id
 * — mencocokkan nama akan membocorkan pengiriman milik pelanggan lain yang
 * kebetulan namanya mirip.
 */
class PengirimanController extends LayananPelangganController
{
    public function index(Request $request): View
    {
        $hasil = $this->tarik(
            fn () => $this->erp->deliveries($this->user(), max(1, (int) $request->input('page', 1))),
            ['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0]],
        );

        return $this->halaman('layanan.pengiriman.index', [
            'deliveries' => $hasil['data'],
            'meta'       => $hasil['meta'],
        ]);
    }
}
