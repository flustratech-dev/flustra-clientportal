<!DOCTYPE html>
<html lang="id" x-data="{ 
    darkMode: localStorage.getItem('darkMode') === 'true'
}" :class="{ 'dark': darkMode }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="google" content="notranslate">
    <title x-text="isRegister ? 'Register - Flustra Client Portal' : 'Login - Flustra Client Portal'">Login - Flustra Client Portal</title>
    
    <!-- Google Fonts -->
    {{-- Plus Jakarta Sans dilokalkan di resources/css/layout.css — lihat
         catatannya di sana. Dulu tiga perjalanan ke Google sebelum satu huruf
         pun muncul, di halaman yang paling sering jadi kesan pertama. --}}
    <link rel="preload" href="/fonts/plus-jakarta-sans-variable.woff2" as="font" type="font/woff2" crossorigin>

    <!-- Styles & Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- Prevent Theme White Flash on Initial Page Load -->
    <script>
        if (localStorage.getItem('darkMode') === 'true') {
            document.documentElement.classList.add('dark');
        }
    </script>
    <style>
        html { background-color: #f0f4f9; }
        html.dark { background-color: #090d16; }
        body { background-color: #f0f4f9; font-family: 'Plus Jakarta Sans', sans-serif; }
        html.dark body { background-color: #090d16; }
        [x-cloak] { display: none; }
    </style>
</head>
<body class="bg-[#f0f4f9] dark:bg-[#090d16] text-slate-800 dark:text-slate-100 min-h-screen flex flex-col justify-between transition-colors duration-300 relative overflow-x-hidden">

    <!-- Ambient background glows -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="absolute -top-[20%] -left-[10%] w-[55%] h-[55%] rounded-full bg-blue-400/30 dark:bg-blue-500/15 blur-[100px] animate-glow-1"></div>
        <div class="absolute -bottom-[20%] -right-[10%] w-[55%] h-[55%] rounded-full bg-indigo-500/25 dark:bg-indigo-600/15 blur-[100px] animate-glow-2"></div>
        <div class="absolute top-[30%] left-[40%] w-[35%] h-[35%] rounded-full bg-cyan-400/20 dark:bg-cyan-500/10 blur-[80px] animate-glow-3"></div>
    </div>

    <!-- Header (Brand & Theme Toggle) -->
    <header class="p-3 px-6 flex justify-between items-center relative z-10">
        <a href="{{ route('welcome') }}" class="flex items-center gap-2.5 group">
            <img src="{{ asset('images/flustraa.png') }}" alt="Flustra Logo" class="w-7 h-7 object-contain group-hover:scale-105 transition-transform duration-200">
            <div class="flex flex-col justify-center">
                <span class="text-sm font-bold tracking-tight text-slate-900 dark:text-white leading-none">Flustra</span>
                <span class="text-[8px] uppercase font-bold tracking-wider text-[#3572EF] leading-none mt-0.5">Client Portal</span>
            </div>
        </a>

        <button @click="darkMode = !darkMode; localStorage.setItem('darkMode', darkMode)" 
                class="p-2 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/50 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all duration-200 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer"
                aria-label="Toggle Theme">
            <!-- Sun Icon (Visible in Dark Mode) -->
            <svg x-show="darkMode" class="w-4.5 h-4.5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z" />
            </svg>
            <!-- Moon Icon (Visible in Light Mode) -->
            <svg x-show="!darkMode" class="w-4.5 h-4.5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
            </svg>
        </button>
    </header>

    {{-- Pengumuman gangguan juga tampil di sini.
         Halaman masuk adalah tempat pertama mitra menyadari ada yang salah;
         tanpa pemberitahuan, ia akan mencoba masuk berulang kali dan mengira
         akunnya yang bermasalah. --}}
    <div class="relative z-10 px-5 sm:px-8 max-w-[960px] mx-auto w-full">
        @include('partials.maintenance-banner')
    </div>

    <!-- Main Container -->
    <main class="flex-grow flex items-center justify-center p-3 md:p-5 pb-10 md:pb-14 -mt-4 md:-mt-8 relative z-10"
          x-data="{ isRegister: {{ request()->routeIs('register') || $errors->has('name') || $errors->has('terms') || ($errors->has('password') && old('name')) ? 'true' : 'false' }} }"
          @popstate.window="isRegister = window.location.pathname.includes('/daftar') || window.location.pathname.includes('/register')">
        <div class="auth-card w-full max-w-[960px] bg-white/60 dark:bg-slate-900/50 backdrop-blur-2xl border border-white/40 dark:border-white/10 rounded-[2.25rem] overflow-hidden relative min-h-[550px] md:h-[590px] floating-card">
            
            <!-- 1. LOGIN VIEW -->
            <div x-show="!isRegister" 
                 x-transition:enter="transition ease-out duration-300 transform"
                 x-transition:enter-start="opacity-0 translate-x-[-30px]"
                 x-transition:enter-end="opacity-100 translate-x-0"
                 x-transition:leave="transition ease-in duration-200 transform"
                 x-transition:leave-start="opacity-100 translate-x-0"
                 x-transition:leave-end="opacity-0 translate-x-[30px]"
                 class="w-full h-full flex flex-col md:flex-row absolute inset-0">
                
                <!-- Left Side: Marketing Image -->
                <div class="image-panel hidden md:flex md:w-[50%] relative overflow-hidden bg-cover bg-center select-none flex-col justify-between p-6" style="background-image: url('{{ asset('images/auth_bg.png') }}');">
                    <div class="absolute inset-0 bg-gradient-to-b from-blue-950/60 via-blue-900/20 to-blue-950/70"></div>
                    <div class="relative z-10 pt-2">
                        <div class="inline-flex items-center gap-2 bg-white/15 backdrop-blur-md border border-white/20 rounded-full px-3 py-1 mb-3">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-ping absolute"></span>
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 relative"></span>
                            <span class="text-[9px] font-semibold text-white/90 tracking-wider uppercase ml-1">Platform Aktif</span>
                        </div>
                        <h1 class="text-2xl font-bold text-white leading-snug tracking-tight mb-2">
                            Satu Tempat untuk<br>Semua Kebutuhan<br>Klien Flustra.
                        </h1>
                        <p class="text-xs text-white/70 leading-relaxed max-w-xs">
                            Kelola tagihan, pantau penawaran, konfirmasi pembayaran, dan lacak pengiriman dari satu portal terpadu yang praktis dan transparan.
                        </p>
                    </div>
                    <div class="relative z-10 p-3.5 rounded-2xl bg-white/10 backdrop-blur-xl border border-white/20 shadow-sm">
                        <p class="text-[10px] text-white/80 leading-relaxed font-medium">
                            &copy; {{ date('Y') }} Flustra Client Portal. Hak cipta dilindungi undang-undang.
                        </p>
                    </div>
                </div>

                <!-- Right Side: Login Form -->
                <div class="form-panel w-full md:w-[50%] bg-white/50 dark:bg-slate-900/40 backdrop-blur-xl p-6 md:p-8 flex flex-col justify-between border-b md:border-b-0 md:border-l border-white/40 dark:border-white/10 overflow-y-auto md:overflow-y-hidden">
                    <div class="flex items-center gap-2.5">
                        <img src="{{ asset('images/flustraa.png') }}" alt="Flustra Logo" class="w-8 h-8 object-contain">
                        <div class="flex flex-col justify-center">
                            <span class="text-lg font-bold tracking-tight text-slate-900 dark:text-white leading-none">Flustra</span>
                            <span class="text-[8px] uppercase font-bold tracking-wider text-[#3572EF] leading-none mt-0.5">Client Portal</span>
                        </div>
                    </div>

                    <div class="my-auto py-3">
                        <div class="mb-3">
                            <h2 class="text-xl font-bold text-slate-900 dark:text-white tracking-tight">Selamat Datang Kembali!</h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Masuk untuk melanjutkan ke akun portal Anda</p>
                        </div>

                        <!-- Toggle Pill -->
                        @if(config('portal.registration_open'))
                        <div class="flex bg-slate-100 dark:bg-slate-900/60 p-1 rounded-full border border-slate-200/50 dark:border-slate-800/80 mb-3.5">
                            <button @click.prevent="isRegister = false; history.pushState(null, '', '{{ route('login') }}')" class="flex-1 text-center py-2 text-xs font-semibold rounded-full transition-all duration-300 bg-[#3572EF] text-white shadow-sm cursor-pointer">
                                Masuk
                            </button>
                            <button @click.prevent="isRegister = true; history.pushState(null, '', '{{ route('register') }}')" class="flex-1 text-center py-2 text-xs font-semibold rounded-full transition-all duration-300 text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 cursor-pointer">
                                Daftar
                            </button>
                        </div>
                        @endif

                        <form action="{{ route('login') }}" method="POST" class="space-y-3.5 relative">
                            @csrf

                            <div class="relative">
                                <input type="email" name="email" required placeholder="Masukkan email Anda" value="{{ old('email') }}" autocomplete="email"
                                    class="w-full px-5 py-3 rounded-full bg-slate-100/60 dark:bg-slate-900/60 border border-slate-200/50 dark:border-slate-800/80 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-[#3572EF] focus:bg-white dark:focus:bg-slate-950 transition-all text-xs md:text-sm pr-10">
                            </div>
                            @error('email')
                                <p class="text-xs text-red-500 mt-0.5 ml-4 font-medium">{{ $message }}</p>
                            @enderror

                            <div class="relative" x-data="{ show: false }">
                                <input :type="show ? 'text' : 'password'" name="password" required placeholder="Masukkan kata sandi Anda" autocomplete="current-password"
                                    class="w-full px-5 py-3 rounded-full bg-slate-100/60 dark:bg-slate-900/60 border border-slate-200/50 dark:border-slate-800/80 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-[#3572EF] focus:bg-white dark:focus:bg-slate-950 transition-all text-xs md:text-sm pr-10">
                                <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 cursor-pointer" :aria-label="show ? 'Sembunyikan sandi' : 'Tampilkan sandi'">
                                    <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                    <svg x-show="show" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a10.025 10.025 0 013.3-5.46m4-2.225A6 6 0 0121.75 12.825M15 12a3 3 0 11-6 0 3 3 0 016 0zM3 3l18 18" /></svg>
                                </button>
                            </div>

                            <div class="flex items-center justify-between px-2">
                                <label class="flex items-center cursor-pointer select-none">
                                    <input type="checkbox" name="remember" class="w-4 h-4 text-[#3572EF] rounded bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700">
                                    <span class="ml-2 text-xs font-semibold text-slate-500 dark:text-slate-400">Ingat saya</span>
                                </label>
                                <a href="{{ route('bantuan') }}" class="text-xs font-bold text-[#3572EF] hover:text-blue-600">Butuh Bantuan?</a>
                            </div>

                            <button type="submit" class="w-full py-3 rounded-full bg-[#3572EF] hover:bg-blue-600 text-white font-semibold text-xs md:text-sm transition-all duration-300 shadow-md shadow-blue-500/10 active:scale-[0.98] cursor-pointer">
                                Masuk
                            </button>
                        </form>

                        <div class="relative flex py-2.5 items-center">
                            <div class="flex-grow border-t border-slate-200/60 dark:border-slate-800/80"></div>
                            <span class="flex-shrink mx-3 text-[10px] text-slate-400 font-semibold">ATAU</span>
                            <div class="flex-grow border-t border-slate-200/60 dark:border-slate-800/80"></div>
                        </div>

                        <div class="space-y-2.5">
                            {{-- Muncul hanya bila GOOGLE_CLIENT_ID & SECRET terisi. Di
                                 lingkungan yang belum punya OAuth client, rute
                                 google.redirect membalas 404 — tombol yang sudah pasti
                                 gagal lebih buruk daripada tidak ada tombol. --}}
                            @if($googleAktif ?? false)
                            <a href="{{ route('google.redirect') }}" class="w-full py-2.5 rounded-full bg-white hover:bg-slate-100 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-white border border-slate-200 dark:border-slate-600 font-semibold text-xs md:text-sm flex items-center justify-center gap-2 transition-all shadow-sm dark:shadow-md dark:ring-1 dark:ring-white/10 cursor-pointer">
                                <svg class="w-4 h-4" viewBox="0 0 24 24">
                                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" fill="#FBBC05"/>
                                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                                </svg>
                                Masuk dengan Google
                            </a>
                            @endif

                            <a href="{{ route('welcome') }}" class="w-full py-2.5 rounded-full bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs md:text-sm flex items-center justify-center gap-2 transition-all border border-slate-200/50 dark:border-slate-700 shadow-sm">
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                                Halaman Utama Portal
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. REGISTER VIEW -->
            <div x-show="isRegister" 
                 x-cloak
                 x-transition:enter="transition ease-out duration-300 transform"
                 x-transition:enter-start="opacity-0 translate-x-[30px]"
                 x-transition:enter-end="opacity-100 translate-x-0"
                 x-transition:leave="transition ease-in duration-200 transform"
                 x-transition:leave-start="opacity-100 translate-x-0"
                 x-transition:leave-end="opacity-0 translate-x-[-30px]"
                 class="w-full h-full flex flex-col md:flex-row absolute inset-0">
                
                <!-- Left Side: Register Form -->
                <div class="form-panel w-full md:w-[50%] bg-white/50 dark:bg-slate-900/40 backdrop-blur-xl p-6 md:p-8 flex flex-col justify-between border-b md:border-b-0 md:border-r border-white/40 dark:border-white/10 overflow-y-auto md:overflow-y-hidden">
                    <div class="flex items-center gap-2.5">
                        <img src="{{ asset('images/flustraa.png') }}" alt="Flustra Logo" class="w-8 h-8 object-contain">
                        <div class="flex flex-col justify-center">
                            <span class="text-lg font-bold tracking-tight text-slate-900 dark:text-white leading-none">Flustra</span>
                            <span class="text-[8px] uppercase font-bold tracking-wider text-[#3572EF] leading-none mt-0.5">Client Portal</span>
                        </div>
                    </div>

                    <div class="my-auto py-3">
                        <div class="mb-3">
                            <h2 class="text-xl font-bold text-slate-900 dark:text-white tracking-tight">Buat Akun Anda!</h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Daftarkan akun baru untuk mengakses portal klien</p>
                        </div>

                        <!-- Toggle Pill -->
                        <div class="flex bg-slate-100 dark:bg-slate-900/60 p-1 rounded-full border border-slate-200/50 dark:border-slate-800/80 mb-3">
                            <button @click.prevent="isRegister = false; history.pushState(null, '', '{{ route('login') }}')" class="flex-1 text-center py-2 text-xs font-semibold rounded-full transition-all duration-300 text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 cursor-pointer">
                                Masuk
                            </button>
                            <button @click.prevent="isRegister = true; history.pushState(null, '', '{{ route('register') }}')" class="flex-1 text-center py-2 text-xs font-semibold rounded-full transition-all duration-300 bg-[#3572EF] text-white shadow-sm cursor-pointer">
                                Daftar
                            </button>
                        </div>

                        <form action="{{ route('register') }}" method="POST" class="space-y-2 relative">
                            @csrf
                            
                            <div class="relative">
                                <input type="text" name="name" required placeholder="Nama lengkap Anda" value="{{ old('name') }}"
                                    class="w-full px-4 py-2.5 rounded-full bg-slate-100/60 dark:bg-slate-900/60 border border-slate-200/50 dark:border-slate-800/80 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-[#3572EF] focus:bg-white dark:focus:bg-slate-950 transition-all text-xs pr-10">
                            </div>
                            @error('name')
                                <p class="text-xs text-red-500 mt-0.5 ml-4 font-medium">{{ $message }}</p>
                            @enderror

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <div class="relative">
                                    <input type="email" name="email" required placeholder="Email Anda" value="{{ old('email') }}"
                                        class="w-full px-4 py-2.5 rounded-full bg-slate-100/60 dark:bg-slate-900/60 border border-slate-200/50 dark:border-slate-800/80 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-[#3572EF] focus:bg-white dark:focus:bg-slate-950 transition-all text-xs">
                                </div>
                                <div class="relative">
                                    <input type="text" name="phone" placeholder="Nomor WhatsApp" value="{{ old('phone') }}"
                                        class="w-full px-4 py-2.5 rounded-full bg-slate-100/60 dark:bg-slate-900/60 border border-slate-200/50 dark:border-slate-800/80 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-[#3572EF] focus:bg-white dark:focus:bg-slate-950 transition-all text-xs">
                                </div>
                            </div>
                            @error('email')
                                <p class="text-xs text-red-500 mt-0.5 ml-4 font-medium">{{ $message }}</p>
                            @enderror
                            @error('phone')
                                <p class="text-xs text-red-500 mt-0.5 ml-4 font-medium">{{ $message }}</p>
                            @enderror

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <div class="relative" x-data="{ show: false }">
                                    <input :type="show ? 'text' : 'password'" name="password" required placeholder="Kata Sandi (min. 8)"
                                        class="w-full px-4 py-2.5 rounded-full bg-slate-100/60 dark:bg-slate-900/60 border border-slate-200/50 dark:border-slate-800/80 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-[#3572EF] focus:bg-white dark:focus:bg-slate-950 transition-all text-xs pr-10">
                                    <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 cursor-pointer" :aria-label="show ? 'Sembunyikan sandi' : 'Tampilkan sandi'">
                                        <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                        <svg x-show="show" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a10.025 10.025 0 013.3-5.46m4-2.225A6 6 0 0121.75 12.825M15 12a3 3 0 11-6 0 3 3 0 016 0zM3 3l18 18" /></svg>
                                    </button>
                                </div>
                                <div class="relative" x-data="{ show: false }">
                                    <input :type="show ? 'text' : 'password'" name="password_confirmation" required placeholder="Konfirmasi Sandi"
                                        class="w-full px-4 py-2.5 rounded-full bg-slate-100/60 dark:bg-slate-900/60 border border-slate-200/50 dark:border-slate-800/80 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-[#3572EF] focus:bg-white dark:focus:bg-slate-950 transition-all text-xs pr-10">
                                    <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 cursor-pointer" :aria-label="show ? 'Sembunyikan sandi' : 'Tampilkan sandi'">
                                        <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                        <svg x-show="show" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a10.025 10.025 0 013.3-5.46m4-2.225A6 6 0 0121.75 12.825M15 12a3 3 0 11-6 0 3 3 0 016 0zM3 3l18 18" /></svg>
                                    </button>
                                </div>
                            </div>
                            @error('password')
                                <p class="text-xs text-red-500 mt-0.5 ml-4 font-medium">{{ $message }}</p>
                            @enderror

                            <div class="px-2 py-1">
                                <label class="flex items-start gap-2 text-[11px] text-slate-500 dark:text-slate-400 cursor-pointer select-none">
                                    <input type="checkbox" name="terms" value="1" class="w-4 h-4 text-[#3572EF] rounded bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700 mt-0.5 shrink-0" @checked(old('terms'))>
                                    <span class="leading-tight">
                                        Saya menyetujui
                                        <a href="{{ route('syarat') }}" target="_blank" class="text-[#3572EF] hover:underline font-semibold">Syarat &amp; Ketentuan</a>
                                        dan
                                        <a href="{{ route('privasi') }}" target="_blank" class="text-[#3572EF] hover:underline font-semibold">Kebijakan Privasi</a>.
                                    </span>
                                </label>
                                @error('terms')
                                    <p class="text-xs text-red-500 mt-0.5 ml-4 font-medium">{{ $message }}</p>
                                @enderror
                            </div>

                            <button type="submit" class="w-full py-2.5 rounded-full bg-[#3572EF] hover:bg-blue-600 text-white font-semibold text-xs md:text-sm transition-all duration-300 shadow-md shadow-blue-500/10 active:scale-[0.98] cursor-pointer mt-1">
                                Daftar Sekarang
                            </button>
                        </form>

                        <div class="relative flex py-2 items-center">
                            <div class="flex-grow border-t border-slate-200/60 dark:border-slate-800/80"></div>
                            <span class="flex-shrink mx-3 text-[10px] text-slate-400 font-semibold">ATAU</span>
                            <div class="flex-grow border-t border-slate-200/60 dark:border-slate-800/80"></div>
                        </div>

                        <div class="space-y-2">
                            @if($googleAktif ?? false)
                            <a href="{{ route('google.redirect') }}" class="w-full py-2 rounded-full bg-white hover:bg-slate-100 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-white border border-slate-200 dark:border-slate-600 font-semibold text-xs md:text-sm flex items-center justify-center gap-2 transition-all shadow-sm dark:shadow-md dark:ring-1 dark:ring-white/10 cursor-pointer">
                                <svg class="w-4 h-4" viewBox="0 0 24 24">
                                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" fill="#FBBC05"/>
                                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                                </svg>
                                Daftar dengan Google
                            </a>
                            @endif

                            <a href="{{ route('welcome') }}" class="w-full py-2 rounded-full bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs flex items-center justify-center gap-2 transition-all border border-slate-200/50 dark:border-slate-700 shadow-sm">
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                                Halaman Utama Portal
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Right Side: Marketing Image -->
                <div class="image-panel hidden md:flex md:w-[50%] relative overflow-hidden bg-cover bg-center select-none flex-col justify-between p-6" style="background-image: url('{{ asset('images/auth_bg.png') }}');">
                    <div class="absolute inset-0 bg-gradient-to-b from-blue-950/60 via-blue-900/20 to-blue-950/70"></div>
                    <div class="relative z-10 pt-2">
                        <div class="inline-flex items-center gap-2 bg-white/15 backdrop-blur-md border border-white/20 rounded-full px-3 py-1 mb-3">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-ping absolute"></span>
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 relative"></span>
                            <span class="text-[9px] font-semibold text-white/90 tracking-wider uppercase ml-1">Platform Aktif</span>
                        </div>
                        <h1 class="text-2xl font-bold text-white leading-snug tracking-tight mb-2">
                            Mulai Pengalaman<br>Portal Klien Anda<br>Bersama Kami.
                        </h1>
                        <p class="text-xs text-white/70 leading-relaxed max-w-xs">
                            Daftarkan akun Anda sekarang untuk mempermudah transaksi, memantau penawaran, konfirmasi pembayaran, dan kolaborasi bisnis bersama Flustra.
                        </p>
                    </div>
                    <div class="relative z-10 p-3.5 rounded-2xl bg-white/10 backdrop-blur-xl border border-white/20 shadow-sm">
                        <p class="text-[10px] text-white/80 leading-relaxed font-medium">
                            &copy; {{ date('Y') }} Flustra Client Portal. Hak cipta dilindungi undang-undang.
                        </p>
                    </div>
                </div>

            </div>

        </div>
    </main>

</body>
</html>
