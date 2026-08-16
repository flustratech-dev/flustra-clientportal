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
