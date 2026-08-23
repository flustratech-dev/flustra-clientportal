<!DOCTYPE html>
<html lang="id" translate="no" class="notranslate" x-data="{ darkMode: localStorage.getItem('darkMode') === 'true' }" :class="{ 'dark': darkMode }"
      @tema-luar.window="darkMode = $event.detail.gelap">
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
    <title>Portal Klien Flustra</title>
    <meta name="description" content="Satu pintu untuk pelanggan dan vendor Flustra: konfirmasi pembayaran, penawaran, tagihan, dan pengiriman.">

    {{-- Plus Jakarta Sans dilokalkan di resources/css/layout.css — lihat
         catatannya di sana. --}}
    <link rel="preload" href="/fonts/plus-jakarta-sans-variable.woff2" as="font" type="font/woff2" crossorigin>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @include('partials.tema')
    <style>
        html { background-color: #f0f4f9; }
        html.dark { background-color: #090d16; }
        body { background-color: #f0f4f9; font-family: 'Plus Jakarta Sans', sans-serif; }
        html.dark body { background-color: #090d16; }
        [x-cloak] { display: none; }
    </style>
</head>
<body class="bg-[#f0f4f9] dark:bg-[#090d16] text-slate-800 dark:text-slate-100 min-h-screen flex flex-col transition-colors duration-300 relative overflow-x-hidden">

<!-- Ambient glow, sama seperti halaman auth flustra-erp -->
<div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
    <div class="absolute -top-[20%] -left-[10%] w-[55%] h-[55%] rounded-full bg-blue-400/30 dark:bg-blue-500/15 blur-[100px] animate-glow-1"></div>
    <div class="absolute -bottom-[20%] -right-[10%] w-[55%] h-[55%] rounded-full bg-indigo-500/25 dark:bg-indigo-600/15 blur-[100px] animate-glow-2"></div>
    <div class="absolute top-[30%] left-[40%] w-[35%] h-[35%] rounded-full bg-cyan-400/20 dark:bg-cyan-500/10 blur-[80px] animate-glow-3"></div>
</div>

<!-- Header -->
<header class="relative z-10 px-5 sm:px-8 py-4 flex items-center justify-between">
    <a href="{{ route('welcome') }}" class="flex items-center gap-2.5 group">
        <img src="{{ asset('images/flustraa.png') }}" alt="Flustra Logo" class="w-8 h-8 object-contain group-hover:scale-105 transition-transform duration-200">
        <div class="flex flex-col justify-center">
            <span class="text-base font-bold tracking-tight text-slate-900 dark:text-white leading-none">Flustra</span>
            <span class="text-[8px] uppercase font-bold tracking-wider text-[#3572EF] leading-none mt-0.5">Client Portal</span>
        </div>
    </a>

    <div class="flex items-center gap-2">
        <button @click="darkMode = !darkMode; localStorage.setItem('darkMode', darkMode)"
                class="p-2 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/50 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all shadow-sm cursor-pointer"
                aria-label="Ganti tema">
            <svg x-show="darkMode" x-cloak class="w-4.5 h-4.5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z"/>
            </svg>
            <svg x-show="!darkMode" class="w-4.5 h-4.5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
            </svg>
        </button>

        <a href="{{ route('login') }}" class="btn-secondary !rounded-full !px-4">Masuk</a>
        <a href="{{ route('register') }}" class="btn-primary !rounded-full !px-4">Daftar</a>
    </div>
</header>

<main class="relative z-10 flex-1">

    <!-- Hero -->
    <section class="px-5 sm:px-8 pt-10 pb-16 max-w-6xl mx-auto">
        <div class="grid lg:grid-cols-2 gap-10 items-center">
            <div>
                <span class="inline-block px-3 py-1 rounded-full bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 text-[10px] font-bold uppercase tracking-wider mb-4">
                    Untuk Pelanggan &amp; Vendor Flustra
                </span>

                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-slate-900 dark:text-white leading-tight tracking-tight">
                    Urusan Anda dengan Flustra,<br class="hidden sm:block"> di satu tempat.
                </h1>

                <p class="mt-4 text-sm text-slate-600 dark:text-slate-400 leading-relaxed max-w-lg">
                    Konfirmasi pembayaran, setujui penawaran, kirim tagihan, dan lacak pengiriman —
                    tanpa perlu menunggu balasan pesan. Statusnya bisa Anda lihat sendiri, kapan saja.
                </p>

                <div class="mt-7 flex flex-wrap gap-3">
                    <a href="{{ route('register') }}" class="btn-primary !rounded-full !px-6 !py-2.5 !text-sm">
                        Daftar Gratis
                    </a>
                    <a href="{{ route('login') }}" class="btn-secondary !rounded-full !px-6 !py-2.5 !text-sm">
                        Sudah punya akun
                    </a>
                </div>

                <p class="mt-4 text-[11px] text-slate-500 dark:text-slate-500">
                    Pendaftaran langsung aktif — tidak ada antrean persetujuan.
                </p>
            </div>

            <!-- Kartu ilustrasi -->
            <div class="floating-card bg-white/70 dark:bg-slate-900/70 backdrop-blur rounded-3xl border border-white/60 dark:border-slate-800 p-6 space-y-3">
                @foreach([
                    ['Konfirmasi Pembayaran', 'Disetujui', 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400'],
                    ['Penawaran #QT-2608-0042', 'Menunggu Anda', 'bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400'],
                    ['Pengiriman DO-20260815', 'Dalam Perjalanan', 'bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400'],
                    ['Tagihan Vendor #INV-771', 'Sedang Ditinjau', 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'],
                ] as [$judul, $status, $warna])
                    <div class="flex items-center justify-between gap-3 p-3 rounded-2xl bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700">
                        <span class="text-xs font-semibold text-slate-700 dark:text-slate-200 truncate">{{ $judul }}</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold shrink-0 {{ $warna }}">{{ $status }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Untuk siapa -->
    <section class="px-5 sm:px-8 py-12 max-w-6xl mx-auto">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white text-center mb-2">Portal ini untuk siapa?</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 text-center mb-8">Pilih yang sesuai dengan hubungan Anda dengan Flustra.</p>

        <div class="grid sm:grid-cols-3 gap-4">
            @foreach([
                ['Pelanggan', 'Anda membeli produk atau jasa dari Flustra.', [
                    'Konfirmasi pembayaran &amp; unggah bukti transfer',
                    'Setujui atau tolak penawaran',
                    'Lihat tagihan &amp; lacak pengiriman',
                ]],
                ['Vendor &amp; Supplier', 'Anda memasok barang atau jasa ke Flustra.', [
                    'Konfirmasi kesanggupan purchase order',
                    'Kirim tagihan beserta dokumennya',
                    'Pantau status pembayaran Anda',
                ]],
                ['Calon Mitra &amp; Pelamar', 'Belum bekerja sama, atau ingin bergabung.', [
                    'Ajukan kerja sama sebagai pelanggan/vendor',
                    'Minta penawaran harga',
                    'Lihat lowongan &amp; kirim lamaran',
                ]],
            ] as [$judul, $sub, $poin])
                <div class="erp-card floating-card !bg-white/80 dark:!bg-slate-900/70 backdrop-blur !border-white/60 dark:!border-slate-800">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">{!! $judul !!}</h3>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 mb-3">{{ $sub }}</p>
                    <ul class="space-y-1.5">
                        @foreach($poin as $p)
                            <li class="flex items-start gap-2 text-[11px] text-slate-600 dark:text-slate-300">
                                <svg class="w-3.5 h-3.5 text-blue-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span>{!! $p !!}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </section>

    <!-- Cara kerja -->
    <section class="px-5 sm:px-8 py-12 max-w-4xl mx-auto">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white text-center mb-8">Tiga langkah saja</h2>

        <div class="grid sm:grid-cols-3 gap-4">
            @foreach([
                ['1', 'Daftar', 'Isi nama, email, dan kata sandi. Anda langsung masuk — tidak ada antrean persetujuan.'],
                ['2', 'Ajukan verifikasi', 'Sebutkan nama perusahaan Anda dan satu bukti: nomor invoice, nomor PO, atau kode undangan dari kami.'],
                ['3', 'Layanan terbuka', 'Setelah tim kami mencocokkan data, seluruh layanan pelanggan atau vendor bisa Anda pakai.'],
            ] as [$no, $judul, $isi])
                <div class="erp-card !bg-white/80 dark:!bg-slate-900/70 backdrop-blur !border-white/60 dark:!border-slate-800">
                    <div class="w-7 h-7 rounded-full bg-blue-600 dark:bg-blue-500 text-white text-xs font-bold flex items-center justify-center mb-3">{{ $no }}</div>
                    <h3 class="text-xs font-bold text-slate-900 dark:text-white mb-1">{{ $judul }}</h3>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">{{ $isi }}</p>
                </div>
            @endforeach
        </div>

        <div class="text-center mt-8">
            <a href="{{ route('register') }}" class="btn-primary !rounded-full !px-7 !py-2.5 !text-sm">Mulai Sekarang</a>
        </div>
    </section>

</main>

<footer class="relative z-10 px-5 sm:px-8 py-8 border-t border-slate-200/60 dark:border-slate-800">
    <div class="max-w-6xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-3 text-[11px] text-slate-500 dark:text-slate-400">
        <p>&copy; {{ date('Y') }} Flustra. Seluruh hak cipta dilindungi.</p>
        <div class="flex items-center gap-4">
            <a href="{{ route('bantuan') }}" class="hover:text-slate-800 dark:hover:text-slate-200">Bantuan</a>
            <a href="{{ route('syarat') }}" class="hover:text-slate-800 dark:hover:text-slate-200">Syarat &amp; Ketentuan</a>
            <a href="{{ route('privasi') }}" class="hover:text-slate-800 dark:hover:text-slate-200">Kebijakan Privasi</a>
        </div>
    </div>
</footer>

</body>
</html>
