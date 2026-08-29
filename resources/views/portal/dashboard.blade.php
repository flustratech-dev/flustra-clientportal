@extends('layouts.app')
@section('title', 'Beranda')
@section('lebar', 'max-w-6xl mx-auto')

@section('content')

<div class="space-y-8 py-2">

    {{-- ==================== 1. HERO & PARTNER IDENTITY ==================== --}}
    @php
        $activeLink = $u->activeLink();
        $companyName = $activeLink?->company_name;
    @endphp

    <div class="p-6 sm:p-7 rounded-3xl bg-gradient-to-br from-slate-900 via-slate-800 to-blue-950 text-white shadow-xl relative overflow-hidden transition-all duration-300 border border-slate-700/50">
        {{-- Exact glows matching Flustra Office welcome banner --}}
        <div class="absolute -right-16 -top-16 w-56 h-56 rounded-full bg-blue-500/15 blur-2xl pointer-events-none"></div>
        <div class="absolute -left-16 -bottom-16 w-56 h-56 rounded-full bg-indigo-500/15 blur-2xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="flex items-center gap-4 sm:gap-5">
                <div class="relative shrink-0">
                    <img src="{{ $u->avatar_url }}" alt="{{ $u->name }}"
                         class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl object-cover border-2 border-white/20 shadow-md">
                    @if($u->isMitra())
                        <span class="absolute -bottom-1 -right-1 w-5 h-5 bg-emerald-500 text-white rounded-full flex items-center justify-center text-[10px] shadow-sm ring-2 ring-slate-900" title="Mitra Terverifikasi">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                            </svg>
                        </span>
                    @endif
                </div>

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-1.5">
                        <h1 class="text-xl sm:text-2xl font-bold text-white tracking-tight">
                            Halo, {{ $u->name }}!
                        </h1>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold tracking-wide bg-blue-500/20 text-blue-300 border border-blue-400/20">
                            <span class="w-1.5 h-1.5 rounded-full bg-blue-400"></span>
                            {{ $u->account_type_label }}
                        </span>
                    </div>

                    @if($companyName)
                        <p class="text-xs sm:text-sm font-semibold text-blue-200 flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-blue-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            <span class="truncate">{{ $companyName }}</span>
                        </p>
                    @else
                        <p class="text-xs text-slate-300 leading-relaxed max-w-xl">
                            Kelola pengajuan, pantau dokumen, dan tinjau status transaksi Anda di Flustra Client Portal.
                        </p>
                    @endif
                </div>
            </div>

            {{-- Quick action CTA buttons --}}
            <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                @if($u->isUmum() && ! $u->isAdmin() && ! $pendingClaim)
                    <a href="{{ route('mitra.create') }}" class="inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-xl transition-all shadow-md active:scale-95">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Ajukan Kerja Sama
                    </a>
                @elseif($u->isPelanggan())
                    <a href="{{ route('layanan.pembayaran.create') }}" class="inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-xl transition-all shadow-md active:scale-95">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/>
                        </svg>
                        Konfirmasi Pembayaran
                    </a>
                @elseif($u->isVendor())
                    <a href="{{ route('vendor.tagihan.create') }}" class="inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-xl transition-all shadow-md active:scale-95">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/>
                        </svg>
                        Kirim Tagihan
                    </a>
                @endif
                <a href="{{ route('riwayat.index') }}" class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 bg-white/10 hover:bg-white/20 text-white border border-white/15 backdrop-blur-sm text-xs font-semibold rounded-xl transition-all active:scale-95">
                    Riwayat Pengajuan
                </a>
            </div>
        </div>
    </div>

    {{-- ==================== 2. SMART ACTION BANNERS ==================== --}}
    @if($pendingClaim)
        <div class="erp-card !p-4 md:!p-5 border-amber-200 dark:border-amber-800/80 bg-amber-50/70 dark:bg-amber-950/20 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex items-start gap-3">
                <span class="p-2 rounded-xl bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </span>
                <p class="text-xs sm:text-sm text-amber-800 dark:text-amber-300 leading-relaxed text-left">
                    <strong class="font-bold">Pengajuan kerja sama Anda sedang diperiksa.</strong><br class="hidden sm:inline">
                    Kami akan mengabari begitu tim selesai mencocokkan data {{ $pendingClaim->company_name }}.
                </p>
            </div>
            <a href="{{ route('riwayat.index') }}" class="btn-secondary shrink-0 whitespace-nowrap">Lihat status</a>
        </div>
    @elseif($u->isUmum() && $rejectedClaim)
        <div class="erp-card !p-4 md:!p-5 border-red-200 dark:border-red-800/80 bg-red-50/70 dark:bg-red-950/20 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex items-start gap-3">
                <span class="p-2 rounded-xl bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400 shrink-0 mt-0.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </span>
                <p class="text-xs sm:text-sm text-red-800 dark:text-red-300 leading-relaxed text-left">
                    <strong class="font-bold">Pengajuan sebelumnya belum bisa kami setujui.</strong><br class="hidden sm:inline">
                    {{ $rejectedClaim->rejected_reason ?: 'Silakan periksa kembali bukti yang Anda lampirkan.' }}
                    Anda tetap bisa mengajukan lagi.
                </p>
            </div>
            <a href="{{ route('mitra.create') }}" class="btn-primary shrink-0 whitespace-nowrap">Ajukan ulang</a>
        </div>
    @elseif($u->isUmum() && ! $u->isAdmin())
        <div class="erp-card !p-4 md:!p-5 border-blue-200 dark:border-blue-800/80 bg-blue-50/70 dark:bg-blue-950/20 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex items-start gap-3">
                <span class="p-2 rounded-xl bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </span>
                <p class="text-xs sm:text-sm text-blue-800 dark:text-blue-300 leading-relaxed text-left">
                    <strong class="font-bold">Buka layanan penuh.</strong><br class="hidden sm:inline">
                    Ajukan verifikasi sebagai pelanggan atau vendor untuk melihat tagihan, penawaran, dan pengiriman Anda.
                </p>
            </div>
            <a href="{{ route('mitra.create') }}" class="btn-primary shrink-0 whitespace-nowrap">Ajukan Kerja Sama</a>
        </div>
    @endif

    {{-- Verifikasi email --}}
    @unless($u->email_verified_at)
        <div class="erp-card !p-4 md:!p-5 border-amber-200 dark:border-amber-800/80 bg-amber-50/70 dark:bg-amber-950/20 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex items-start gap-3">
                <span class="p-2 rounded-xl bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </span>
                <p class="text-xs sm:text-sm text-amber-800 dark:text-amber-300 leading-relaxed text-left">
                    <strong class="font-bold">Verifikasi email Anda.</strong><br class="hidden sm:inline">
                    Kami perlu memastikan {{ $u->email }} benar milik Anda sebelum pengajuan kerja sama bisa diproses.
                </p>
            </div>
            <form action="{{ route('verifikasi.kirim') }}" method="POST" class="shrink-0">
                @csrf
                <button type="submit" class="btn-primary whitespace-nowrap">Kirim Tautan Verifikasi</button>
            </form>
        </div>
    @endunless

    {{-- ==================== 3. METRICS OVERVIEW ==================== --}}
    @if($stats['total'] > 0)
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5 sm:gap-4">
        @foreach([
            ['Sedang Diproses', $stats['diproses'], 'text-amber-500 dark:text-amber-400', 'bg-amber-500/10 dark:bg-amber-400/10', 'Menunggu respon tim'],
            ['Disetujui Bulan Ini', $stats['disetujui'], 'text-emerald-500 dark:text-emerald-400', 'bg-emerald-500/10 dark:bg-emerald-400/10', 'Pengajuan selesai'],
            ['Ditolak', $stats['ditolak'], 'text-red-500 dark:text-red-400', 'bg-red-500/10 dark:bg-red-400/10', 'Perlu perbaikan data'],
            ['Total Pengajuan', $stats['total'], 'text-slate-800 dark:text-slate-100', 'bg-slate-500/10 dark:bg-slate-400/10', 'Seluruh riwayat'],
        ] as [$label, $angka, $warna, $bgWarna, $subLabel])
            <div class="erp-card !p-4 sm:!p-5 relative overflow-hidden transition-all duration-300 hover:border-slate-300 dark:hover:border-slate-600">
                <div class="flex items-center justify-between gap-2 mb-2">
                    <p class="erp-label !mb-0 text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ $label }}</p>
                    <span class="w-2 h-2 rounded-full {{ $bgWarna }}"></span>
                </div>
                <p class="text-2xl sm:text-3xl font-bold tracking-tight {{ $warna }}">{{ $angka }}</p>
                <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1 truncate">{{ $subLabel }}</p>
            </div>
        @endforeach
    </div>
    @endif

    {{-- ==================== 4. SERVICE CATALOG HUB ==================== --}}
    @foreach($services as $group => $items)
        <div class="pt-2">
            <div class="flex items-center gap-2 mb-4">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $group }}</h2>
                <span class="px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-[10px] font-semibold text-slate-500 dark:text-slate-400">
                    {{ count($items) }} Layanan
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach($items as $item)
                    @php
                        $terkunci = $item['locked'];
                        $segera   = ! $terkunci && $item['route'] === null;
                        $aktif    = ! $terkunci && ! $segera;
                    @endphp

                    <div x-data="{ pesan: false }" class="relative">
                        <a @if($aktif) href="{{ route($item['route']) }}" @else href="#" @click.prevent="pesan = true" @endif
                           class="erp-card service-hub-card relative flex flex-col justify-between h-full p-5 transition-all text-left group {{ $aktif ? 'hover:border-blue-400 dark:hover:border-blue-600' : 'opacity-60 cursor-help bg-slate-50/50 dark:bg-slate-800/40' }}">

                            {{-- Top Row: Icon + Status Badge --}}
                            <div class="flex items-start justify-between gap-3 mb-4">
                                <span class="w-10 h-10 rounded-xl inline-flex items-center justify-center shrink-0 transition-transform duration-200 {{ $aktif ? 'bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 group-hover:scale-110 shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-400' }}">
                                    @include('partials.service-icon', ['name' => $item['icon']])
                                </span>

                                @if($terkunci)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-400 text-[10px] font-medium" title="Terkunci - Butuh Verifikasi">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                        </svg>
                                    </span>
                                @elseif($segera)
                                    <span class="px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 text-[9px] font-bold tracking-wider uppercase">
                                        Segera
                                    </span>
                                @else
                                    <span class="w-6 h-6 rounded-full inline-flex items-center justify-center text-slate-300 dark:text-slate-600 group-hover:text-blue-500 group-hover:translate-x-0.5 transition-all">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </span>
                                @endif
                            </div>

                            {{-- Middle Content: Title & Description --}}
                            <div class="flex-1">
                                <h3 class="text-xs font-bold text-slate-800 dark:text-white leading-snug group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                                    {{ $item['title'] }}
                                </h3>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed line-clamp-2">
                                    {{ $item['desc'] }}
                                </p>
                            </div>
                        </a>

                        {{-- Kartu terkunci diberi modal penjelasan --}}
                        <div x-show="pesan" x-cloak @click.outside="pesan = false"
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             class="absolute inset-0 z-20 rounded-2xl bg-white/95 dark:bg-slate-800/95 backdrop-blur-md border border-blue-300 dark:border-blue-600 shadow-2xl p-4 flex flex-col justify-center gap-2.5">
                            @if($terkunci)
                                <div class="w-8 h-8 rounded-full bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 inline-flex items-center justify-center mx-auto">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                    </svg>
                                </div>
                                <p class="text-[11px] text-slate-600 dark:text-slate-300 text-center leading-relaxed">
                                    Layanan ini terbuka setelah diverifikasi sebagai
                                    <strong class="text-slate-800 dark:text-white">{{ in_array('pelanggan', $item['for'], true) && in_array('vendor', $item['for'], true) ? 'Pelanggan atau Vendor' : (in_array('pelanggan', $item['for'], true) ? 'Pelanggan' : 'Vendor') }}</strong>.
                                </p>
                                <a href="{{ route('mitra.create') }}" class="btn-primary !py-1.5 w-full justify-center text-xs">
                                    Ajukan Verifikasi
                                </a>
                            @else
                                <p class="text-[11px] text-slate-600 dark:text-slate-300 text-center leading-relaxed">
                                    Layanan ini sedang disiapkan dan akan segera tersedia di portal.
                                </p>
                            @endif
                            <button @click="pesan = false" class="btn-secondary !py-1.5 w-full justify-center text-xs">
                                Tutup
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    {{-- ==================== 5. RECENT ACTIVITY FEED ==================== --}}
    @if($recent->isNotEmpty())
    <div class="pt-4">
        <div class="flex items-center justify-between mb-3 px-1">
            <div class="flex items-center gap-2">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Aktivitas Terakhir</h2>
                <span class="px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-[10px] font-semibold text-slate-500 dark:text-slate-400">
                    5 Terbaru
                </span>
            </div>
            <a href="{{ route('riwayat.index') }}" class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline inline-flex items-center gap-1">
                Lihat semua
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>

        <div class="erp-card !p-0 divide-y divide-slate-100 dark:divide-slate-700/60 overflow-hidden shadow-sm">
            @foreach($recent as $s)
                <a href="{{ route('riwayat.show', $s) }}"
                   class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-5 py-3.5 hover:bg-slate-50/80 dark:hover:bg-slate-700/30 transition-colors group">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 inline-flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold text-slate-800 dark:text-white truncate group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                                {{ $s->title }}
                            </p>
                            <p class="text-[10px] text-slate-400 font-mono mt-0.5">
                                {{ $s->reference_number }} &middot; {{ $s->submitted_at?->diffForHumans() ?? 'Baru saja' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center justify-between sm:justify-end gap-2 shrink-0">
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold {{ $s->status_color }}">
                            {{ $s->status_label }}
                        </span>
                        <svg class="w-4 h-4 text-slate-300 dark:text-slate-600 group-hover:text-slate-500 dark:group-hover:text-slate-400 group-hover:translate-x-0.5 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
    @endif

</div>
@endsection
