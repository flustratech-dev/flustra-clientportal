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

        /*
         * Umur cache pembacaan ERP, dalam detik. 0 = mati.
         *
         * Ini BUKAN salinan data ERP di portal — aturan "portal tidak menyimpan
         * salinan data transaksi" tetap berlaku, dan alasannya tetap benar:
         * saldo tagihan yang basi lebih berbahaya daripada tidak ada saldo sama
         * sekali.
         *
         * Yang dikerjakan cache ini adalah menggabungkan permintaan yang
         * berdekatan. Satu mitra membuka Tagihan, menekan tombol kembali, lalu
         * membukanya lagi — tanpa cache itu tiga panggilan HTTP ke ERP dalam
         * sepuluh detik untuk jawaban yang sama persis. Dengan 30 detik, angka
         * yang dilihat mitra tidak pernah lebih tua dari setengah menit, dan
         * beban ERP saat banyak mitra membuka bersamaan turun drastis.
         *
         * Dua penjaga yang membuat ini aman:
         *  - hanya GET, dan hanya lewat ErpCustomerApi / ErpVendorApi;
         *  - setiap POST milik mitra itu langsung membuang seluruh cache-nya,
         *    jadi orang yang baru saja mengirim sesuatu selalu melihat keadaan
         *    terbaru — bukan jawaban dari sebelum ia menekan Kirim.
         *
         * Naikkan hanya kalau ERP benar-benar kewalahan, dan sadari bahwa yang
         * dibayar adalah kesegaran angka yang dilihat mitra.
         */
        'read_cache_seconds' => (int) env('ERP_READ_CACHE_SECONDS', 30),
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
        'email'    => env('PORTAL_CONTACT_EMAIL', 'flustratechcompany@gmail.com'),
        'whatsapp' => env('PORTAL_CONTACT_WHATSAPP'),
    ],

];
