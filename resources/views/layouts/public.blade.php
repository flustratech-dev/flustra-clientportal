<!DOCTYPE html>
<html lang="id" translate="no" class="notranslate" x-data="{ darkMode: localStorage.getItem('darkMode') === 'true' }" :class="{ 'dark': darkMode }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="google" content="notranslate">
    <title>@yield('title', 'Portal Klien') &middot; Flustra</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script>
        if (localStorage.getItem('darkMode') === 'true') {
            document.documentElement.classList.add('dark');
        }
    </script>
    <style>
        html { background-color: #f0f4f9; }
        html.dark { background-color: #090d16; }
        body { background-color: #f0f4f9; font-family: 'Plus Jakarta Sans', 'Inter', sans-serif; }
        html.dark body { background-color: #090d16; }
        [x-cloak] { display: none; }
    </style>
</head>
<body class="bg-[#f0f4f9] dark:bg-[#090d16] text-slate-800 dark:text-slate-100 min-h-screen flex flex-col transition-colors duration-300 relative overflow-x-hidden">

<div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
    <div class="absolute -top-[20%] -left-[10%] w-[55%] h-[55%] rounded-full bg-blue-400/25 dark:bg-blue-500/12 blur-[100px] animate-glow-1"></div>
    <div class="absolute -bottom-[20%] -right-[10%] w-[55%] h-[55%] rounded-full bg-indigo-500/20 dark:bg-indigo-600/12 blur-[100px] animate-glow-2"></div>
</div>

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
    </div>
</header>

<main class="relative z-10 flex-1 px-5 sm:px-8 py-6 max-w-3xl mx-auto w-full">
    @include('partials.maintenance-banner')

    @yield('content')
</main>

<footer class="relative z-10 px-5 sm:px-8 py-6 border-t border-slate-200/60 dark:border-slate-800 text-center text-[11px] text-slate-500 dark:text-slate-400">
    &copy; {{ date('Y') }} Flustra &middot;
    <a href="{{ route('bantuan') }}" class="hover:text-slate-800 dark:hover:text-slate-200">Bantuan</a> &middot;
    <a href="{{ route('syarat') }}" class="hover:text-slate-800 dark:hover:text-slate-200">Syarat</a> &middot;
    <a href="{{ route('privasi') }}" class="hover:text-slate-800 dark:hover:text-slate-200">Privasi</a>
</footer>

</body>
</html>
