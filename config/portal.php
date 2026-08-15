<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Integrasi flustra-erp
    |--------------------------------------------------------------------------
    |
    | ERP adalah sumber kebenaran seluruh data transaksi. Portal hanya konsumen:
    | membaca lewat endpoint di bawah dan menitipkan kiriman mitra ke sana.
    |
    */

    'erp' => [
        'url'     => rtrim((string) env('ERP_API_URL', 'http://localhost:8006/api/portal/v1'), '/'),
        'token'   => env('ERP_API_TOKEN'),
        'timeout' => (int) env('ERP_TIMEOUT', 15),

        /*
         * Unggahan berkas butuh napas lebih panjang daripada panggilan biasa:
         * bukti transfer 5 MB lewat koneksi mitra bisa memakan puluhan detik.
         */
        'upload_timeout' => (int) env('ERP_UPLOAD_TIMEOUT', 60),
    ],

    /*
     * Rahasia HMAC untuk memverifikasi webhook MASUK dari ERP. Harus sama
     * dengan PORTAL_WEBHOOK_SECRET di flustra-erp/.env.
     *
     * Kosong = webhook ditolak semua. Disengaja: menerima webhook tanpa
     * memverifikasi tanda tangan berarti siapa pun bisa menaikkan tipe akunnya
     * sendiri jadi pelanggan.
     */
    'webhook_secret' => env('ERP_WEBHOOK_SECRET'),

    /*
     * Umur maksimum webhook yang masih diterima, dalam detik. Kiriman yang
     * lebih tua ditolak supaya permintaan lama tidak bisa diputar ulang.
     */
    'webhook_max_age' => (int) env('ERP_WEBHOOK_MAX_AGE', 300),

    /*
     * Umur URL bukti klaim yang dititipkan ke ERP, dalam hari.
     *
     * Berkasnya tetap di disk privat dan tidak pernah bisa diambil lewat URL
     * langsung. Yang dikirim ke ERP hanya tautan bertanda tangan — cukup lama
     * agar staf sempat memeriksanya, tapi tetap kedaluwarsa. Sengaja lebih
     * panjang dari signed_url_minutes: itu untuk pengguna yang sedang membuka
     * halaman, ini untuk staf yang mungkin baru memeriksa besok.
     */
    'evidence_url_days' => (int) env('ERP_EVIDENCE_URL_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Perilaku portal
    |--------------------------------------------------------------------------
    */

    /*
     * Pendaftaran terbuka & instan — SENGAJA berbeda dari flustra-erp. Di sini
     * tidak ada halaman "menunggu persetujuan admin"; yang dijaga bukan akunnya
     * melainkan datanya, lewat verifikasi klaim mitra.
     */
    'registration_open' => filter_var(env('PORTAL_REGISTRATION_OPEN', true), FILTER_VALIDATE_BOOLEAN),

    'max_upload_kb'       => (int) env('PORTAL_MAX_UPLOAD_KB', 5120),
    'signed_url_minutes'  => (int) env('PORTAL_SIGNED_URL_MINUTES', 5),

    'contact' => [
        'email'    => env('PORTAL_CONTACT_EMAIL'),
        'whatsapp' => env('PORTAL_CONTACT_WHATSAPP'),
    ],

];
