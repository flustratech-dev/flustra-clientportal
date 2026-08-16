<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Masuk dengan Google
    |--------------------------------------------------------------------------
    |
    | Kredensial kosong berarti fitur ini MATI, bukan rusak: tombolnya tidak
    | muncul di halaman masuk dan rutenya membalas 404. Itu disengaja — portal
    | harus tetap bisa dipasang di lingkungan yang belum punya OAuth client
    | tanpa halaman masuknya menampilkan tombol yang pasti gagal.
    |
    | Redirect URI yang harus didaftarkan di Google Cloud Console persis sama
    | dengan nilai `redirect` di bawah, termasuk skema dan trailing path.
    |
    */
    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI', env('APP_URL').'/masuk/google/callback'),
    ],

];
