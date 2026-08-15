<?php

use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Portal — hanya untuk flustra-erp
|--------------------------------------------------------------------------
|
| Tidak ada API publik di portal ini. Satu-satunya rute di sini adalah penerima
| webhook dari ERP: perubahan status yang diputuskan staf di sana (klaim
| disetujui, pembayaran diverifikasi, kerja sama dicabut) didorong ke portal
| lewat rute ini.
|
| Penjagaannya ada di WebhookController, bukan di middleware: tanda tangan
| HMAC dihitung atas RAW BODY, dan itu harus dibaca sebelum apa pun menyentuh
| muatannya.
|
| Rute ini sengaja tanpa CSRF (grup api memang begitu) dan tanpa sesi — ERP
| memanggilnya server-ke-server, tidak pernah dari peramban.
|
*/

Route::post('/webhooks/erp', [WebhookController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('webhooks.erp');
