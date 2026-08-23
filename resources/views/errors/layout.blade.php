{{--
    Kerangka bersama seluruh halaman error portal.

    Berdiri sendiri, tanpa @vite dan tanpa Alpine: halaman 500 justru muncul
    ketika ada yang rusak, dan halaman error yang ikut bergantung pada aset
    hasil build akan gagal dirender persis pada saat paling dibutuhkan.
    Semua gaya ditulis inline dengan alasan yang sama.

    Nadanya sengaja tenang dan tanpa istilah teknis. Yang membacanya adalah
    mitra kantor, bukan tim teknis — "HTTP 419 Page Expired" tidak memberi tahu
    mereka apa pun tentang apa yang harus dilakukan berikutnya.
--}}
<!DOCTYPE html>
<html lang="id" translate="no" class="notranslate">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="google" content="notranslate">
    {{-- Ikon tab. Naikkan ?v= setiap kali berkasnya diganti: favicon di-cache
         peramban jauh lebih lengket daripada aset biasa. --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=3" sizes="any">
    {{-- Ikon layar utama iOS. Sengaja persegi penuh: iOS mengabaikan transparansi
         dan memasang mask sudut membulatnya sendiri, jadi badge bersudut transparan
         justru tampil dengan sudut hitam. --}}
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}?v=1">
    <title>@yield('judul') &middot; {{ config('app.name') }}</title>
    @include('partials.tema')
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Instrument Sans', 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: #f0f4f9; color: #1e293b; padding: 24px;
        }
        .kotak {
            width: 100%; max-width: 460px; background: #fff; border: 1px solid #e2e8f0;
            border-radius: 24px; padding: 40px 32px; text-align: center;
            box-shadow: 0 10px 30px -12px rgba(15, 23, 42, .12);
        }
        .kode { font-size: 44px; font-weight: 800; letter-spacing: -1px; color: #3572EF; line-height: 1; }
        h1 { font-size: 17px; font-weight: 700; margin-top: 14px; color: #0f172a; }
        p { font-size: 13px; line-height: 1.7; color: #64748b; margin-top: 10px; }
        .tombol {
            display: inline-block; margin-top: 24px; padding: 11px 22px; border-radius: 9999px;
            background: #3572EF; color: #fff; font-size: 12px; font-weight: 600; text-decoration: none;
        }
        .tombol:hover { background: #1d4ed8; }
        .tautan { display: block; margin-top: 14px; font-size: 11px; color: #94a3b8; text-decoration: none; }
        .tautan:hover { color: #64748b; }

        html.dark body { background: #090d16; color: #e2e8f0; }
        html.dark .kotak { background: #09090b; border-color: #1c1c1e; box-shadow: none; }
        html.dark h1 { color: #fff; }
        html.dark p, html.dark .tautan { color: #94a3b8; }
    </style>
</head>
<body>
    <div class="kotak">
        <div class="kode">@yield('kode')</div>
        <h1>@yield('judul')</h1>
        <p>@yield('pesan')</p>

        <a href="{{ url('/') }}" class="tombol">@yield('aksi', 'Kembali ke Beranda')</a>
        <a href="{{ route('bantuan') }}" class="tautan">Butuh bantuan? Hubungi kami</a>
    </div>
</body>
</html>
