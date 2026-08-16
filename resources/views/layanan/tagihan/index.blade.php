@extends('layouts.app')
@section('title', 'Tagihan Saya')
@section('page_title', 'Tagihan Saya')
@section('lebar', 'max-w-5xl mx-auto')

@section('content')
<div class="space-y-5">

    @include('partials.erp-offline')

    <form method="GET" class="flex flex-wrap gap-2">
        <select name="status" class="erp-input !w-auto">
            <option value="">Semua Status</option>
            @foreach(['sent' => 'Terkirim', 'partial' => 'Dibayar Sebagian', 'overdue' => 'Jatuh Tempo', 'paid' => 'Lunas'] as $key => $label)
                <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn-secondary">Filter</button>
        @if(request('status'))
            <a href="{{ route('layanan.tagihan.index') }}" class="btn-secondary">Reset</a>
        @endif
    </form>

    @if(empty($invoices))
        <div class="erp-card text-center py-14">
            <svg class="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">
                {{ empty($erpError) ? 'Belum ada tagihan.' : 'Daftar tagihan belum bisa ditampilkan.' }}
            </p>
            <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                {{ empty($erpError)
                    ? 'Tagihan yang kami terbitkan untuk perusahaan Anda akan muncul di sini.'
                    : 'Coba muat ulang halaman ini beberapa saat lagi.' }}
            </p>
        </div>
    @else

        {{-- Tabel: desktop --}}
        <div class="erp-card !p-0 overflow-x-auto hidden sm:block">
            <table class="w-full whitespace-nowrap text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-400 border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th class="p-4">Nomor</th>
                        <th class="p-4">Tanggal</th>
                        <th class="p-4">Jatuh Tempo</th>
                        <th class="p-4 text-right">Total</th>
                        <th class="p-4 text-right">Sisa</th>
                        <th class="p-4">Status</th>
                        <th class="p-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                    @foreach($invoices as $i)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="p-4 font-mono text-[11px] text-slate-500">{{ $i['invoice_number'] }}</td>
                            <td class="p-4">{{ $i['invoice_date'] ? \Illuminate\Support\Carbon::parse($i['invoice_date'])->format('d M Y') : '—' }}</td>
                            <td class="p-4 {{ !empty($i['is_overdue']) ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-slate-500' }}">
                                {{ $i['due_date'] ? \Illuminate\Support\Carbon::parse($i['due_date'])->format('d M Y') : '—' }}
                            </td>
                            <td class="p-4 text-right font-mono">Rp {{ number_format($i['grand_total'], 0, ',', '.') }}</td>
                            <td class="p-4 text-right font-mono font-semibold text-slate-800 dark:text-white">
                                Rp {{ number_format($i['remaining_amount'], 0, ',', '.') }}
                            </td>
                            <td class="p-4">
                                @include('layanan.partials.status-badge', ['status' => $i['status'], 'label' => $i['status_label'] ?? $i['status']])
                            </td>
                            <td class="p-4 flex gap-1.5">
                                <a href="{{ route('layanan.tagihan.show', $i['id']) }}" class="btn-secondary !py-1">Detail</a>
                                @if($i['remaining_amount'] > 0)
                                    <a href="{{ route('layanan.pembayaran.create', ['invoice' => $i['id']]) }}" class="btn-primary !py-1">Bayar</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Kartu bertumpuk: mobile --}}
        <div class="space-y-2 sm:hidden">
            @foreach($invoices as $i)
                <div class="erp-card">
                    <div class="flex items-start justify-between gap-2 mb-1.5">
                        <span class="text-xs font-mono text-slate-500">{{ $i['invoice_number'] }}</span>
                        @include('layanan.partials.status-badge', ['status' => $i['status'], 'label' => $i['status_label'] ?? $i['status']])
                    </div>
                    <p class="text-sm font-bold text-slate-800 dark:text-white">Rp {{ number_format($i['remaining_amount'], 0, ',', '.') }}</p>
                    <p class="text-[11px] text-slate-500">
                        dari total Rp {{ number_format($i['grand_total'], 0, ',', '.') }}
                    </p>
                    <div class="flex items-center justify-between gap-2 mt-2 pt-2 border-t border-slate-100 dark:border-slate-700">
                        <span class="text-[10px] {{ !empty($i['is_overdue']) ? 'text-red-500 font-semibold' : 'text-slate-400' }}">
                            Jatuh tempo {{ $i['due_date'] ? \Illuminate\Support\Carbon::parse($i['due_date'])->format('d M Y') : '—' }}
                        </span>
                        <div class="flex gap-1.5">
                            <a href="{{ route('layanan.tagihan.show', $i['id']) }}" class="btn-secondary !py-1">Detail</a>
                            @if($i['remaining_amount'] > 0)
                                <a href="{{ route('layanan.pembayaran.create', ['invoice' => $i['id']]) }}" class="btn-primary !py-1">Bayar</a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @include('layanan.partials.pagination', ['meta' => $meta])
    @endif
</div>
@endsection
