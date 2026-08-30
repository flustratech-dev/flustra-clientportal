@extends('layouts.app')
@section('title', 'Rincian Tagihan')
@section('page_title', 'Rincian Tagihan')
@section('lebar', 'max-w-3xl mx-auto')
@section('kembali_url', route('layanan.tagihan.index'))
@section('kembali_label', 'Tagihan')

@section('content')
<div class="space-y-5">

    @include('partials.erp-offline')

    @if(!empty($invoice))
        <div class="erp-card">
            <div class="flex flex-wrap items-start justify-between gap-3 pb-4 border-b border-slate-100 dark:border-slate-700">
                <div>
                    <p class="erp-label">Nomor Tagihan</p>
                    <p class="text-sm font-bold text-slate-800 dark:text-white font-mono">{{ $invoice['invoice_number'] }}</p>
                </div>
                @include('layanan.partials.status-badge', ['status' => $invoice['status'], 'label' => $invoice['status_label'] ?? $invoice['status']])
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 py-4 border-b border-slate-100 dark:border-slate-700">
                <div>
                    <p class="erp-label">Tanggal</p>
                    <p class="text-xs text-slate-700 dark:text-slate-300">
                        {{ $invoice['invoice_date'] ? \Illuminate\Support\Carbon::parse($invoice['invoice_date'])->format('d M Y') : '—' }}
                    </p>
                </div>
                <div>
                    <p class="erp-label">Jatuh Tempo</p>
                    <p class="text-xs {{ !empty($invoice['is_overdue']) ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-slate-700 dark:text-slate-300' }}">
                        {{ $invoice['due_date'] ? \Illuminate\Support\Carbon::parse($invoice['due_date'])->format('d M Y') : '—' }}
                    </p>
                </div>
                <div>
                    <p class="erp-label">Sudah Dibayar</p>
                    <p class="text-xs font-mono text-slate-700 dark:text-slate-300">Rp {{ number_format($invoice['paid_amount'], 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="erp-label">Sisa Tagihan</p>
                    <p class="text-xs font-mono font-bold text-slate-900 dark:text-white">Rp {{ number_format($invoice['remaining_amount'], 0, ',', '.') }}</p>
                </div>
            </div>

            @if(!empty($invoice['items']))
                <div class="py-4 overflow-x-auto">
                    <table class="w-full text-left text-xs whitespace-nowrap">
                        <thead class="text-slate-400 border-b border-slate-100 dark:border-slate-700">
                            <tr>
                                <th class="py-2">Barang / Jasa</th>
                                <th class="py-2 text-right">Jumlah</th>
                                <th class="py-2 text-right">Harga</th>
                                <th class="py-2 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                            @foreach($invoice['items'] as $item)
                                <tr>
                                    <td class="py-2 whitespace-normal">{{ $item['description'] }}</td>
                                    <td class="py-2 text-right font-mono">{{ rtrim(rtrim(number_format($item['quantity'], 2, ',', '.'), '0'), ',') }} {{ $item['unit'] ?? '' }}</td>
                                    <td class="py-2 text-right font-mono">Rp {{ number_format($item['unit_price'], 0, ',', '.') }}</td>
                                    <td class="py-2 text-right font-mono">Rp {{ number_format($item['subtotal'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="pt-4 border-t border-slate-100 dark:border-slate-700 space-y-1 text-xs">
                <div class="flex justify-between text-slate-500">
                    <span>Subtotal</span><span class="font-mono">Rp {{ number_format($invoice['subtotal'], 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between text-slate-500">
                    <span>Pajak</span><span class="font-mono">Rp {{ number_format($invoice['tax'], 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between text-sm font-bold text-slate-900 dark:text-white pt-1">
                    <span>Total</span><span class="font-mono">Rp {{ number_format($invoice['grand_total'], 0, ',', '.') }}</span>
                </div>
            </div>

            @if(!empty($invoice['notes']))
                <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700">
                    <p class="erp-label">Catatan</p>
                    <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">{{ $invoice['notes'] }}</p>
                </div>
            @endif
        </div>

        {{-- Konfirmasi pembayaran yang pernah dikirim untuk tagihan ini --}}
        @if(!empty($invoice['payment_confirmations']))
            <div class="erp-card">
                <h3 class="text-xs font-bold text-slate-800 dark:text-white mb-3">Konfirmasi Pembayaran Anda</h3>
                <div class="space-y-2">
                    @foreach($invoice['payment_confirmations'] as $p)
                        <div class="flex flex-wrap items-center justify-between gap-2 p-3 rounded-xl border border-slate-100 dark:border-slate-700">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-slate-800 dark:text-white font-mono">
                                    Rp {{ number_format($p['amount'], 0, ',', '.') }}
                                </p>
                                <p class="text-[11px] text-slate-500">
                                    {{ $p['payment_date'] ? \Illuminate\Support\Carbon::parse($p['payment_date'])->format('d M Y') : '—' }}
                                </p>
                            </div>
                            @include('layanan.partials.status-badge', [
                                'status' => $p['status'],
                                'label'  => match($p['status']) {
                                    'pending'  => 'Menunggu Verifikasi',
                                    'verified' => 'Terverifikasi',
                                    'rejected' => 'Ditolak',
                                    default    => $p['status'],
                                },
                            ])
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('layanan.tagihan.index') }}" class="btn-secondary">Kembali</a>
            <a href="{{ route('layanan.tagihan.pdf', $invoice['id']) }}" target="_blank" rel="noopener" class="btn-secondary inline-flex items-center gap-2">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span>Unduh Faktur PDF Resmi</span>
            </a>
            @if($invoice['remaining_amount'] > 0)
                <a href="{{ route('layanan.pembayaran.create', ['invoice' => $invoice['id']]) }}" class="btn-primary">Konfirmasi Pembayaran</a>
            @endif
            @if(!empty($invoice['items']))
                <a href="{{ route('layanan.retur.create', ['invoice' => $invoice['id']]) }}" class="btn-secondary">Ajukan Retur</a>
            @endif
        </div>
    @else
        <div class="erp-card text-center py-14">
            <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">Rincian tagihan belum bisa ditampilkan.</p>
            <a href="{{ route('layanan.tagihan.index') }}" class="btn-secondary mt-4">Kembali ke Daftar Tagihan</a>
        </div>
    @endif
</div>
@endsection
