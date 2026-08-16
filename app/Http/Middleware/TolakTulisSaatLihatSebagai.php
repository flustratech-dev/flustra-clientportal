<?php

namespace App\Http\Middleware;

use App\Services\KonteksMitra;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Selama admin melihat sebagai mitra lain, seluruh aksi tulis ditolak.
 *
 * Admin boleh melihat apa saja — itu memang tugasnya saat memeriksa keluhan.
 * Yang tidak boleh adalah **mengirim pengajuan atas nama orang lain**: riwayat
 * mitra jadi memuat hal yang tidak pernah ia lakukan, dan jejak auditnya
 * berbohong tentang siapa yang menekan tombolnya.
 *
 * Kalau admin memang perlu mengirimkan sesuatu untuk mitra, jalurnya adalah
 * mengerjakannya di ERP dengan akunnya sendiri — di sana pelakunya tercatat
 * dengan benar.
 *
 * Dipasang pada metode tulis saja (POST/PUT/PATCH/DELETE); GET tetap bebas.
 */
class TolakTulisSaatLihatSebagai
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return $next($request);
        }

        if (! KonteksMitra::sedangLihatSebagai($request->user())) {
            return $next($request);
        }

        /*
         * Kecuali mengganti atau mengakhiri konteksnya sendiri.
         *
         * Tanpa pengecualian ini, admin yang sudah masuk ke satu mitra
         * terkunci di sana: tombol "Lihat Sebagai Ini" dan "Selesai" sama-sama
         * POST, dan penjaga ini akan menolak keduanya. Keluar dari mode
         * lihat-sebagai tidak boleh butuh logout.
         */
        if ($request->routeIs('admin.lihat-sebagai.*')) {
            return $next($request);
        }

        $link = KonteksMitra::pilihanAdmin();

        return back()->with('error',
            'Anda sedang melihat sebagai '.($link?->company_name ?? 'mitra lain')
            .', jadi pengiriman ditolak. Pengajuan atas nama mitra harus dikerjakan di Flustra Office '
            .'dengan akun Anda sendiri, supaya jejaknya benar.'
        );
    }
}
