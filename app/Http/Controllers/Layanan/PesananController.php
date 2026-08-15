<?php

namespace App\Http\Controllers\Layanan;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Pesanan Saya — status sales order yang sedang berjalan.
 *
 * Halaman baca murni. Pesanan dibuat staf dari penawaran yang disetujui;
 * pelanggan hanya perlu tahu sudah sampai mana.
 */
class PesananController extends LayananPelangganController
{
    public function index(Request $request): View
    {
        $hasil = $this->tarik(
            fn () => $this->erp->salesOrders($this->user(), max(1, (int) $request->input('page', 1))),
            ['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0]],
        );

        return $this->halaman('layanan.pesanan.index', [
            'orders' => $hasil['data'],
            'meta'   => $hasil['meta'],
        ]);
    }
}
