@extends('layouts.app')
@section('title', 'Beranda')
@section('lebar', 'w-full')

@section('content')

<div class="space-y-8 py-2">

    {{-- ==================== 1. HERO BENTO SHOWCASE & PARTNER IDENTITY ==================== --}}
    @php
        $activeLink = $u->activeLink();
        $companyName = $activeLink?->company_name;
    @endphp

    <div class="p-6 sm:p-8 lg:p-10 rounded-3xl bg-gradient-to-br from-slate-950 via-slate-900 to-blue-950 text-white shadow-2xl relative overflow-hidden transition-all duration-300 border border-slate-700/60">
        {{-- Background Ambient Glows matching Flustra Office welcome banner --}}
        <div class="absolute -right-20 -top-20 w-80 h-80 rounded-full bg-blue-500/15 blur-3xl pointer-events-none"></div>
        <div class="absolute -left-20 -bottom-20 w-80 h-80 rounded-full bg-indigo-500/15 blur-3xl pointer-events-none"></div>
        <div class="absolute right-1/3 top-1/2 -translate-y-1/2 w-64 h-64 rounded-full bg-sky-500/10 blur-3xl pointer-events-none"></div>
        <div class="absolute inset-0 bg-[radial-gradient(#38bdf8_1px,transparent_1px)] [background-size:24px_24px] opacity-[0.04] pointer-events-none"></div>

        <div class="relative z-10 grid grid-cols-1 lg:grid-cols-12 gap-8 items-stretch">
            
            {{-- Left Column: Identity, Greeting & Relevant Action (Span 7) --}}
            <div class="lg:col-span-7 flex flex-col justify-center space-y-5">
                <div class="flex items-start sm:items-center gap-4 sm:gap-5">
                    <div class="relative shrink-0">
                        <img src="{{ $u->avatar_url }}" alt="{{ $u->name }}"
                             class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl object-cover ring-4 ring-white/15 shadow-xl">
                        @if($u->isMitra())
                            <span class="absolute -bottom-1.5 -right-1.5 w-6 h-6 bg-emerald-500 text-white rounded-full flex items-center justify-center text-xs shadow-md ring-2 ring-slate-900" title="Mitra Terverifikasi">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                </svg>
                            </span>
                        @endif
                    </div>

                    <div class="min-w-0">
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight leading-tight">
                            Halo, {{ $u->name }}!
                        </h1>

                        @if($companyName)
                            <div class="mt-2 inline-flex items-center gap-2 px-3 py-1 rounded-xl bg-white/10 border border-white/15 text-xs sm:text-sm font-semibold text-blue-200 backdrop-blur-sm max-w-full">
                                <svg class="w-4 h-4 text-blue-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                                <span class="truncate">{{ $companyName }}</span>
                                @if($u->isMitra())
                                    <span class="shrink-0 text-[10px] font-bold px-1.5 py-0.5 rounded bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">Terverifikasi</span>
                                @endif
                            </div>
                        @else
                            <p class="text-xs sm:text-sm text-slate-300 mt-2 leading-relaxed max-w-xl">
                                Kelola pengajuan, pantau dokumen bisnis, dan tinjau status transaksi Anda secara terpadu di Flustra Client Portal.
                            </p>
                        @endif
                    </div>
                </div>

                {{-- Action CTA (Hanya aksi utama yang benar-benar dibutuhkan) --}}
                @if($u->isUmum() && ! $u->isAdmin() && ! $pendingClaim)
                    <div class="pt-1">
                        <a href="{{ route('mitra.create') }}" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-xl transition-all shadow-lg shadow-blue-600/30 active:scale-95">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            Ajukan Kerja Sama
                        </a>
                    </div>
                @elseif($u->isPelanggan())
                    <div class="pt-1">
                        <a href="{{ route('layanan.pembayaran.create') }}" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-xl transition-all shadow-lg shadow-blue-600/30 active:scale-95">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/>
                            </svg>
                            Konfirmasi Pembayaran
                        </a>
                    </div>
                @elseif($u->isVendor())
                    <div class="pt-1">
                        <a href="{{ route('vendor.tagihan.create') }}" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-xl transition-all shadow-lg shadow-blue-600/30 active:scale-95">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/>
                            </svg>
                            Kirim Tagihan
                        </a>
                    </div>
                @elseif($u->isAdmin())
                    <div class="pt-1 flex flex-wrap items-center gap-2.5">
                        <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-xl transition-all shadow-lg shadow-blue-600/30 active:scale-95">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            Panel Admin
                        </a>
                        <a href="{{ route('admin.lihat-sebagai') }}" class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 bg-white/10 hover:bg-white/20 text-white border border-white/15 backdrop-blur-sm text-xs font-semibold rounded-xl transition-all active:scale-95">
                            Lihat Sebagai Mitra
                        </a>
                    </div>
                @endif
            </div>

            {{-- Right Column: Integrated Frosted Widget & Metrics / Status (Span 5) --}}
            <div class="lg:col-span-5 flex flex-col justify-between">
                <div class="h-full rounded-2xl bg-white/[0.06] border border-white/15 backdrop-blur-md p-5 sm:p-6 flex flex-col justify-between shadow-xl relative overflow-hidden">
                    <div class="absolute -right-8 -top-8 w-28 h-28 rounded-full bg-blue-400/10 blur-xl pointer-events-none"></div>

                    <div>
                        <div class="flex items-center justify-between pb-3.5 border-b border-white/10 mb-4">
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-200">
                                    {{ $u->isAdmin() ? 'Status Sistem Portal' : ($stats['total'] > 0 ? 'Ringkasan Pengajuan' : 'Status Kemitraan') }}
                                </h3>
                            </div>
                            @if(! $u->isAdmin())
                                <a href="{{ route('riwayat.index') }}" class="text-[11px] font-semibold text-blue-300 hover:text-blue-200 inline-flex items-center gap-1 transition-colors">
                                    Detail
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </a>
                            @endif
                        </div>

                        @if($u->isAdmin())
                            <div class="space-y-2.5">
                                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-900/60 border border-white/10 text-xs">
                                    <div class="flex items-center gap-2.5">
                                        <span class="w-5 h-5 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-[11px] font-bold">✓</span>
                                        <span class="text-slate-200 font-medium">Hak Akses Penuh</span>
                                    </div>
                                    <span class="text-[10px] text-emerald-400 font-semibold">Administrator</span>
                                </div>
                                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-900/60 border border-white/10 text-xs">
                                    <div class="flex items-center gap-2.5">
                                        <span class="w-5 h-5 rounded-full bg-blue-500/20 text-blue-400 flex items-center justify-center text-[11px] font-bold">✓</span>
                                        <span class="text-slate-200 font-medium">Semua Modul Layanan</span>
                                    </div>
                                    <span class="text-[10px] text-blue-400 font-semibold">Terbuka</span>
                                </div>
                                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-900/60 border border-white/10 text-xs">
                                    <div class="flex items-center gap-2.5">
                                        <span class="w-5 h-5 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-[11px] font-bold">✓</span>
                                        <span class="text-slate-200 font-medium">Koneksi ERP Flustra</span>
                                    </div>
                                    <span class="text-[10px] text-emerald-400 font-semibold">Tersinkronisasi</span>
                                </div>
                            </div>
                        @elseif($stats['total'] > 0)
                            <div class="grid grid-cols-2 gap-2.5">
                                <div class="p-3 rounded-xl bg-slate-900/60 border border-white/10 hover:border-white/20 transition-all">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Diproses</span>
                                        <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                                    </div>
                                    <p class="text-xl sm:text-2xl font-bold text-amber-400">{{ $stats['diproses'] }}</p>
                                    <p class="text-[10px] text-slate-400 truncate mt-0.5">Menunggu tim</p>
                                </div>

                                <div class="p-3 rounded-xl bg-slate-900/60 border border-white/10 hover:border-white/20 transition-all">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Disetujui</span>
                                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                    </div>
                                    <p class="text-xl sm:text-2xl font-bold text-emerald-400">{{ $stats['disetujui'] }}</p>
                                    <p class="text-[10px] text-slate-400 truncate mt-0.5">Bulan ini</p>
                                </div>

                                <div class="p-3 rounded-xl bg-slate-900/60 border border-white/10 hover:border-white/20 transition-all">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Ditolak</span>
                                        <span class="w-2 h-2 rounded-full bg-red-400"></span>
                                    </div>
                                    <p class="text-xl sm:text-2xl font-bold text-red-400">{{ $stats['ditolak'] }}</p>
                                    <p class="text-[10px] text-slate-400 truncate mt-0.5">Perlu perbaikan</p>
                                </div>

                                <div class="p-3 rounded-xl bg-slate-900/60 border border-white/10 hover:border-white/20 transition-all">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total</span>
                                        <span class="w-2 h-2 rounded-full bg-blue-400"></span>
                                    </div>
                                    <p class="text-xl sm:text-2xl font-bold text-white">{{ $stats['total'] }}</p>
                                    <p class="text-[10px] text-slate-400 truncate mt-0.5">Semua riwayat</p>
                                </div>
                            </div>
                        @else
                            <div class="space-y-2.5">
                                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-900/60 border border-white/10 text-xs">
                                    <div class="flex items-center gap-2.5">
                                        <span class="w-5 h-5 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-[11px] font-bold">✓</span>
                                        <span class="text-slate-200 font-medium">Akun Portal Terdaftar</span>
                                    </div>
                                    <span class="text-[10px] text-emerald-400 font-semibold">Aktif</span>
                                </div>

                                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-900/60 border border-white/10 text-xs">
                                    <div class="flex items-center gap-2.5">
                                        <span class="w-5 h-5 rounded-full {{ $u->isMitra() ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400' }} flex items-center justify-center text-[11px] font-bold">
                                            {{ $u->isMitra() ? '✓' : '•' }}
                                        </span>
                                        <span class="text-slate-200 font-medium">Verifikasi Mitra Bisnis</span>
                                    </div>
                                    <span class="text-[10px] font-semibold {{ $u->isMitra() ? 'text-emerald-400' : 'text-amber-400' }}">
                                        {{ $u->isMitra() ? 'Terverifikasi' : 'Belum Terhubung' }}
                                    </span>
                                </div>

                                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-900/60 border border-white/10 text-xs">
                                    <div class="flex items-center gap-2.5">
                                        <span class="w-5 h-5 rounded-full {{ $u->isMitra() ? 'bg-blue-500/20 text-blue-400' : 'bg-slate-700 text-slate-400' }} flex items-center justify-center text-[11px] font-bold">
                                            {{ $u->isMitra() ? '✓' : '•' }}
                                        </span>
                                        <span class="text-slate-200 font-medium">Sinkronisasi Dokumen ERP</span>
                                    </div>
                                    <span class="text-[10px] font-semibold {{ $u->isMitra() ? 'text-blue-400' : 'text-slate-400' }}">
                                        {{ $u->isMitra() ? 'Siap Digunakan' : 'Perlu Verifikasi' }}
                                    </span>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="mt-4 pt-3 border-t border-white/10 flex items-center justify-between text-[11px] text-slate-300">
                        <span class="flex items-center gap-1.5 text-slate-300">
                            <svg class="w-3.5 h-3.5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            Sinkronisasi ERP Aktif
                        </span>
                        <a href="{{ route('bantuan') }}" class="text-slate-400 hover:text-slate-200 transition-colors">
                            Bantuan & FAQ
                        </a>
                    </div>
                </div>
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

    {{-- ==================== 3. SERVICE CATALOG HUB ==================== --}}
    <div x-data="{ activeCategory: 'all' }" class="space-y-8 pt-2">
        <div class="flex flex-col items-center justify-center gap-3 pb-3 border-b border-slate-200/80 dark:border-slate-800 text-center">
            <div>
                <h2 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white tracking-tight">Katalog Layanan Portal</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Modul mandiri untuk transaksi, penawaran, dan pemantauan dokumen.</p>
            </div>

            {{-- Filter Tabs (Centered) --}}
            <div class="flex items-center justify-center gap-1.5 flex-wrap py-1">
                <button type="button" @click="activeCategory = 'all'"
                        :class="activeCategory === 'all' ? 'bg-blue-600 text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700'"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-all cursor-pointer">
                    Semua ({{ $services->flatten(1)->count() }})
                </button>
                @foreach($services as $group => $items)
                    <button type="button" @click="activeCategory = '{{ \Illuminate\Support\Str::slug($group) }}'"
                            :class="activeCategory === '{{ \Illuminate\Support\Str::slug($group) }}' ? 'bg-blue-600 text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700'"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-all cursor-pointer">
                        {{ $group }}
                        <span class="ml-1 opacity-75">({{ count($items) }})</span>
                    </button>
                @endforeach
            </div>
        </div>

        @foreach($services as $group => $items)
            <div x-show="activeCategory === 'all' || activeCategory === '{{ \Illuminate\Support\Str::slug($group) }}'"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="space-y-4">
                
                {{-- Group Header (Centered with sleek divider lines) --}}
                <div class="flex items-center justify-center gap-3">
                    <div class="h-px bg-slate-200 dark:bg-slate-800 flex-1 max-w-xs"></div>
                    <div class="flex items-center gap-2 shrink-0">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $group }}</h3>
                        <span class="px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-[10px] font-semibold text-slate-500 dark:text-slate-400">
                            {{ count($items) }} Layanan
                        </span>
                    </div>
                    <div class="h-px bg-slate-200 dark:bg-slate-800 flex-1 max-w-xs"></div>
                </div>

                {{-- Cards Container: Centered flex layout --}}
                <div class="flex flex-wrap justify-center gap-4">
                    @foreach($items as $item)
                        @php
                            $terkunci = $item['locked'];
                            $segera   = ! $terkunci && $item['route'] === null;
                            $aktif    = ! $terkunci && ! $segera;
                        @endphp

                        <div x-data="{ pesan: false }" class="relative w-full sm:w-[calc(50%-0.6rem)] md:w-[calc(33.333%-0.75rem)] lg:w-[calc(25%-0.8rem)] xl:w-[calc(20%-0.85rem)] min-w-[210px] max-w-[280px] flex">
                            <a @if($aktif) href="{{ route($item['route']) }}" @else href="#" @click.prevent="pesan = true" @endif
                               class="erp-card service-hub-card relative flex flex-col items-center justify-between text-center w-full h-full p-5 sm:p-6 transition-all group {{ $aktif ? 'hover:border-blue-400 dark:hover:border-blue-600 hover:shadow-lg hover:-translate-y-1' : 'opacity-60 cursor-help bg-slate-50/50 dark:bg-slate-800/40' }}">

                                {{-- Top: Centered Icon & Status --}}
                                <div class="flex flex-col items-center gap-2 mb-3.5 w-full">
                                    <span class="w-12 h-12 rounded-2xl inline-flex items-center justify-center shrink-0 transition-all duration-300 {{ $aktif ? 'bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 group-hover:scale-110 group-hover:bg-blue-600 group-hover:text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-400' }}">
                                        @include('partials.service-icon', ['name' => $item['icon']])
                                    </span>

                                    @if($terkunci)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 text-[10px] font-medium" title="Terkunci - Butuh Verifikasi">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                            </svg>
                                            Terkunci
                                        </span>
                                    @elseif($segera)
                                        <span class="px-2.5 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 text-[9px] font-bold tracking-wider uppercase">
                                            Segera
                                        </span>
                                    @endif
                                </div>

                                {{-- Middle Content: Centered Title & Description --}}
                                <div class="flex-1 flex flex-col items-center justify-center w-full">
                                    <h3 class="text-xs sm:text-sm font-bold text-slate-800 dark:text-white leading-snug group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors text-center">
                                        {{ $item['title'] }}
                                    </h3>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed text-center line-clamp-2">
                                        {{ $item['desc'] }}
                                    </p>
                                </div>
                            </a>

                            {{-- Kartu terkunci diberi modal penjelasan --}}
                            <div x-show="pesan" x-cloak @click.outside="pesan = false"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 scale-95"
                                 x-transition:enter-end="opacity-100 scale-100"
                                 class="absolute inset-0 z-20 rounded-2xl bg-white/95 dark:bg-slate-800/95 backdrop-blur-md border border-blue-300 dark:border-blue-600 shadow-2xl p-4 flex flex-col justify-center items-center text-center gap-2.5">
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
    </div>

    {{-- ==================== 4. RECENT ACTIVITY FEED ==================== --}}
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
