@extends('layouts.app')
@section('title', 'Retur & Selisih')
@section('page_title', 'Retur & Selisih')
@section('breadcrumb_title', 'Retur & Selisih')

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

    @if(empty($returns))
        <div class="erp-card text-center py-14">
            <svg class="w-12 h-12 mx-auto text-emerald-300 dark:text-emerald-700 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">
                {{ empty($erpError) ? 'Tidak ada retur atau selisih.' : 'Data retur belum bisa ditampilkan.' }}
            </p>
            <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                {{ empty($erpError)
                    ? 'Bila ada barang yang kami kembalikan atau nota debit yang kami terbitkan, rinciannya muncul di sini.'
                    : 'Coba muat ulang halaman ini beberapa saat lagi.' }}
            </p>
        </div>
    @else
        @foreach($returns as $r)
            <div class="erp-card" x-data="{ sanggah: false }">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-mono text-slate-500">{{ $r['return_number'] }}</p>
                        <p class="text-sm font-bold text-slate-800 dark:text-white font-mono mt-0.5">
                            Rp {{ number_format($r['total_amount'], 0, ',', '.') }}
                        </p>
                        <p class="text-[11px] text-slate-500 mt-0.5">
                            {{ $r['date'] ? \Illuminate\Support\Carbon::parse($r['date'])->format('d M Y') : '—' }}
                        </p>
                    </div>
                    @include('layanan.partials.status-badge', [
                        'status' => $r['status'],
                        'label'  => ucfirst(str_replace('_', ' ', $r['status'])),
                    ])
                </div>

                @if($r['reason'])
                    <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700">
                        <p class="erp-label">Alasan</p>
                        <p class="text-[11px] text-slate-600 dark:text-slate-400 leading-relaxed">{{ $r['reason'] }}</p>
                    </div>
                @endif

                @if($r['debit_note'])
                    <div class="mt-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="erp-label">Nota Debit</p>
                                <p class="text-xs font-mono text-slate-700 dark:text-slate-300">{{ $r['debit_note']['number'] ?? '—' }}</p>
                            </div>
                            <p class="text-xs font-mono font-semibold text-slate-800 dark:text-white">
                                Rp {{ number_format($r['debit_note']['amount'] ?? 0, 0, ',', '.') }}
                            </p>
                        </div>
                    </div>
                @endif

                @php $milik = $sanggahan[$r['id']] ?? null; @endphp

                @if($milik)
                    <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-[11px] text-slate-500">
                                Sanggahan Anda
                                <a href="{{ route('riwayat.show', $milik) }}" class="font-mono text-blue-500 hover:underline">{{ $milik->reference_number }}</a>
                            </p>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $milik->status_color }}">{{ $milik->status_label }}</span>
                        </div>
                        @if($milik->status_reason)
                            <p class="text-[11px] text-slate-600 dark:text-slate-400 leading-relaxed mt-2">{{ $milik->status_reason }}</p>
                        @endif
                    </div>
                @else
                    <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700">
                        <button @click="sanggah = !sanggah"
                                class="text-[11px] font-semibold text-blue-500 hover:underline cursor-pointer">
                            <span x-show="!sanggah">Tidak setuju dengan retur ini?</span>
                            <span x-show="sanggah" x-cloak>Batalkan sanggahan</span>
                        </button>

                        <form x-show="sanggah" x-cloak x-transition method="POST"
                              action="{{ route('vendor.retur.dispute', $r['id']) }}"
                              enctype="multipart/form-data" class="mt-3 space-y-3">
                            @csrf
                            <input type="hidden" name="number" value="{{ $r['return_number'] }}">

                            <div>
                                <label class="erp-label">Keberatan Anda <span class="text-red-500">*</span></label>
                                <textarea name="reason" rows="3" required class="erp-input"
                                          placeholder="Contoh: barang yang diretur sesuai spesifikasi pada purchase order, terlampir foto saat pengiriman."></textarea>
                            </div>

                            <div>
                                <label class="erp-label">Berkas pendukung</label>
                                <input type="file" name="bukti" accept=".jpg,.jpeg,.png,.pdf" class="erp-input">
                                <p class="text-[10px] text-slate-400 mt-1">
                                    Opsional. JPG, PNG, atau PDF, maksimal {{ round(config('portal.max_upload_kb') / 1024) }} MB.
                                </p>
                            </div>

                            <p class="text-[10px] text-slate-400 leading-relaxed">
                                Mengirim sanggahan tidak otomatis membatalkan retur atau nota debitnya.
                                Tim kami akan meninjau keberatan Anda dan mengabari hasilnya.
                            </p>

                            <div class="flex justify-end">
                                <button type="submit" class="btn-primary">Kirim Sanggahan</button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        @endforeach

        @include('layanan.partials.pagination', ['meta' => $meta])
    @endif
</div>
@endsection
