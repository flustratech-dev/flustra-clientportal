@extends('layouts.app')
@section('title', 'Kredit & Plafon')
@section('page_title', 'Kredit & Plafon')
@section('lebar', 'max-w-2xl mx-auto')

@section('content')
<div class="space-y-5 max-w-2xl mx-auto">

    @include('partials.erp-offline')

    @if(empty($s))
        <div class="erp-card text-center py-14">
            <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">Data kredit belum bisa ditampilkan.</p>
            <p class="text-xs text-slate-400 mt-1">Coba muat ulang halaman ini beberapa saat lagi.</p>
        </div>
    @else
        @php
            $limit    = (float) ($s['credit_limit'] ?? 0);
            $terpakai = (float) ($s['credit_used'] ?? 0);
            $sisa     = (float) ($s['available_credit'] ?? 0);
            // -1 di ERP berarti tanpa batas, bukan minus.
            $tanpaBatas = $sisa < 0 || $limit <= 0;
            $persen = $tanpaBatas || $limit <= 0 ? 0 : min(100, round($terpakai / $limit * 100));
        @endphp

        <div class="erp-card">
            <p class="erp-label">Plafon Kredit</p>

            @if($tanpaBatas)
                <p class="text-lg font-bold text-slate-800 dark:text-white mt-1">Tanpa batas</p>
                <p class="text-xs text-slate-500 mt-1">
                    Perusahaan Anda tidak dibatasi plafon kredit. Pembayaran tetap mengikuti jatuh tempo setiap tagihan.
                </p>
            @else
                <p class="text-lg font-bold text-slate-800 dark:text-white font-mono mt-1">
                    Rp {{ number_format($limit, 0, ',', '.') }}
                </p>

                <div class="mt-4">
                    <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                        <div class="h-full rounded-full {{ $persen >= 90 ? 'bg-red-500' : ($persen >= 70 ? 'bg-amber-500' : 'bg-emerald-500') }}"
                             style="width: {{ $persen }}%"></div>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-1.5">{{ $persen }}% terpakai</p>
                </div>

                <div class="grid grid-cols-2 gap-4 mt-4 pt-4 border-t border-slate-100 dark:border-slate-700">
                    <div>
                        <p class="erp-label">Terpakai</p>
                        <p class="text-xs font-mono font-semibold text-slate-800 dark:text-white">
                            Rp {{ number_format($terpakai, 0, ',', '.') }}
                        </p>
                    </div>
                    <div>
                        <p class="erp-label">Sisa Plafon</p>
                        <p class="text-xs font-mono font-semibold {{ $sisa <= 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                            Rp {{ number_format(max(0, $sisa), 0, ',', '.') }}
                        </p>
                    </div>
                </div>
            @endif
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="erp-card">
                <p class="erp-label">Tagihan Terbuka</p>
                <p class="text-lg font-bold text-slate-800 dark:text-white mt-1">{{ $s['open_invoice_count'] ?? 0 }}</p>
                <p class="text-[11px] text-slate-500 font-mono mt-0.5">
                    Rp {{ number_format((float) ($s['open_invoice_amount'] ?? 0), 0, ',', '.') }}
                </p>
            </div>
            <div class="erp-card">
                <p class="erp-label">Lewat Jatuh Tempo</p>
                <p class="text-lg font-bold {{ ($s['overdue_count'] ?? 0) > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-800 dark:text-white' }} mt-1">
                    {{ $s['overdue_count'] ?? 0 }}
                </p>
                <p class="text-[11px] text-slate-500 mt-0.5">tagihan</p>
            </div>
            <div class="erp-card">
                <p class="erp-label">Penawaran Menunggu</p>
                <p class="text-lg font-bold text-slate-800 dark:text-white mt-1">{{ $s['pending_quotations'] ?? 0 }}</p>
                <p class="text-[11px] text-slate-500 mt-0.5">butuh keputusan Anda</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('layanan.tagihan.index') }}" class="btn-secondary">Lihat Tagihan</a>
            @if(($s['pending_quotations'] ?? 0) > 0)
                <a href="{{ route('layanan.penawaran.index') }}" class="btn-primary">Lihat Penawaran</a>
            @endif
        </div>

        <p class="text-[10px] text-slate-400">
            Plafon kredit ditetapkan tim kami. Untuk membicarakan penyesuaiannya, hubungi kami lewat halaman
            <a href="{{ route('bantuan') }}" class="text-blue-500 hover:underline">Bantuan</a>.
        </p>
    @endif
</div>
@endsection
