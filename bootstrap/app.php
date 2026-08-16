<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        /*
         * Portal selalu berdiri di belakang reverse proxy di produksi (Traefik
         * milik Coolify). Proxy itu yang memegang TLS, lalu meneruskan ke
         * container sebagai HTTP biasa. Tanpa baris ini Laravel percaya
         * dirinya diakses lewat http, dan akibatnya berantai:
         *
         *  1. Seluruh URL aset dibuat `http://…`, lalu diblokir peramban
         *     sebagai *mixed content* — halaman terbuka tapi tanpa satu pun
         *     CSS. Ini yang terjadi pada deploy pertama, 16 Agustus 2026.
         *  2. `$request->ip()` mengembalikan IP proxy, bukan IP pengunjung.
         *     Itu merusak tiga hal sekaligus: `throttle` jadi menghitung
         *     SEMUA pengguna sebagai satu alamat, `last_login_ip` mencatat
         *     alamat yang salah, dan `actor_ip` pada persetujuan kontrak —
         *     yang justru menjadi bentuk final pengganti tanda tangan
         *     (CLAUDE.md §3 no.5) — kehilangan artinya.
         *
         * `at: '*'` aman di sini: container tidak pernah terekspos langsung
         * ke internet, satu-satunya jalan masuk adalah proxy Coolify.
         */
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            // Dipakai 'mitra:customer' dan 'mitra:vendor'.
            'mitra' => \App\Http\Middleware\EnsureMitra::class,
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
        ]);

        /*
         * Selama admin melihat sebagai mitra lain, aksi tulis ditolak di
         * SELURUH rute yang sudah masuk — bukan hanya di rute layanan.
         * Dipasang global supaya tidak ada rute baru yang lupa memasangnya.
         */
        $middleware->appendToGroup('web', \App\Http\Middleware\TolakTulisSaatLihatSebagai::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
