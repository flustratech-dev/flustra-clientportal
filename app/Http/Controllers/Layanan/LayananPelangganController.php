<?php

namespace App\Http\Controllers\Layanan;

use App\Services\Erp\ErpCustomerApi;

/**
 * Induk sembilan layanan pelanggan. Isinya hanya penyuntikan kliennya;
 * seluruh aturan ketahanan ada di LayananController.
 */
abstract class LayananPelangganController extends LayananController
{
    public function __construct(protected ErpCustomerApi $erp)
    {
    }
}
