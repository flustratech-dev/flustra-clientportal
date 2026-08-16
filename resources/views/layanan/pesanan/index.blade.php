@extends('layouts.app')
@section('title', 'Pesanan Saya')
@section('page_title', 'Pesanan Saya')

@section('content')
<div class="space-y-5">

    @include('partials.erp-offline')

    @if(empty($orders))
        <div class="erp-card text-center py-14">
            <svg class="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
            <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">
                {{ empty($erpError) ? 'Belum ada pesanan.' : 'Daftar pesanan belum bisa ditampilkan.' }}
            </p>
            <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                {{ empty($erpError)
                    ? 'Pesanan dibuat setelah Anda menyetujui penawaran dari kami.'
                    : 'Coba muat ulang halaman ini beberapa saat lagi.' }}
            </p>
        </div>
    @else
        <div class="erp-card !p-0 overflow-x-auto hidden sm:block">
            <table class="w-full whitespace-nowrap text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-400 border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th class="p-4">Nomor Pesanan</th>
                        <th class="p-4">Tanggal</th>
                        <th class="p-4 text-right">Nilai</th>
                        <th class="p-4">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                    @foreach($orders as $o)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="p-4 font-mono text-[11px] text-slate-500">{{ $o['so_number'] }}</td>
                            <td class="p-4">{{ $o['date'] ? \Illuminate\Support\Carbon::parse($o['date'])->format('d M Y') : '—' }}</td>
                            <td class="p-4 text-right font-mono">Rp {{ number_format($o['grand_total'], 0, ',', '.') }}</td>
                            <td class="p-4">
                                @include('layanan.partials.status-badge', ['status' => $o['status'], 'label' => ucfirst(str_replace('_', ' ', $o['status']))])
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="space-y-2 sm:hidden">
            @foreach($orders as $o)
                <div class="erp-card">
                    <div class="flex items-start justify-between gap-2 mb-1.5">
                        <span class="text-xs font-mono text-slate-500">{{ $o['so_number'] }}</span>
                        @include('layanan.partials.status-badge', ['status' => $o['status'], 'label' => ucfirst(str_replace('_', ' ', $o['status']))])
                    </div>
                    <p class="text-sm font-bold text-slate-800 dark:text-white font-mono">Rp {{ number_format($o['grand_total'], 0, ',', '.') }}</p>
                    <p class="text-[10px] text-slate-400 mt-1">{{ $o['date'] ? \Illuminate\Support\Carbon::parse($o['date'])->format('d M Y') : '—' }}</p>
                </div>
            @endforeach
        </div>

        @include('layanan.partials.pagination', ['meta' => $meta])
    @endif
</div>
@endsection
