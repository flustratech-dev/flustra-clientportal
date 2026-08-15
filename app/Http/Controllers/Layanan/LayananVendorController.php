<?php

namespace App\Http\Controllers\Layanan;

use App\Services\Erp\ErpVendorApi;

/**
 * Induk tujuh layanan vendor. Kembaran LayananPelangganController, beda
 * kliennya saja.
 */
abstract class LayananVendorController extends LayananController
{
    public function __construct(protected ErpVendorApi $erp)
    {
    }
}
