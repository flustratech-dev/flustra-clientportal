@extends('layouts.app')
@section('title', 'Penawaran')
@section('page_title', 'Penawaran')

@section('content')
<div class="space-y-5 max-w-3xl">

    @include('partials.erp-offline')

    @if(empty($quotations))
        <div class="erp-card text-center py-14">
            <svg class="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">
                {{ empty($erpError) ? 'Belum ada penawaran.' : 'Daftar penawaran belum bisa ditampilkan.' }}
            </p>
            <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                {{ empty($erpError)
                    ? 'Penawaran yang kami kirimkan untuk Anda akan muncul di sini beserta tombol untuk menyetujuinya.'
                    : 'Coba muat ulang halaman ini beberapa saat lagi.' }}
            </p>
        </div>
    @else
        @foreach($quotations as $q)
            <div class="erp-card" x-data="{ rincian: false, form: null }">

                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-mono text-slate-500">{{ $q['quotation_number'] }}</p>
                        <p class="text-sm font-bold text-slate-800 dark:text-white font-mono mt-0.5">
                            Rp {{ number_format($q['grand_total'], 0, ',', '.') }}
                        </p>
                        <p class="text-[11px] text-slate-500 mt-0.5">
                            Dikirim {{ $q['date'] ? \Illuminate\Support\Carbon::parse($q['date'])->format('d M Y') : '—' }}
                            @if($q['expiry_date'])
                                &middot; berlaku sampai {{ \Illuminate\Support\Carbon::parse($q['expiry_date'])->format('d M Y') }}
                            @endif
                        </p>
                    </div>
                    @include('layanan.partials.status-badge', [
                        'status' => $q['status'],
                        'label'  => match($q['status']) {
                            'sent'     => 'Menunggu Keputusan Anda',
                            'accepted' => 'Anda Setujui',
                            'rejected' => 'Anda Tolak',
                            'expired'  => 'Kedaluwarsa',
                            default    => $q['status'],
                        },
                    ])
                </div>

                @if(!empty($q['is_expired']))
                    <p class="mt-3 text-[11px] text-amber-600 dark:text-amber-400">
                        Masa berlaku penawaran ini sudah lewat. Hubungi kami lewat halaman
                        <a href="{{ route('bantuan') }}" class="underline">Bantuan</a> bila masih Anda butuhkan.
                    </p>
                @endif

                <button @click="rincian = !rincian"
                        class="mt-3 text-[11px] font-semibold text-blue-500 hover:underline cursor-pointer">
                    <span x-show="!rincian">Lihat rincian</span>
                    <span x-show="rincian" x-cloak>Sembunyikan rincian</span>
                </button>

                <div x-show="rincian" x-cloak x-transition class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700">
                    @if(!empty($q['items']))
                        <div class="overflow-x-auto">
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
                                    @foreach($q['items'] as $item)
                                        <tr>
                                            <td class="py-2 whitespace-normal">{{ $item['description'] }}</td>
                                            <td class="py-2 text-right font-mono">{{ rtrim(rtrim(number_format($item['quantity'], 2, ',', '.'), '0'), ',') }}</td>
                                            <td class="py-2 text-right font-mono">Rp {{ number_format($item['unit_price'], 0, ',', '.') }}</td>
                                            <td class="py-2 text-right font-mono">Rp {{ number_format($item['subtotal'], 0, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    <div class="mt-3 space-y-1 text-xs">
                        <div class="flex justify-between text-slate-500">
                            <span>Subtotal</span><span class="font-mono">Rp {{ number_format($q['subtotal'], 0, ',', '.') }}</span>
                        </div>
                        @if($q['discount'] > 0)
                            <div class="flex justify-between text-slate-500">
                                <span>Diskon</span><span class="font-mono">Rp {{ number_format($q['discount'], 0, ',', '.') }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between text-slate-500">
                            <span>Pajak</span><span class="font-mono">Rp {{ number_format($q['tax'], 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-sm font-bold text-slate-900 dark:text-white pt-1 border-t border-slate-100 dark:border-slate-700">
                            <span>Total</span><span class="font-mono">Rp {{ number_format($q['grand_total'], 0, ',', '.') }}</span>
                        </div>
                    </div>

                    @if(!empty($q['terms']))
                        <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700">
                            <p class="erp-label">Syarat &amp; Ketentuan</p>
                            <p class="text-[11px] text-slate-600 dark:text-slate-400 leading-relaxed">{{ $q['terms'] }}</p>
                        </div>
                    @endif
                </div>

                @if(!empty($q['can_decide']))
                    <form action="{{ route('layanan.penawaran.decide', $q['id']) }}" method="POST"
                          class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700 space-y-3">
                        @csrf
                        <input type="hidden" name="number" value="{{ $q['quotation_number'] }}">
                        <input type="hidden" name="amount" value="{{ $q['grand_total'] }}">

                        <div>
                            <label class="erp-label">Catatan untuk tim kami</label>
                            <textarea name="note" rows="2" class="erp-input"
                                      placeholder="Opsional. Bila menolak, alasannya sangat membantu kami."></textarea>
                        </div>

                        <div class="flex flex-wrap justify-end gap-2">
                            <button type="submit" name="decision" value="rejected" class="btn-danger">Tolak</button>
                            <button type="submit" name="decision" value="accepted" class="btn-primary">Setujui Penawaran</button>
                        </div>
                    </form>
                @elseif($q['accepted_at'])
                    <p class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700 text-[11px] text-slate-400">
                        Diputuskan {{ \Illuminate\Support\Carbon::parse($q['accepted_at'])->format('d M Y H:i') }}
                    </p>
                @endif
            </div>
        @endforeach

        @include('layanan.partials.pagination', ['meta' => $meta])
    @endif
</div>
@endsection
