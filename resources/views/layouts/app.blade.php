<!DOCTYPE html>
<html lang="id" translate="no" class="notranslate" x-data="{
    darkMode: localStorage.getItem('darkMode') === 'true',
    mobileMenuOpen: false,
    showNotifyDropdown: false,
    unreadCount: 0,
    notifications: [],
    toasts: [],
    pollInterval: null,
    prevUnreadCount: null,

    // Fast native document print preview
    openDocPreview(url, title = 'Pratinjau Dokumen') {
        window.openDocPreview(url, title);
    },

    init() {
        this.$watch('darkMode', val => {
            localStorage.setItem('darkMode', val);
            document.documentElement.classList.toggle('dark', val);
        });
        document.documentElement.classList.toggle('dark', this.darkMode);

        this.pollNotifications(true);
        this.startPolling();

        // Jeda polling saat tab tidak aktif untuk menghemat beban server
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.stopPolling();
            } else {
                this.pollNotifications(false);
                this.startPolling();
            }
        });
    },

    startPolling() {
        if (!this.pollInterval) {
            // Polling realtime 8 detik saat tab aktif
            this.pollInterval = setInterval(() => this.pollNotifications(false), 8000);
        }
    },

    stopPolling() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
            this.pollInterval = null;
        }
    },

    pollNotifications(isInitial = false) {
        fetch('{{ route('notifikasi.poll') }}', {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(r => r.ok ? r.json() : null)
        .then(data => {
            if (!data) return;
            
            // Deteksi notifikasi baru masuk untuk memunculkan Toast Realtime
            if (!isInitial && this.prevUnreadCount !== null && data.unread > this.prevUnreadCount) {
                const latest = data.items && data.items[0];
                if (latest && !latest.is_read) {
                    this.pushToast(latest);
                }
            }

            this.prevUnreadCount = data.unread;
            this.unreadCount = data.unread;
            this.notifications = data.items;
        })
        .catch(() => {});
    },

    pushToast(n) {
        const toast = {
            id: n.id || Date.now(),
            title: n.title,
            body: n.body,
            url: n.url,
            visible: true
        };
        this.toasts.push(toast);
        setTimeout(() => {
            toast.visible = false;
        }, 6000);
    },

    markAllRead() {
        fetch('{{ route('notifikasi.read-all') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        }).then(() => this.pollNotifications());
    },

    // ── Pencarian cepat (Ctrl+K) ────────────────────────────────────────
    cariBuka: false,
    cariKata: '',
    cariHasil: [],
    cariPilih: 0,
    cariTimer: null,

    bukaCari() {
        this.cariBuka = true;
        this.$nextTick(() => this.$refs.cariInput?.focus());
    },

    // Ditunda 200 ms: tanpa ini setiap huruf jadi satu permintaan, dan
    // mengetik 'pembayaran' berarti sepuluh query untuk satu jawaban.
    cariBerubah() {
        clearTimeout(this.cariTimer);
        this.cariPilih = 0;

        if (this.cariKata.trim().length < 2) {
            this.cariHasil = [];
            return;
        }

        this.cariTimer = setTimeout(() => {
            fetch('{{ route('cari') }}?q=' + encodeURIComponent(this.cariKata), {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
            .then(r => r.ok ? r.json() : null)
            .then(d => { this.cariHasil = d ? d.hasil : []; })
            .catch(() => { this.cariHasil = []; });
        }, 200);
    },

    cariTurun() { if (this.cariHasil.length) this.cariPilih = (this.cariPilih + 1) % this.cariHasil.length; },
    cariNaik()  { if (this.cariHasil.length) this.cariPilih = (this.cariPilih - 1 + this.cariHasil.length) % this.cariHasil.length; },
    cariBuka2() { const h = this.cariHasil[this.cariPilih]; if (h) window.location.href = h.url; }
}"
      @tema-luar.window="darkMode = $event.detail.gelap"
      @keydown.window.ctrl.k.prevent="bukaCari()"
      @keydown.window.meta.k.prevent="bukaCari()">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    {{-- Wajib: auto-translate Chrome menulis ulang teks di dalam atribut x-data
         (mengecilkan huruf kunci, mengubah "/" jadi spasi), yang merusak JSON
         inline dan mematikan seluruh komponen Alpine di halaman ini. --}}
    <meta name="google" content="notranslate">
    {{-- Ikon tab. Naikkan ?v= setiap kali berkasnya diganti: favicon di-cache
         peramban jauh lebih lengket daripada aset biasa. --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=3" sizes="any">
    {{-- Ikon layar utama iOS. Sengaja persegi penuh: iOS mengabaikan transparansi
         dan memasang mask sudut membulatnya sendiri, jadi badge bersudut transparan
         justru tampil dengan sudut hitam. --}}
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}?v=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Beranda') &middot; {{ config('app.name') }}</title>

    <script src="{{ asset('vendor/sweetalert2/sweetalert2.all.min.js') }}"></script>
    <script>
        if (typeof Swal !== 'undefined') {
            window.alert = function (message) {
                const isDark = document.documentElement.classList.contains('dark') || localStorage.getItem('darkMode') === 'true';
                let icon = 'info';
                let title = 'Pemberitahuan';
                const lower = String(message || '').toLowerCase();
                if (lower.includes('gagal') || lower.includes('error') || lower.includes('tidak') || lower.includes('salah') || lower.includes('melebihi')) {
                    icon = 'error';
                    title = 'Perhatian';
                } else if (lower.includes('berhasil') || lower.includes('sukses') || lower.includes('terpilih') || lower.includes('disimpan')) {
                    icon = 'success';
                    title = 'Berhasil';
                } else if (lower.includes('peringatan') || lower.includes('warning') || lower.includes('minimal')) {
                    icon = 'warning';
                    title = 'Peringatan';
                }
                return Swal.fire({
                    title: title,
                    text: String(message),
                    icon: icon,
                    confirmButtonText: 'Mengerti',
                    confirmButtonColor: '#2563eb',
                    background: isDark ? '#1e293b' : '#ffffff',
                    color: isDark ? '#f8fafc' : '#0f172a',
                    customClass: {
                        popup: 'rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700',
                        confirmButton: 'px-4 py-2 rounded-xl text-xs font-semibold'
                    }
                });
            };
        }
    </script>

    <script>
        // Global Fast Native Browser Print Preview Handler
        window.openDocPreview = function(url, title = 'Pratinjau Dokumen') {
            try {
                let targetUrl = new URL(url, window.location.origin);
                targetUrl.searchParams.set('format_mode', 'html');
                targetUrl.searchParams.set('print', '1');

                let printIframe = document.getElementById('nativeDocPrintIframe');
                if (!printIframe) {
                    printIframe = document.createElement('iframe');
                    printIframe.id = 'nativeDocPrintIframe';
                    printIframe.name = 'nativeDocPrintIframe';
                    printIframe.style.position = 'fixed';
                    printIframe.style.top = '-9999px';
                    printIframe.style.left = '-9999px';
                    printIframe.style.width = '1024px';
                    printIframe.style.height = '768px';
                    printIframe.style.opacity = '0';
                    printIframe.style.pointerEvents = 'none';
                    document.body.appendChild(printIframe);
                }

                printIframe.onload = function() {
                    try {
                        setTimeout(function() {
                            printIframe.contentWindow.focus();
                            printIframe.contentWindow.print();
                        }, 120);
                    } catch (e) {
                        window.open(targetUrl.toString(), '_blank');
                    }
                };

                printIframe.src = targetUrl.toString();
            } catch (err) {
                window.open(url, '_blank');
            }
        };
    </script>

    @include('partials.tema')

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        [x-cloak] { display: none !important; }
    </style>

    <style>
        @media print {
            header, nav, footer, #bottom-nav, .no-print, [role="dialog"], .toast-container, .btn-primary, .btn-secondary:not(.print-include) {
                display: none !important;
            }
            body {
                background: #ffffff !important;
                color: #0f172a !important;
                padding-bottom: 0 !important;
            }
            .erp-card {
                background: #ffffff !important;
                color: #0f172a !important;
                border: 1px solid #cbd5e1 !important;
                box-shadow: none !important;
                break-inside: avoid;
                page-break-inside: avoid;
            }
            .dark {
                color-scheme: light !important;
            }
            .print-only {
                display: block !important;
            }
        }
        @media screen {
            .print-only {
                display: none !important;
            }
        }
    </style>
</head>
<body class="bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 min-h-screen flex flex-col transition-colors duration-200 pb-16 md:pb-0">

@php
    $u = auth()->user();

    // Profil sengaja TIDAK ada di daftar ini: tempatnya di dropdown avatar
    // sebelah kanan header, mengikuti pola flustra-erp. Menaruhnya di dua
    // tempat sekaligus membuat pengguna ragu mana yang "benar".
    $navs = [
        ['route' => 'beranda',        'label' => 'Beranda',  'match' => 'beranda'],
        ['route' => 'riwayat.index',  'label' => 'Riwayat',  'match' => 'riwayat.*'],
        ['route' => 'bantuan',        'label' => 'Bantuan',  'match' => 'bantuan'],
    ];
@endphp

<!-- ==================== KONTEN ==================== -->
<div class="flex-1 flex flex-col min-w-0">

    {{-- Header tunggal: logo, menu, lalu perkakas di kanan.
         Tidak ada sidebar — keputusan pemilik produk, lihat CLAUDE.md §6. --}}
    {{-- Header sengaja mentok kiri-kanan, tidak ikut kolom terpusat mana pun
         (ketentuan pemilik produk). --}}
    <header class="sticky top-0 z-40 h-14 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md border-b border-slate-200/80 dark:border-slate-800 flex items-center gap-2 px-4 transition-colors">

        <a href="{{ route('beranda') }}" class="flex items-center gap-2.5 shrink-0 group mr-2">
            <img src="{{ asset('images/flustraa.png') }}" alt="Flustra Logo" class="w-7 h-7 object-contain group-hover:scale-105 transition-transform duration-200 shrink-0">
            <div class="min-w-0 hidden sm:flex flex-col justify-center">
                <span class="text-xs font-bold text-slate-800 dark:text-white truncate leading-tight">Flustra</span>
                <span class="text-[8px] uppercase font-bold tracking-wider text-blue-600 dark:text-blue-400 truncate leading-tight">Client Portal</span>
            </div>
        </a>

        {{-- Menu utama. Di bawah md digantikan bottom nav — deretan mendatar
             tidak muat di lebar 375px tanpa menyusut jadi tidak terbaca. --}}
        <nav class="hidden md:flex items-center gap-1 min-w-0 flex-1">
            @foreach($navs as $nav)
                <a href="{{ route($nav['route']) }}"
                   class="relative px-3 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-all duration-200 {{ request()->routeIs($nav['match']) ? 'bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100/70 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white' }}">
                    {{ $nav['label'] }}
                </a>
            @endforeach
        </nav>

        {{-- Di mobile logo tidak memakan seluruh lebar, jadi perkakas kana        {{-- Pemicu pencarian. Desktop: pill input bar persis Flustra Office --}}
        <div class="hidden sm:flex items-center flex-1 max-w-xs md:max-w-md mx-2 md:mx-4">
            <button @click="bukaCari()"
                    class="w-full flex items-center justify-between px-3.5 py-1.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-400 dark:text-slate-500 rounded-xl hover:bg-slate-100/50 dark:hover:bg-slate-800 transition-all text-xs font-medium focus:outline-none cursor-pointer">
                <div class="flex items-center gap-2 truncate">
                    <svg class="w-4 h-4 text-slate-400 dark:text-slate-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <span class="truncate">Cari pengajuan, dokumen, layanan...</span>
                </div>
            </button>
        </div>

        {{-- Mobile Search Trigger Icon --}}
        <button @click="bukaCari()"
                class="sm:hidden p-2 text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-xl focus:outline-none transition-colors cursor-pointer"
                aria-label="Cari">
            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </button>

        {{-- Right Section: Moon (Dark Mode) -> Bell (Notifications) -> Avatar (Blue circle with initials or avatar image) --}}
        <div class="flex items-center gap-1.5 md:gap-2 shrink-0">

            <!-- Toggle tema (Dark Mode) -->
            <button @click="darkMode = !darkMode"
                    class="p-2 text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-700/60 rounded-xl relative focus:outline-none transition-colors cursor-pointer"
                    aria-label="Ganti tema">
                <svg x-show="darkMode" x-cloak class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z"/>
                </svg>
                <svg x-show="!darkMode" class="w-5 h-5 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                </svg>
            </button>

            <!-- Lonceng notifikasi -->
            <div class="relative">
                <button @click="showNotifyDropdown = !showNotifyDropdown"
                        class="p-2 text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-700/60 rounded-xl relative focus:outline-none transition-colors cursor-pointer"
                        aria-label="Notifikasi">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1h6z"/>
                    </svg>
                    <span x-show="unreadCount > 0" x-cloak
                          class="absolute top-1 right-1 min-w-4 h-4 px-1 rounded-full bg-red-500 text-white text-[9px] font-bold flex items-center justify-center shadow-sm">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                        <span class="relative" x-text="unreadCount > 9 ? '9+' : unreadCount"></span>
                    </span>
                </button>

                <div x-show="showNotifyDropdown" x-cloak @click.outside="showNotifyDropdown = false"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 -translate-y-2"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="absolute right-0 mt-2 w-80 max-w-[90vw] bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-xl overflow-hidden z-50">

                    <div class="px-4 py-2.5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-800 dark:text-white">Notifikasi</span>
                        <button @click="markAllRead()" class="text-[10px] text-blue-500 hover:underline cursor-pointer">Tandai semua</button>
                    </div>

                    <div class="max-h-80 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-700">
                        <template x-for="n in notifications" :key="n.id">
                            <a :href="n.url || '{{ route('notifikasi.index') }}'"
                               class="flex gap-2.5 px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors"
                               :class="!n.is_read ? 'bg-blue-50/50 dark:bg-blue-950/20' : ''">
                                <span class="w-1.5 h-1.5 rounded-full mt-1.5 shrink-0"
                                      :class="n.is_read ? 'bg-slate-300 dark:bg-slate-600' : 'bg-blue-500'"></span>
                                <span class="min-w-0">
                                    <span class="block text-xs font-semibold text-slate-800 dark:text-white" x-text="n.title"></span>
                                    <span class="block text-[11px] text-slate-500 line-clamp-2" x-text="n.body"></span>
                                    <span class="block text-[10px] text-slate-400 mt-0.5" x-text="n.time"></span>
                                </span>
                            </a>
                        </template>
                        <div x-show="notifications.length === 0" class="px-4 py-8 text-center text-xs text-slate-400">
                            Belum ada notifikasi.
                        </div>
                    </div>

                    <a href="{{ route('notifikasi.index') }}"
                       class="block px-4 py-2.5 text-center text-[11px] font-semibold text-blue-500 hover:bg-slate-50 dark:hover:bg-slate-700/50 border-t border-slate-100 dark:border-slate-700">
                        Lihat semua
                    </a>
                </div>
            </div>

            <!-- Profil: Avatar Bulat dengan inisial seperti di Flustra Office -->
            <div class="relative shrink-0" x-data="{ profilBuka: false }">
                <button @click="profilBuka = !profilBuka"
                        class="flex items-center gap-1.5 rounded-full p-0.5 hover:ring-2 hover:ring-blue-500/20 transition-all cursor-pointer focus:outline-none"
                        aria-label="Menu profil">
                    @if($u->avatar && (str_starts_with($u->avatar, 'http') || \Illuminate\Support\Facades\Storage::disk('public')->exists($u->avatar)))
                        <img src="{{ $u->avatar_url }}" alt="{{ $u->name }}"
                             class="w-8 h-8 rounded-full object-cover border border-slate-200 dark:border-slate-700 shadow-sm shrink-0" loading="lazy">
                    @else
                        <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold text-xs shadow-sm uppercase shrink-0">
                            {{ substr($u->name ?? 'U', 0, 2) }}
                        </div>
                    @endif
                </button>

                <div x-show="profilBuka" x-cloak @click.outside="profilBuka = false"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 -translate-y-2"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="absolute right-0 mt-2 w-56 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-xl overflow-hidden z-50">

                    <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700">
                        <p class="text-xs font-semibold text-slate-800 dark:text-white truncate">{{ $u->name }}</p>
                        <p class="text-[10px] text-slate-400 truncate mt-0.5">{{ $u->email }}</p>
                        <span class="inline-block mt-1.5 px-1.5 py-0.5 rounded-full text-[9px] font-bold {{ $u->account_type_color }}">
                            {{ $u->account_type_label }}
                        </span>
                    </div>

                    @if($u?->isAdmin())
                        <a href="{{ route('admin.dashboard') }}"
                           class="block px-4 py-2.5 text-xs font-semibold text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/20 transition-colors">
                            Panel Superadmin
                        </a>
                    @endif

                    <a href="{{ route('profil.edit') }}"
                       class="block px-4 py-2.5 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                        Profil Saya
                    </a>

                    <form action="{{ route('logout') }}" method="POST" class="border-t border-slate-100 dark:border-slate-700">
                        @csrf
                        <button type="submit"
                                class="w-full text-left px-4 py-2.5 text-xs font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/20 transition-colors cursor-pointer">
                            Keluar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <main class="flex-1 p-4 md:p-6">

        {{-- Pesan kilat dan banner ikut kolom yang sama dengan kontennya, kalau
             tidak tepi kirinya tidak pernah bertemu dengan kartu di bawahnya. --}}
        <div class="@yield('lebar', 'max-w-7xl mx-auto') w-full">
            @if(session('success'))
                <div class="erp-card !p-3 mb-4 border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 text-xs font-medium">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="erp-card !p-3 mb-4 border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/40 text-red-700 dark:text-red-400 text-xs font-medium">
                    {{ session('error') }}
                </div>
            @endif

            @include('partials.lihat-sebagai-bar')
            @include('partials.maintenance-banner')
        </div>

        {{-- Lebar konten ditentukan halamannya sendiri lewat @section('lebar').

             Bawaannya kolom terpusat 'max-w-7xl mx-auto'. Ini BUKAN sekadar
             selera: tanpa sidebar, konten yang dibiarkan selebar layar terus
             melar di monitor lebar sampai kolom keempat kartu terpotong di tepi
             kanan — dan karena grid-nya tetap empat kolom, seluruh isinya
             tampak bergeser ke kiri walau tiap kartunya sudah rata tengah.

             HEADER sengaja TIDAK ikut kolom ini; ia tetap mentok kiri-kanan
             (ketentuan pemilik produk). Jangan membungkus header dengan
             `max-w-*`.

             Halaman formulir mempersempitnya lagi jadi 'max-w-2xl mx-auto'. --}}
        <div class="@yield('lebar', 'max-w-7xl mx-auto') w-full">

            {{-- Tombol kembali, di ATAS judul dan rata kiri dengan kartunya —
                 urutan yang sama dengan halaman create di ERP. Muncul hanya bila
                 halamannya menyebutkan tujuannya; halaman daftar tidak punya
                 "kembali ke" yang masuk akal. --}}
            @hasSection('kembali_url')
                <a href="@yield('kembali_url')"
                   class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 transition-colors mb-4">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Kembali ke @yield('kembali_label')
                </a>
            @endif

            {{-- Judul halaman pindah ke sini dari header: tempatnya di header
                 sekarang dipakai menu. --}}
            @hasSection('page_title')
                <div class="mb-5 text-center">
                    <h1 class="text-lg font-bold text-slate-800 dark:text-white">@yield('page_title')</h1>
                    @hasSection('page_subtitle')
                        <p class="text-xs text-slate-400 mt-1">@yield('page_subtitle')</p>
                    @endif
                </div>
            @endif

            @yield('content')
        </div>
    </main>

    <footer class="px-4 py-5 text-center text-[10px] text-slate-400 border-t border-slate-200 dark:border-slate-800">
        &copy; {{ date('Y') }} Flustra &middot;
        <a href="{{ route('syarat') }}" class="hover:text-slate-600 dark:hover:text-slate-300">Syarat</a> &middot;
        <a href="{{ route('privasi') }}" class="hover:text-slate-600 dark:hover:text-slate-300">Privasi</a>
    </footer>
</div>

<!-- ==================== REALTIME TOAST CONTAINER ==================== -->
<div class="fixed top-16 right-4 z-[9999] flex flex-col gap-2 max-w-sm w-full pointer-events-none px-2 sm:px-0">
    <template x-for="t in toasts" :key="t.id">
        <div x-show="t.visible"
             x-transition:enter="transition ease-out duration-300 transform"
             x-transition:enter-start="opacity-0 -translate-y-2 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-200 transform"
             x-transition:leave-start="opacity-100 translate-y-0 scale-100"
             x-transition:leave-end="opacity-0 -translate-y-2 scale-95"
             class="pointer-events-auto bg-slate-900/95 dark:bg-slate-800/95 backdrop-blur-md text-white px-4 py-3 rounded-2xl shadow-2xl border border-white/10 flex items-start gap-3">
            <span class="w-2.5 h-2.5 rounded-full bg-blue-400 mt-1 shrink-0 animate-ping"></span>
            <div class="flex-1 min-w-0">
                <a :href="t.url || '#'" class="block hover:underline">
                    <h5 class="text-xs font-bold text-white truncate" x-text="t.title"></h5>
                    <p class="text-[11px] text-slate-300 line-clamp-2 mt-0.5" x-text="t.body"></p>
                </a>
            </div>
            <button @click="t.visible = false" class="text-slate-400 hover:text-white p-1 -mr-1 -mt-1 cursor-pointer">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </template>
</div>

<!-- ==================== PENCARIAN CEPAT (Ctrl+K) ==================== -->
<div x-show="cariBuka" x-cloak
     @keydown.escape.window="cariBuka = false"
     class="fixed inset-0 z-[60] flex items-start justify-center pt-[12vh] px-4">

    <div @click="cariBuka = false" class="absolute inset-0 bg-slate-900/40 dark:bg-black/60 backdrop-blur-sm"></div>

    <div x-show="cariBuka"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-3 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         class="relative w-full max-w-lg bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl overflow-hidden">

        <div class="flex items-center gap-2.5 px-4 py-3 border-b border-slate-100 dark:border-slate-700">
            <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input x-ref="cariInput" x-model="cariKata" @input="cariBerubah()"
                   @keydown.arrow-down.prevent="cariTurun()"
                   @keydown.arrow-up.prevent="cariNaik()"
                   @keydown.enter.prevent="cariBuka2()"
                   type="text" placeholder="Cari pengajuan atau layanan…"
                   class="flex-1 bg-transparent text-xs text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none">
            <button @click="cariBuka = false" class="text-[9px] font-mono px-1.5 py-0.5 rounded border border-slate-200 dark:border-slate-700 text-slate-400 cursor-pointer">ESC</button>
        </div>

        <div class="max-h-80 overflow-y-auto">
            <template x-for="(h, i) in cariHasil" :key="i">
                <a :href="h.url" @mouseenter="cariPilih = i"
                   class="flex items-start gap-3 px-4 py-2.5 transition-colors"
                   :class="cariPilih === i ? 'bg-blue-50 dark:bg-blue-950/30' : 'hover:bg-slate-50 dark:hover:bg-slate-700/40'">
                    <span class="px-1.5 py-0.5 rounded-full text-[9px] font-bold shrink-0 mt-0.5 bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400"
                          x-text="h.kelompok"></span>
                    <span class="min-w-0">
                        <span class="block text-xs font-semibold text-slate-800 dark:text-white truncate" x-text="h.judul"></span>
                        <span class="block text-[11px] text-slate-500 truncate" x-text="h.ket"></span>
                    </span>
                </a>
            </template>

            <div x-show="cariKata.trim().length >= 2 && cariHasil.length === 0" class="px-4 py-8 text-center text-xs text-slate-400">
                Tidak ada yang cocok dengan &ldquo;<span x-text="cariKata"></span>&rdquo;.
            </div>
            <div x-show="cariKata.trim().length < 2" class="px-4 py-8 text-center text-xs text-slate-400">
                Ketik minimal dua huruf. Cari nomor pengajuan, judulnya, atau nama layanan.
            </div>
        </div>
    </div>
</div>

<!-- ==================== BOTTOM NAV (MOBILE) ==================== -->
<nav class="fixed bottom-0 left-0 right-0 h-16 bg-white dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700 flex md:hidden items-center justify-around z-40 transition-colors duration-200 px-2 shadow-lg">
    @foreach($navs as $nav)
        <a href="{{ route($nav['route']) }}"
           class="relative flex flex-col items-center justify-center gap-0.5 flex-1 h-full {{ request()->routeIs($nav['match']) ? 'text-blue-600 dark:text-blue-400' : 'text-slate-400' }}">
            @include('partials.nav-icon', ['name' => $nav['route'], 'size' => 'w-5 h-5'])
            <span class="text-[9px] font-semibold">{{ $nav['label'] }}</span>
        </a>
    @endforeach
</nav>

</body>
</html>
