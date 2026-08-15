<?php

namespace App\Http\Controllers\Layanan\Umum;

use App\Http\Controllers\Layanan\LayananController;
use App\Services\Erp\ErpPublicApi;

/**
 * Induk layanan yang terbuka untuk semua akun portal, termasuk yang belum
 * terverifikasi sebagai mitra.
 *
 * Tidak ada middleware `mitra:*` di atasnya — yang dijaga di sini bukan siapa
 * penggunanya, melainkan apa yang boleh dilihat: ERP menyaring lowongan
 * internal, dan tidak ada satu pun endpoint di sini yang menyentuh data mitra.
 */
abstract class LayananUmumController extends LayananController
{
    public function __construct(protected ErpPublicApi $erp)
    {
    }
}
