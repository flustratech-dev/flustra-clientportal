@extends('layouts.app')
@section('title', 'Status Pembayaran')
@section('page_title', 'Status Pembayaran')
@section('lebar', 'max-w-5xl mx-auto')

@section('content')
<div class="space-y-5">

    @include('partials.erp-offline')

    @if(!empty($advances))
        <div class="erp-card">
            <h3 class="text-xs font-bold text-slate-800 dark:text-white mb-1">Uang Muka Berjalan</h3>
            <p class="text-[11px] text-slate-500 mb-3">
                Uang muka yang sudah kami bayarkan dan belum sepenuhnya diperhitungkan ke tagihan Anda.
            </p>
            <div class="space-y-2">
                @foreach($advances as $a)
                    <div class="flex flex-wrap items-center justify-between gap-2 p-3 rounded-xl border border-slate-100 dark:border-slate-700">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold text-slate-800 dark:text-white font-mono">{{ $a['advance_number'] }}</p>
                            <p class="text-[11px] text-slate-500">
                                {{ $a['date'] ? \Illuminate\Support\Carbon::parse($a['date'])->format('d M Y') : '—' }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs font-mono font-semibold text-slate-800 dark:text-white">
                                Rp {{ number_format($a['amount'], 0, ',', '.') }}
                            </p>
                            <p class="text-[10px] text-slate-500">
                                terpakai Rp {{ number_format($a['applied_amount'], 0, ',', '.') }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if(empty($bills))
        <div class="erp-card text-center py-14">
            <svg class="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
            </svg>
            <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">
                {{ empty($erpError) ? 'Belum ada tagihan.' : 'Data pembayaran belum bisa ditampilkan.' }}
            </p>
            <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                {{ empty($erpError)
                    ? 'Tagihan yang Anda kirim akan muncul di sini beserta status pembayarannya.'
                    : 'Coba muat ulang halaman ini beberapa saat lagi.' }}
            </p>
            @if(empty($erpError))
                <a href="{{ route('vendor.tagihan.create') }}" class="btn-primary mt-4">Kirim Tagihan</a>
            @endif
        </div>
    @else
        <div class="erp-card !p-0 overflow-x-auto hidden sm:block">
            <table class="w-full whitespace-nowrap text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-400 border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th class="p-4">Nomor Faktur</th>
                        <th class="p-4">Tanggal</th>
                        <th class="p-4">Jatuh Tempo</th>
                        <th class="p-4 text-right">Nilai</th>
                        <th class="p-4 text-right">Dibayar</th>
                        <th class="p-4">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                    @foreach($bills as $b)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="p-4 font-mono text-[11px] text-slate-500">
                                {{ $b['bill_number'] }}
                                @if(!empty($b['has_discrepancy']))
                                    <span class="block text-[10px] text-amber-600 dark:text-amber-400 font-semibold">ada selisih</span>
                                @endif
                            </td>
                            <td class="p-4">{{ $b['bill_date'] ? \Illuminate\Support\Carbon::parse($b['bill_date'])->format('d M Y') : '—' }}</td>
                            <td class="p-4 text-slate-500">{{ $b['due_date'] ? \Illuminate\Support\Carbon::parse($b['due_date'])->format('d M Y') : '—' }}</td>
                            <td class="p-4 text-right font-mono">Rp {{ number_format($b['amount'], 0, ',', '.') }}</td>
                            <td class="p-4 text-right font-mono font-semibold {{ $b['paid_amount'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400' }}">
                                Rp {{ number_format($b['paid_amount'], 0, ',', '.') }}
                            </td>
                            <td class="p-4">
                                @include('layanan.partials.status-badge', ['status' => $b['status'], 'label' => $b['status_label']])
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="space-y-2 sm:hidden">
            @foreach($bills as $b)
                <div class="erp-card">
                    <div class="flex items-start justify-between gap-2 mb-1.5">
                        <span class="text-xs font-mono text-slate-500">{{ $b['bill_number'] }}</span>
                        @include('layanan.partials.status-badge', ['status' => $b['status'], 'label' => $b['status_label']])
                    </div>
                    <p class="text-sm font-bold text-slate-800 dark:text-white font-mono">Rp {{ number_format($b['amount'], 0, ',', '.') }}</p>
                    <p class="text-[11px] text-slate-500">
                        dibayar Rp {{ number_format($b['paid_amount'], 0, ',', '.') }}
                    </p>
                    @if(!empty($b['has_discrepancy']))
                        <p class="text-[10px] text-amber-600 dark:text-amber-400 font-semibold mt-1">Ada selisih terhadap purchase order</p>
                    @endif
                </div>
            @endforeach
        </div>

        @include('layanan.partials.pagination', ['meta' => $meta])
    @endif
</div>
@endsection
