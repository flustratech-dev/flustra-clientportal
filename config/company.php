<?php

/*
|--------------------------------------------------------------------------
| Identitas Perusahaan
|--------------------------------------------------------------------------
|
| Salinan data yang sama dengan `flustra-erp/config/company.php`. Portal tidak
| membuat dokumen resmi sendiri (PDF-nya selalu dari ERP); berkas ini hanya
| untuk teks kop pada halaman cetak milik portal, seperti tanda terima
| pengajuan. Kalau data perusahaan berubah, ubah di kedua tempat.
|
*/

return [
    'name'          => env('COMPANY_NAME', 'PT FLUSTRA FINANCES ARTHA'),
    'address'       => env('COMPANY_ADDRESS', 'Tangerang Selatan, Pondok Aren, Banten, Indonesia 15224'),
    'email_office'  => env('COMPANY_EMAIL_OFFICE', 'office@flustra.id'),
    'email_support' => env('COMPANY_EMAIL_SUPPORT', 'support@flustra.id'),
    'phone'         => env('COMPANY_PHONE', '+62 822-7727-1822'),
    'website'       => env('COMPANY_WEBSITE', 'https://flustra.id'),
];
