<?php

namespace App\Http\Controllers\Layanan;

use Illuminate\Contracts\View\View;

/**
 * Kredit & Plafon — baca saja.
 *
 * Limit, terpakai, dan sisa plafon. Tidak ada tombol apa pun di sini: plafon
 * adalah keputusan internal, dan pelanggan yang bisa mengajukan kenaikannya
 * lewat satu klik hanya akan memindahkan negosiasi ke tempat yang salah.
 *
 * `available_credit` bernilai -1 di ERP berarti tanpa batas, bukan minus.
 */
class KreditController extends LayananPelangganController
{
    public function index(): View
    {
        $summary = $this->tarik(fn () => $this->erp->summary($this->user()), []);

        return $this->halaman('layanan.kredit.index', ['s' => $summary]);
    }
}
