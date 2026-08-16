<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penjaga ruang admin portal.
 *
 * Balas 404, bukan 403 — sama seperti pagar data mitra. Mitra yang menebak
 * `/admin` tidak perlu diberi tahu bahwa halamannya memang ada.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isAdmin(), 404);

        return $next($request);
    }
}
