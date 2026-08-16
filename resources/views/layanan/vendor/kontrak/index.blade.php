@extends('layouts.app')
@section('title', 'Kontrak & Dokumen')
@section('page_title', 'Kontrak & Dokumen')

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

    @if(empty($contracts))
        <div class="erp-card text-center py-14">
            <svg class="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
            </svg>
            <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">
                {{ empty($erpError) ? 'Belum ada kontrak.' : 'Daftar kontrak belum bisa ditampilkan.' }}
            </p>
            <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                {{ empty($erpError)
                    ? 'Kontrak kerja sama antara perusahaan Anda dan Flustra akan muncul di sini.'
                    : 'Coba muat ulang halaman ini beberapa saat lagi.' }}
            </p>
        </div>
    @else
        @foreach($contracts as $k)
            <div class="erp-card">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-slate-800 dark:text-white">{{ $k['title'] }}</p>
                        <p class="text-[11px] text-slate-500 mt-0.5">
                            {{ $k['start_date'] ? \Illuminate\Support\Carbon::parse($k['start_date'])->format('d M Y') : '—' }}
                            &ndash;
                            {{ $k['end_date'] ? \Illuminate\Support\Carbon::parse($k['end_date'])->format('d M Y') : 'tanpa batas' }}
                        </p>
                        @if($k['value'] > 0)
                            <p class="text-xs font-mono text-slate-700 dark:text-slate-300 mt-1">
                                Rp {{ number_format($k['value'], 0, ',', '.') }}
                            </p>
                        @endif
                    </div>
                    @include('layanan.partials.status-badge', ['status' => $k['status'], 'label' => ucfirst(str_replace('_', ' ', $k['status']))])
                </div>

                @if($k['customer_ack_at'])
                    <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700 flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-[11px] text-emerald-600 dark:text-emerald-400">
                            Anda setujui pada {{ \Illuminate\Support\Carbon::parse($k['customer_ack_at'])->format('d M Y H:i') }}
                        </p>
                    </div>
                @elseif(!empty($k['needs_acknowledge']))
                    <form action="{{ route('vendor.kontrak.acknowledge', $k['id']) }}" method="POST"
                          class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700 space-y-3">
                        @csrf
                        <input type="hidden" name="title" value="{{ $k['title'] }}">

                        <label class="flex gap-2 items-start cursor-pointer">
                            <input type="checkbox" name="setuju" value="1" required class="mt-0.5 shrink-0">
                            <span class="text-[11px] text-slate-600 dark:text-slate-400 leading-relaxed">
                                Saya telah membaca isi kontrak ini dan menyetujuinya mewakili perusahaan saya.
                                Persetujuan ini dicatat beserta nama, waktu, dan alamat IP saya.
                            </span>
                        </label>

                        <div class="flex justify-end">
                            <button type="submit" class="btn-primary">Setujui Kontrak</button>
                        </div>
                    </form>
                @endif

                @if(!empty($k['has_document']))
                    <p class="text-[10px] text-slate-400 mt-3">
                        Dokumen kontrak dipegang tim kami. Minta salinannya lewat halaman
                        <a href="{{ route('bantuan') }}" class="text-blue-500 hover:underline">Bantuan</a>.
                    </p>
                @endif
            </div>
        @endforeach

        <div class="erp-card !p-3.5 border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-950/30">
            <p class="text-[11px] text-blue-700 dark:text-blue-400 leading-relaxed">
                Persetujuan lewat portal ini dicatat sebagai pernyataan setuju beserta nama, waktu, dan alamat IP Anda.
                Untuk kontrak yang memerlukan tanda tangan digital bersertifikat, tim kami akan menghubungi Anda terpisah.
            </p>
        </div>
    @endif
</div>
@endsection
