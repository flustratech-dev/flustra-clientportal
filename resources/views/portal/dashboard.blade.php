@extends('layouts.app')
@section('title', 'Beranda')
@section('lebar', 'max-w-6xl mx-auto')
{{-- Sengaja tanpa @section('page_title'): halaman ini punya sapaan sendiri
     ("Halo, nama") di bawah, dan layout akan menambahkan judul kedua. --}}

@section('content')

<div class="space-y-10 py-2">

    {{-- Sapaan (Rata Kiri) --}}
    <div class="flex items-center gap-2.5">
        <h1 class="text-xl md:text-2xl font-bold text-slate-800 dark:text-white">Halo, {{ $u->name }}</h1>
        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold {{ $u->account_type_color }}">
            {{ $u->account_type_label }}
        </span>
    </div>

    {{-- Banner aksi: lebar penuh dengan teks di kiri dan tombol di kanan --}}
    @if($pendingClaim)
        <div class="erp-card !p-4 md:!p-5 border-amber-200 dark:border-amber-800/80 bg-amber-50/70 dark:bg-amber-950/20 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <p class="text-xs sm:text-sm text-amber-700 dark:text-amber-400 leading-relaxed text-left">
                <strong>Pengajuan kerja sama Anda sedang diperiksa.</strong>
                Kami akan mengabari begitu tim selesai mencocokkan data {{ $pendingClaim->company_name }}.
            </p>
            <a href="{{ route('riwayat.index') }}" class="btn-secondary shrink-0 whitespace-nowrap">Lihat status</a>
        </div>
    @elseif($u->isUmum() && $rejectedClaim)
        <div class="erp-card !p-4 md:!p-5 border-red-200 dark:border-red-800/80 bg-red-50/70 dark:bg-red-950/20 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <p class="text-xs sm:text-sm text-red-700 dark:text-red-400 leading-relaxed text-left">
                <strong>Pengajuan sebelumnya belum bisa kami setujui.</strong>
                {{ $rejectedClaim->rejected_reason ?: 'Silakan periksa kembali bukti yang Anda lampirkan.' }}
                Anda tetap bisa mengajukan lagi.
            </p>
            <a href="{{ route('mitra.create') }}" class="btn-primary shrink-0 whitespace-nowrap">Ajukan ulang</a>
        </div>
    @elseif($u->isUmum() && ! $u->isAdmin())
        <div class="erp-card !p-4 md:!p-5 border-blue-200 dark:border-blue-800/80 bg-blue-50/70 dark:bg-blue-950/20 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <p class="text-xs sm:text-sm text-blue-700 dark:text-blue-400 leading-relaxed text-left">
                <strong class="font-bold">Buka layanan penuh.</strong>
                Ajukan verifikasi sebagai pelanggan atau vendor untuk melihat tagihan, penawaran, dan pengiriman Anda.
            </p>
            <a href="{{ route('mitra.create') }}" class="btn-primary shrink-0 whitespace-nowrap">Ajukan Kerja Sama</a>
        </div>
    @endif

    {{-- Verifikasi email --}}
    @unless($u->email_verified_at)
        <div class="erp-card !p-4 md:!p-5 border-amber-200 dark:border-amber-800/80 bg-amber-50/70 dark:bg-amber-950/20 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <p class="text-xs sm:text-sm text-amber-700 dark:text-amber-400 leading-relaxed text-left">
                <strong>Verifikasi email Anda.</strong>
                Kami perlu memastikan {{ $u->email }} benar milik Anda sebelum pengajuan kerja sama bisa diproses.
            </p>
            <form action="{{ route('verifikasi.kirim') }}" method="POST" class="shrink-0">
                @csrf
                <button type="submit" class="btn-primary whitespace-nowrap">Kirim Tautan Verifikasi</button>
            </form>
        </div>
    @endunless

    {{-- Ringkasan --}}
    @if($stats['total'] > 0)
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach([
            ['Sedang Diproses', $stats['diproses'], 'text-amber-500'],
            ['Disetujui Bulan Ini', $stats['disetujui'], 'text-emerald-500'],
            ['Ditolak', $stats['ditolak'], 'text-red-500'],
            ['Total Pengajuan', $stats['total'], 'text-slate-700 dark:text-slate-200'],
        ] as [$label, $angka, $warna])
            <div class="erp-card text-center p-4">
                <p class="erp-label !mb-1.5">{{ $label }}</p>
                <p class="text-2xl font-bold {{ $warna }}">{{ $angka }}</p>
            </div>
        @endforeach
    </div>
    @endif

    {{-- Grid layanan --}}
    @foreach($services as $group => $items)
        <div class="pt-2">
            <h2 class="erp-label !text-xs font-bold uppercase tracking-wider text-center text-slate-400 dark:text-slate-500 mb-6">{{ $group }}</h2>

            <div class="flex flex-wrap justify-center gap-4">
                @foreach($items as $item)
                    @php
                        $terkunci = $item['locked'];
                        $segera   = ! $terkunci && $item['route'] === null;
                        $aktif    = ! $terkunci && ! $segera;
                    @endphp

                    <div x-data="{ pesan: false }" class="relative w-full sm:w-[calc(50%-0.5rem)] lg:w-[calc(25%-0.75rem)] max-w-xs">
                        <a @if($aktif) href="{{ route($item['route']) }}" @else href="#" @click.prevent="pesan = true" @endif
                           class="erp-card relative block h-full text-center p-5 transition-all {{ $aktif ? 'floating-card hover:border-blue-300 dark:hover:border-blue-700' : 'opacity-60 cursor-help' }}">

                            {{-- Lencana dipojokkan secara absolut, bukan didudukkan
                                 sebaris dengan ikon: kalau sebaris, kartu yang punya
                                 lencana menggeser ikonnya keluar dari tengah dan
                                 barisan ikon jadi tidak lurus. --}}
                            @if($terkunci)
                                <svg class="absolute top-3.5 right-3.5 w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            @elseif($segera)
                                <span class="absolute top-3.5 right-3.5 px-1.5 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 text-[9px] font-bold">SEGERA</span>
                            @endif

                            <span class="w-9 h-9 rounded-xl inline-flex items-center justify-center mx-auto mb-2.5 shrink-0 {{ $aktif ? 'bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400' : 'bg-slate-100 dark:bg-slate-800 text-slate-400' }}">
                                @include('partials.service-icon', ['name' => $item['icon']])
                            </span>

                            <h3 class="text-xs font-bold text-slate-800 dark:text-white leading-snug">{{ $item['title'] }}</h3>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed">{{ $item['desc'] }}</p>
                        </a>

                        {{-- Kartu terkunci diberi penjelasan, bukan langsung 403 --}}
                        <div x-show="pesan" x-cloak @click.outside="pesan = false"
                             x-transition.opacity
                             class="absolute inset-0 z-10 rounded-2xl bg-white dark:bg-slate-800 border border-blue-300 dark:border-blue-700 shadow-xl p-4 flex flex-col justify-center gap-2">
                            @if($terkunci)
                                <p class="text-[11px] text-slate-600 dark:text-slate-300 leading-relaxed">
                                    Layanan ini terbuka setelah Anda terverifikasi sebagai
                                    <strong>{{ in_array('pelanggan', $item['for'], true) && in_array('vendor', $item['for'], true) ? 'pelanggan atau vendor' : (in_array('pelanggan', $item['for'], true) ? 'pelanggan' : 'vendor') }}</strong>.
                                </p>
                                <a href="{{ route('mitra.create') }}" class="btn-primary w-full justify-center">Ajukan Verifikasi</a>
                            @else
                                <p class="text-[11px] text-slate-600 dark:text-slate-300 leading-relaxed">
                                    Layanan ini sedang kami siapkan dan akan segera tersedia di portal.
                                </p>
                            @endif
                            <button @click="pesan = false" class="btn-secondary w-full justify-center">Tutup</button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    {{-- Aktivitas terakhir --}}
    @if($recent->isNotEmpty())
    <div class="pt-2">
        <div class="flex flex-col items-center gap-1 mb-3">
            <h2 class="erp-label !text-xs font-bold uppercase tracking-wider text-center text-slate-400 dark:text-slate-500 !mb-0">Aktivitas Terakhir</h2>
            <a href="{{ route('riwayat.index') }}" class="text-[11px] text-blue-500 hover:underline">Lihat semua</a>
        </div>

        <div class="erp-card !p-0 divide-y divide-slate-100 dark:divide-slate-800">
            @foreach($recent as $s)
                <a href="{{ route('riwayat.show', $s) }}"
                   class="flex flex-wrap items-center justify-center gap-2 px-4 py-3.5 hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                    <span class="text-xs font-semibold text-slate-800 dark:text-white truncate">{{ $s->title }}</span>
                    <span class="text-[10px] text-slate-400 font-mono">{{ $s->reference_number }}</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $s->status_color }}">{{ $s->status_label }}</span>
                </a>
            @endforeach
        </div>
    </div>
    @endif

</div>
@endsection
