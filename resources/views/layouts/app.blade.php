<!DOCTYPE html>
<html lang="id" translate="no" class="notranslate" x-data="{
    darkMode: localStorage.getItem('darkMode') === 'true',
    mobileMenuOpen: false,
    showNotifyDropdown: false,
    unreadCount: 0,
    notifications: [],
    pollInterval: null,

    init() {
        this.$watch('darkMode', val => {
            localStorage.setItem('darkMode', val);
            document.documentElement.classList.toggle('dark', val);
        });
        document.documentElement.classList.toggle('dark', this.darkMode);

        this.pollNotifications();
        this.startPolling();

        // Hentikan polling saat tab tidak aktif — tidak ada gunanya menembak
        // server tiap 30 detik untuk tab yang tidak dilihat siapa pun.
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.stopPolling();
            } else {
                this.pollNotifications();
                this.startPolling();
            }
        });
    },

    startPolling() {
        if (!this.pollInterval) {
            this.pollInterval = setInterval(() => this.pollNotifications(), 30000);
        }
    },

    stopPolling() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
            this.pollInterval = null;
        }
    },

    pollNotifications() {
        fetch('{{ route('notifikasi.poll') }}', {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(r => r.ok ? r.json() : null)
        .then(data => {
            if (!data) return;
            this.unreadCount = data.unread;
            this.notifications = data.items;
        })
        .catch(() => {});
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
      @keydown.window.ctrl.k.prevent="bukaCari()"
      @keydown.window.meta.k.prevent="bukaCari()">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    {{-- Wajib: auto-translate Chrome menulis ulang teks di dalam atribut x-data
         (mengecilkan huruf kunci, mengubah "/" jadi spasi), yang merusak JSON
         inline dan mematikan seluruh komponen Alpine di halaman ini. --}}
    <meta name="google" content="notranslate">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Beranda') &middot; {{ config('app.name') }}</title>

    <script src="{{ asset('vendor/sweetalert2/sweetalert2.all.min.js') }}"></script>

    {{-- Cegah kedip putih saat halaman dimuat ulang dalam mode gelap. Harus
         berjalan SEBELUM @vite, sebelum ada apa pun yang tergambar. --}}
    <script>
        if (localStorage.getItem('darkMode') === 'true') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 min-h-screen flex flex-col md:flex-row transition-colors duration-200 pb-16 md:pb-0">

@php
    $u = auth()->user();
    $navs = [
        ['route' => 'beranda',        'label' => 'Beranda',  'match' => 'beranda'],
        ['route' => 'riwayat.index',  'label' => 'Riwayat',  'match' => 'riwayat.*'],
        ['route' => 'notifikasi.index','label' => 'Notifikasi','match' => 'notifikasi.*'],
        ['route' => 'profil.edit',    'label' => 'Profil',   'match' => 'profil.*'],
        ['route' => 'bantuan',        'label' => 'Bantuan',  'match' => 'bantuan'],
    ];
@endphp

<!-- ==================== SIDEBAR (DESKTOP) ==================== -->
<aside class="fixed inset-y-0 left-0 z-50 w-52 bg-white dark:bg-slate-800 border-r border-slate-200 dark:border-slate-700 hidden md:flex flex-col transition-colors">

    <a href="{{ route('beranda') }}" class="h-14 flex items-center gap-2.5 px-4 border-b border-slate-200 dark:border-slate-700 shrink-0 group">
        <img src="{{ asset('images/flustraa.png') }}" alt="Flustra Logo" class="w-7 h-7 object-contain group-hover:scale-105 transition-transform duration-200 shrink-0">
        <div class="min-w-0 flex flex-col justify-center">
            <span class="text-xs font-bold text-slate-800 dark:text-white truncate leading-tight">Flustra</span>
            <span class="text-[8px] uppercase font-bold tracking-wider text-[#3572EF] truncate leading-tight">Client Portal</span>
        </div>
    </a>

    <nav id="sidebar-nav" class="flex-1 min-h-0 px-3 py-3 space-y-0.5 overflow-y-auto">
        @foreach($navs as $nav)
            <a href="{{ route($nav['route']) }}"
               class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium transition-all {{ request()->routeIs($nav['match']) ? 'bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-slate-900 dark:hover:text-white' }}">
                @include('partials.nav-icon', ['name' => $nav['route']])
                {{ $nav['label'] }}
                @if($nav['route'] === 'notifikasi.index')
                    <span x-show="unreadCount > 0" x-cloak
                          class="ml-auto px-1.5 py-0.5 rounded-full bg-blue-500 text-white text-[9px] font-bold"
                          x-text="unreadCount"></span>
                @endif
            </a>
        @endforeach
    </nav>

    <div class="p-3 border-t border-slate-200 dark:border-slate-700 shrink-0">
        <div class="flex items-center gap-2 mb-2">
            <img src="{{ $u->avatar_url }}" alt="" class="w-7 h-7 rounded-full object-cover shrink-0">
            <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold text-slate-800 dark:text-white truncate leading-tight">{{ $u->name }}</p>
                <span class="inline-block px-1.5 py-0.5 rounded-full text-[9px] font-bold {{ $u->account_type_color }}">
                    {{ $u->account_type_label }}
                </span>
            </div>
        </div>
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="btn-secondary w-full justify-center">Keluar</button>
        </form>
    </div>
</aside>

<!-- ==================== KONTEN ==================== -->
<div class="flex-1 md:ml-52 flex flex-col min-w-0">

    <!-- Header -->
    <header class="sticky top-0 z-40 h-14 bg-white/90 dark:bg-slate-800/90 backdrop-blur border-b border-slate-200 dark:border-slate-700 flex items-center gap-3 px-4 transition-colors">
        <h2 class="text-sm font-semibold text-slate-800 dark:text-white truncate flex-1">@yield('page_title', 'Portal Klien')</h2>

        {{-- Pemicu pencarian. Di mobile hanya ikon: bilah lengkap memakan
             lebar yang lebih dibutuhkan judul halaman. --}}
        <button @click="bukaCari()"
                class="flex items-center gap-2 px-2.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-400 hover:border-slate-300 dark:hover:border-slate-600 transition-colors cursor-pointer"
                aria-label="Cari">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <span class="hidden lg:inline text-[11px]">Cari…</span>
            <kbd class="hidden lg:inline text-[9px] font-mono px-1 py-0.5 rounded border border-slate-200 dark:border-slate-700">Ctrl K</kbd>
        </button>

        <!-- Lonceng notifikasi -->
        <div class="relative">
            <button @click="showNotifyDropdown = !showNotifyDropdown"
                    class="relative p-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors cursor-pointer"
                    aria-label="Notifikasi">
                <svg class="w-4.5 h-4.5 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1h6z"/>
                </svg>
                <span x-show="unreadCount > 0" x-cloak
                      class="absolute top-1 right-1 min-w-4 h-4 px-1 rounded-full bg-red-500 text-white text-[9px] font-bold flex items-center justify-center"
                      x-text="unreadCount > 9 ? '9+' : unreadCount"></span>
            </button>

            <div x-show="showNotifyDropdown" x-cloak @click.outside="showNotifyDropdown = false"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="absolute right-0 mt-2 w-80 max-w-[90vw] bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-xl overflow-hidden">

                <div class="flex items-center justify-between px-4 py-2.5 border-b border-slate-100 dark:border-slate-700">
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

        <!-- Toggle tema -->
        <button @click="darkMode = !darkMode"
                class="p-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors cursor-pointer"
                aria-label="Ganti tema">
            <svg x-show="darkMode" x-cloak class="w-4.5 h-4.5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z"/>
            </svg>
            <svg x-show="!darkMode" class="w-4.5 h-4.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
            </svg>
        </button>
    </header>

    <!-- Breadcrumb -->
    @hasSection('breadcrumb_title')
    <div class="px-4 pt-4 text-[11px] text-slate-400 flex items-center gap-1.5">
        <a href="{{ route('beranda') }}" class="hover:text-slate-600 dark:hover:text-slate-300">Beranda</a>
        <span>/</span>
        <span class="text-slate-800 dark:text-slate-200 font-semibold">@yield('breadcrumb_title')</span>
    </div>
    @endif

    <main class="flex-1 p-4">
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

        @yield('content')
    </main>

    <footer class="px-4 py-5 text-center text-[10px] text-slate-400 border-t border-slate-200 dark:border-slate-800">
        &copy; {{ date('Y') }} Flustra &middot;
        <a href="{{ route('syarat') }}" class="hover:text-slate-600 dark:hover:text-slate-300">Syarat</a> &middot;
        <a href="{{ route('privasi') }}" class="hover:text-slate-600 dark:hover:text-slate-300">Privasi</a>
    </footer>
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
            @if($nav['route'] === 'notifikasi.index')
                <span x-show="unreadCount > 0" x-cloak
                      class="absolute top-2 right-1/4 min-w-3.5 h-3.5 px-1 rounded-full bg-red-500 text-white text-[8px] font-bold flex items-center justify-center"
                      x-text="unreadCount > 9 ? '9+' : unreadCount"></span>
            @endif
        </a>
    @endforeach
</nav>

</body>
</html>
