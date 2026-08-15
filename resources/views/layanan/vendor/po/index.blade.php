@extends('layouts.app')
@section('title', 'Purchase Order Masuk')
@section('page_title', 'Purchase Order Masuk')
@section('breadcrumb_title', 'Purchase Order')

@section('content')
<div class="space-y-5 max-w-3xl">

    @include('partials.erp-offline')

    @if($errors->any())
        <div class="erp-card !p-3 border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/40">
            <ul class="text-xs text-red-700 dark:text-red-400 space-y-1">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    @if(empty($orders))
        <div class="erp-card text-center py-14">
            <svg class="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">
                {{ empty($erpError) ? 'Belum ada purchase order.' : 'Daftar purchase order belum bisa ditampilkan.' }}
            </p>
            <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                {{ empty($erpError)
                    ? 'Pesanan pembelian dari kami akan muncul di sini beserta tombol untuk menyatakan kesanggupan Anda.'
                    : 'Coba muat ulang halaman ini beberapa saat lagi.' }}
            </p>
        </div>
    @else
        @foreach($orders as $po)
            <div class="erp-card" x-data="{ rincian: false, tolak: false }">

                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-mono text-slate-500">{{ $po['po_number'] }}</p>
                        <p class="text-sm font-bold text-slate-800 dark:text-white font-mono mt-0.5">
                            Rp {{ number_format($po['grand_total'], 0, ',', '.') }}
                        </p>
                        <p class="text-[11px] text-slate-500 mt-0.5">
                            Terbit {{ $po['po_date'] ? \Illuminate\Support\Carbon::parse($po['po_date'])->format('d M Y') : '—' }}
                            @if($po['expected_delivery_date'])
                                &middot; diharapkan tiba {{ \Illuminate\Support\Carbon::parse($po['expected_delivery_date'])->format('d M Y') }}
                            @endif
                        </p>
                    </div>

                    @if($po['vendor_confirmation_status'])
                        @include('layanan.partials.status-badge', [
                            'status' => $po['vendor_confirmation_status'] === 'accepted' ? 'accepted' : 'rejected',
                            'label'  => $po['vendor_confirmation_status'] === 'accepted' ? 'Anda Sanggupi' : 'Anda Tolak',
                        ])
                    @else
                        @include('layanan.partials.status-badge', ['status' => 'pending', 'label' => 'Menunggu Konfirmasi Anda'])
                    @endif
                </div>

                @if($po['shipping_address'])
                    <div class="mt-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700">
                        <p class="erp-label">Kirim ke</p>
                        <p class="text-[11px] text-slate-600 dark:text-slate-400 leading-relaxed">{{ $po['shipping_address'] }}</p>
                    </div>
                @endif

                <button @click="rincian = !rincian"
                        class="mt-3 text-[11px] font-semibold text-blue-500 hover:underline cursor-pointer">
                    <span x-show="!rincian">Lihat rincian barang</span>
                    <span x-show="rincian" x-cloak>Sembunyikan rincian</span>
                </button>

                <div x-show="rincian" x-cloak x-transition class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700 overflow-x-auto">
                    <table class="w-full text-left text-xs whitespace-nowrap">
                        <thead class="text-slate-400 border-b border-slate-100 dark:border-slate-700">
                            <tr>
                                <th class="py-2">Barang</th>
                                <th class="py-2 text-right">Dipesan</th>
                                <th class="py-2 text-right">Diterima</th>
                                <th class="py-2 text-right">Harga</th>
                                <th class="py-2 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                            @foreach($po['items'] as $item)
                                <tr>
                                    <td class="py-2 whitespace-normal">{{ $item['description'] }}</td>
                                    <td class="py-2 text-right font-mono">{{ rtrim(rtrim(number_format($item['quantity_ordered'], 2, ',', '.'), '0'), ',') }} {{ $item['unit'] }}</td>
                                    <td class="py-2 text-right font-mono text-slate-500">{{ rtrim(rtrim(number_format($item['quantity_received'], 2, ',', '.'), '0'), ',') }}</td>
                                    <td class="py-2 text-right font-mono">Rp {{ number_format($item['unit_price'], 0, ',', '.') }}</td>
                                    <td class="py-2 text-right font-mono">Rp {{ number_format($item['subtotal'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    @if($po['notes'])
                        <div class="mt-3">
                            <p class="erp-label">Catatan</p>
                            <p class="text-[11px] text-slate-600 dark:text-slate-400 leading-relaxed">{{ $po['notes'] }}</p>
                        </div>
                    @endif
                </div>

                @if(!empty($po['needs_confirmation']))
                    <form action="{{ route('vendor.po.confirm', $po['id']) }}" method="POST"
                          class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700 space-y-3">
                        @csrf
                        <input type="hidden" name="number" value="{{ $po['po_number'] }}">

                        <div x-show="!tolak">
                            <label class="erp-label">Tanggal kirim yang Anda sanggupi <span class="text-red-500">*</span></label>
                            <input type="date" name="promised_date" min="{{ date('Y-m-d') }}" class="erp-input"
                                   value="{{ $po['expected_delivery_date'] }}">
                            <p class="text-[10px] text-slate-400 mt-1">
                                Isi tanggal yang realistis. Tim kami menjadwalkan penerimaan barang berdasarkan ini.
                            </p>
                        </div>

                        <div>
                            <label class="erp-label">Catatan</label>
                            <textarea name="notes" rows="2" class="erp-input"
                                      placeholder="Opsional. Bila menolak, alasannya sangat membantu kami."></textarea>
                        </div>

                        <div class="flex flex-wrap justify-end gap-2">
                            <button type="submit" name="status" value="rejected" @click="tolak = true" class="btn-danger">Tidak Sanggup</button>
                            <button type="submit" name="status" value="accepted" class="btn-primary">Sanggup Kirim</button>
                        </div>
                    </form>
                @elseif($po['vendor_confirmed_at'])
                    <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700 flex flex-wrap gap-4">
                        <div>
                            <p class="erp-label">Dikonfirmasi</p>
                            <p class="text-[11px] text-slate-600 dark:text-slate-400">
                                {{ \Illuminate\Support\Carbon::parse($po['vendor_confirmed_at'])->format('d M Y H:i') }}
                            </p>
                        </div>
                        @if($po['vendor_promised_date'])
                            <div>
                                <p class="erp-label">Janji Kirim</p>
                                <p class="text-[11px] font-semibold text-slate-700 dark:text-slate-300">
                                    {{ \Illuminate\Support\Carbon::parse($po['vendor_promised_date'])->format('d M Y') }}
                                </p>
                            </div>
                        @endif
                        @if($po['vendor_confirmation_status'] === 'accepted')
                            <div class="ml-auto flex items-end">
                                <a href="{{ route('vendor.tagihan.create', ['po' => $po['id']]) }}" class="btn-secondary !py-1">Kirim Tagihan</a>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach

        @include('layanan.partials.pagination', ['meta' => $meta])
    @endif
</div>
@endsection
