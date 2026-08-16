@extends('layouts.app')
@section('title', 'Lacak Pengiriman')
@section('page_title', 'Lacak Pengiriman')
@section('lebar', 'max-w-3xl mx-auto')

@section('content')
<div class="space-y-5 max-w-3xl mx-auto">

    @include('partials.erp-offline')

    @if(empty($deliveries))
        <div class="erp-card text-center py-14">
            <svg class="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zm10 0a2 2 0 11-4 0 2 2 0 014 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1"/>
            </svg>
            <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">
                {{ empty($erpError) ? 'Belum ada pengiriman.' : 'Data pengiriman belum bisa ditampilkan.' }}
            </p>
            <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                {{ empty($erpError)
                    ? 'Begitu pesanan Anda disiapkan, nomor resi dan posisinya akan muncul di sini.'
                    : 'Coba muat ulang halaman ini beberapa saat lagi.' }}
            </p>
        </div>
    @else
        @foreach($deliveries as $d)
            <div class="erp-card">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-mono text-slate-500">{{ $d['do_number'] }}</p>
                        <p class="text-xs text-slate-700 dark:text-slate-300 mt-1 whitespace-normal">{{ $d['shipping_address'] ?: '—' }}</p>
                    </div>
                    @include('layanan.partials.status-badge', ['status' => $d['status'], 'label' => $d['status_label']])
                </div>

                {{-- Empat tahap pengiriman. Yang sudah dilewati diwarnai. --}}
                @php
                    $tahap = ['draft' => 'Disiapkan', 'ready' => 'Siap Kirim', 'shipped' => 'Dalam Pengiriman', 'delivered' => 'Diterima'];
                    $urutan = array_keys($tahap);
                    $posisi = array_search($d['status'], $urutan, true);
                    $posisi = $posisi === false ? -1 : $posisi;
                @endphp

                <div class="flex items-center gap-1 mt-4">
                    @foreach($urutan as $index => $kode)
                        <div class="flex-1">
                            <div class="h-1 rounded-full {{ $index <= $posisi ? 'bg-blue-500' : 'bg-slate-200 dark:bg-slate-700' }}"></div>
                            <p class="text-[9px] mt-1.5 {{ $index <= $posisi ? 'text-blue-600 dark:text-blue-400 font-semibold' : 'text-slate-400' }}">
                                {{ $tahap[$kode] }}
                            </p>
                        </div>
                    @endforeach
                </div>

                @if($d['courier'] || $d['tracking_number'])
                    <div class="grid grid-cols-2 gap-4 mt-4 pt-3 border-t border-slate-100 dark:border-slate-700">
                        <div>
                            <p class="erp-label">Kurir</p>
                            <p class="text-xs text-slate-700 dark:text-slate-300">{{ $d['courier'] ?: '—' }}</p>
                        </div>
                        <div>
                            <p class="erp-label">Nomor Resi</p>
                            <p class="text-xs font-mono text-slate-700 dark:text-slate-300">{{ $d['tracking_number'] ?: '—' }}</p>
                        </div>
                    </div>
                @endif

                @if($d['updated_at'])
                    <p class="text-[10px] text-slate-400 mt-3">
                        Diperbarui {{ \Illuminate\Support\Carbon::parse($d['updated_at'])->format('d M Y H:i') }}
                    </p>
                @endif
            </div>
        @endforeach

        @include('layanan.partials.pagination', ['meta' => $meta])
    @endif
</div>
@endsection
