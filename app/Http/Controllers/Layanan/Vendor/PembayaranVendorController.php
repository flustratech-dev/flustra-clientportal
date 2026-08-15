<?php

namespace App\Http\Controllers\Layanan\Vendor;

use App\Http\Controllers\Layanan\LayananVendorController;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Status Pembayaran — tagihan mana yang sudah dibayar, dan uang muka yang
 * masih menggantung.
 *
 * Halaman baca murni, dan ini pertanyaan paling sering dari vendor. Setiap
 * kali dijawab sendiri di sini, itu satu telepon ke finance yang tidak perlu
 * terjadi.
 *
 * ERP sengaja tidak meneruskan `override_notes` pada tagihan — kolom itu bisa
 * memuat catatan internal, dan catatan internal tidak pernah boleh terlihat
 * pihak luar.
 */
class PembayaranVendorController extends LayananVendorController
{
    public function index(Request $request): View
    {
        $hasil = $this->tarik(
            fn () => $this->erp->bills($this->user(), max(1, (int) $request->input('page', 1))),
            ['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0], 'advances' => []],
        );

        return $this->halaman('layanan.vendor.pembayaran.index', [
            'bills'    => $hasil['data'],
            'meta'     => $hasil['meta'],
            'advances' => $hasil['advances'] ?? [],
        ]);
    }
}
