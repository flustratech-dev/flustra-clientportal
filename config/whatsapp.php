<?php

/*
|--------------------------------------------------------------------------
| Klien Flustra WA Gateway
|--------------------------------------------------------------------------
|
| File ini disalin apa adanya ke setiap aplikasi Flustra yang perlu mengirim
| WhatsApp (flustra-erp, flustra-web, flustra-pricing, flustra-helpdesk).
| Pasangannya: app/Services/WhatsAppGateway.php.
|
| Sumber aslinya ada di repo flustra-wa (docs/client/). Kalau ada perubahan,
| ubah di sana dulu lalu salin ulang ke tiap aplikasi.
|
*/

return [

    'enabled' => env('WA_GATEWAY_ENABLED', true),

    'url' => env('WA_GATEWAY_URL', 'https://wa.flustra.id'),

    // API key milik tenant aplikasi ini di dashboard gateway.
    'key' => env('WA_GATEWAY_KEY'),

    // Kosongkan untuk memakai sesi tenant pertama yang sedang terhubung.
    // Isi kalau aplikasi ini harus selalu mengirim dari nomor tertentu.
    'session' => env('WA_GATEWAY_SESSION'),

    // Sengaja pendek. Notifikasi WhatsApp adalah pelengkap email, bukan
    // penggantinya — gateway yang lambat tidak boleh menahan request pengguna.
    'timeout' => (int) env('WA_GATEWAY_TIMEOUT', 5),

    'connect_timeout' => (int) env('WA_GATEWAY_CONNECT_TIMEOUT', 2),

];
