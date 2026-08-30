@extends('layouts.app')
@section('title', 'Kontrak Kerja Sama')
@section('page_title', 'Kontrak Kerja Sama')
@section('lebar', 'max-w-3xl mx-auto')

@section('content')
<div class="space-y-5 max-w-3xl mx-auto">

    @include('partials.erp-offline')

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
            <div class="erp-card" x-data="{ showModal: false }">
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
                    <div class="flex items-center gap-2">
                        @include('layanan.partials.status-badge', ['status' => $k['status'], 'label' => ucfirst(str_replace('_', ' ', $k['status']))])
                    </div>
                </div>

                <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700 flex flex-wrap items-center justify-between gap-2">
                    <button type="button" @click="showModal = true" class="btn-secondary inline-flex items-center gap-1.5 text-xs py-1 px-2.5">
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span>Lihat & Cetak Naskah Perjanjian</span>
                    </button>

                    @if($k['customer_ack_at'])
                        <div class="flex items-center gap-1.5 text-[11px] text-emerald-600 dark:text-emerald-400">
                            <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>Disetujui pada {{ \Illuminate\Support\Carbon::parse($k['customer_ack_at'])->format('d M Y H:i') }}</span>
                        </div>
                    @endif
                </div>

                @if(!$k['customer_ack_at'] && !empty($k['needs_acknowledge']))
                    <form action="{{ route('layanan.kontrak.acknowledge', $k['id']) }}" method="POST"
                          class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700 space-y-3">
                        @csrf
                        <input type="hidden" name="title" value="{{ $k['title'] }}">

                        <label class="flex gap-2 items-start cursor-pointer">
                            <input type="checkbox" name="setuju" value="1" required class="mt-0.5 shrink-0">
                            <span class="text-[11px] text-slate-600 dark:text-slate-400 leading-relaxed">
                                Saya telah membaca isi naskah perjanjian kerja sama ini dan menyetujuinya secara sah mewakili perusahaan saya.
                                Persetujuan ini dicatat beserta nama, waktu, dan alamat IP saya.
                            </span>
                        </label>

                        <div class="flex justify-end">
                            <button type="submit" class="btn-primary">Setujui Kontrak Ini</button>
                        </div>
                    </form>
                @endif

                {{-- Modal Naskah Perjanjian Standar Enterprise --}}
                <div x-show="showModal" x-cloak
                     class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/70 backdrop-blur-sm flex items-center justify-center p-3 sm:p-4"
                     @keydown.escape.window="showModal = false">
                    
                    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 max-w-4xl w-full max-h-[90vh] flex flex-col overflow-hidden"
                         @click.away="showModal = false">
                        
                        {{-- Modal Header --}}
                        <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between no-print bg-slate-50 dark:bg-slate-800/50">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold text-slate-800 dark:text-white uppercase tracking-wider">Draf Naskah Perjanjian Layanan</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" onclick="window.print()" class="btn-secondary inline-flex items-center gap-1.5 text-xs py-1 px-3">
                                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                    </svg>
                                    <span>Cetak Perjanjian</span>
                                </button>
                                <button type="button" @click="showModal = false" class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-200 dark:hover:bg-slate-700">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>

                        {{-- Modal Content (Naskah Perjanjian Resmi) --}}
                        <div class="p-6 sm:p-8 overflow-y-auto space-y-6 text-slate-800 dark:text-slate-200 text-xs leading-relaxed font-sans bg-white dark:bg-slate-900">
                            
                            {{-- Kop Surat Resmi --}}
                            <div class="flex items-center justify-between pb-4 border-b-2 border-slate-900 dark:border-slate-100">
                                <div class="flex items-center gap-3">
                                    <img src="{{ asset('images/flustraa.png') }}" class="w-12 h-12 object-contain" alt="Flustra">
                                    <div>
                                        <h2 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-wider">PT FLUSTRA TEKNOLOGI NUSANTARA</h2>
                                        <p class="text-[10px] text-slate-600 dark:text-slate-400">Gedung Flustra Tower Lt. 8, Jakarta &bull; legal@flustra.id &bull; www.flustra.id</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="text-[10px] font-mono font-bold px-2 py-0.5 border border-slate-900 dark:border-slate-100 rounded">DOKUMEN HUKUM RESMI</span>
                                    <p class="text-[9px] text-slate-500 mt-1 font-mono">PKS/FLS-CST/{{ date('Y') }}/{{ str_pad($k['id'], 4, '0', STR_PAD_LEFT) }}</p>
                                </div>
                            </div>

                            {{-- Judul Perjanjian --}}
                            <div class="text-center py-2">
                                <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-wide">
                                    PERJANJIAN KERJA SAMA LAYANAN PELANGGAN
                                </h3>
                                <p class="text-[11px] font-semibold text-slate-600 dark:text-slate-400 mt-0.5">
                                    TENTANG: {{ strtoupper($k['title']) }}
                                </p>
                            </div>

                            {{-- Komparisi Para Pihak --}}
                            <div class="space-y-2 text-[11px] text-justify">
                                <p>
                                    Pada hari ini, tanggal <strong>{{ $k['start_date'] ? \Illuminate\Support\Carbon::parse($k['start_date'])->translatedFormat('d F Y') : now()->translatedFormat('d F Y') }}</strong>, bertempat di Jakarta, telah dibuat dan disepakati Perjanjian Kerja Sama oleh dan antara:
                                </p>
                                <div class="pl-4 space-y-1.5 border-l-2 border-slate-200 dark:border-slate-700">
                                    <p>
                                        <strong>1. PT FLUSTRA TEKNOLOGI NUSANTARA</strong>, berkedudukan di Gedung Flustra Tower Lt. 8, Jakarta, dalam hal ini bertindak untuk dan atas nama perseroan (selanjutnya disebut sebagai <strong>"PIHAK PERTAMA"</strong>);
                                    </p>
                                    <p>
                                        <strong>2. {{ auth()->user()->name }}</strong> (selaku perwakilan resmi dari Mitra Pelanggan yang terdaftar sah pada sistem Flustra Client Portal), berkedudukan di alamat terdaftar (selanjutnya disebut sebagai <strong>"PIHAK KEDUA"</strong>).
                                    </p>
                                </div>
                                <p>
                                    PIHAK PERTAMA dan PIHAK KEDUA secara bersama-sama disebut sebagai <strong>"PARA PIHAK"</strong>. PARA PIHAK sepakat untuk mengikatkan diri dalam Perjanjian ini dengan syarat dan ketentuan sebagai berikut:
                                </p>
                            </div>

                            {{-- Pasal-Pasal Perjanjian --}}
                            <div class="space-y-4 pt-2">
                                <div>
                                    <h4 class="font-bold text-slate-900 dark:text-white text-[11px]">PASAL 1: RUANG LINGKUP LAYANAN</h4>
                                    <p class="mt-1 text-slate-600 dark:text-slate-400">
                                        PIHAK PERTAMA setuju untuk menyediakan produk, sistem, dan/atau layanan pendukung operasional kepada PIHAK KEDUA sesuai dengan judul kontrak: <em>"{{ $k['title'] }}"</em> serta spesifikasi teknis yang disepakati bersama.
                                    </p>
                                </div>

                                <div>
                                    <h4 class="font-bold text-slate-900 dark:text-white text-[11px]">PASAL 2: JANGKA WAKTU PERJANJIAN</h4>
                                    <p class="mt-1 text-slate-600 dark:text-slate-400">
                                        Perjanjian ini berlaku efektif mulai tanggal <strong>{{ $k['start_date'] ? \Illuminate\Support\Carbon::parse($k['start_date'])->format('d M Y') : '—' }}</strong> sampai dengan tanggal <strong>{{ $k['end_date'] ? \Illuminate\Support\Carbon::parse($k['end_date'])->format('d M Y') : 'Selesai / Tanpa Batas' }}</strong>, kecuali diakhiri lebih awal berdasarkan kesepakatan tertulis PARA PIHAK.
                                    </p>
                                </div>

                                <div>
                                    <h4 class="font-bold text-slate-900 dark:text-white text-[11px]">PASAL 3: NILAI KONTRAK & SKEMA PEMBAYARAN</h4>
                                    <p class="mt-1 text-slate-600 dark:text-slate-400">
                                        @if($k['value'] > 0)
                                            Nilai komitmen kerja sama yang disepakati adalah sebesar <strong>Rp {{ number_format($k['value'], 0, ',', '.') }}</strong>. Pembayaran wajib dipenuhi oleh PIHAK KEDUA sesuai dengan faktur tagihan resmi (Invoice) yang diterbitkan melalui Flustra Portal.
                                        @else
                                            Nilai dan skema komersial mengacu pada setiap Sales Order / Invoice yang disepakati secara berkesinambungan selama masa aktif perjanjian ini.
                                        @endif
                                    </p>
                                </div>

                                <div>
                                    <h4 class="font-bold text-slate-900 dark:text-white text-[11px]">PASAL 4: KERAHASIAAN INFORMASI (NDA) & INTEGRITAS DATA</h4>
                                    <p class="mt-1 text-slate-600 dark:text-slate-400">
                                        Masing-masing pihak sepakat untuk menjaga kerahasiaan seluruh data komersial, data pelanggan, informasi sistem, dan dokumen bisnis yang diperoleh selama masa pelaksanaan kerja sama ini.
                                    </p>
                                </div>

                                <div>
                                    <h4 class="font-bold text-slate-900 dark:text-white text-[11px]">PASAL 5: HUKUM YANG BERLAKU & PENYELESAIAN PERSELISIHAN</h4>
                                    <p class="mt-1 text-slate-600 dark:text-slate-400">
                                        Perjanjian ini tunduk pada hukum Negara Republik Indonesia. Segala perselisihan yang timbul akan diselesaikan terlebih dahulu melalui musyawarah mufakat, dan apabila tidak tercapai kesepakatan, akan diselesaikan melalui domisili hukum di Pengadilan Negeri Jakarta.
                                    </p>
                                </div>
                            </div>

                            {{-- Tanda Tangan & Otorisasi --}}
                            <div class="pt-6 border-t border-slate-300 dark:border-slate-700">
                                <table class="w-full text-center text-xs">
                                    <tr>
                                        <td class="w-1/2 align-top pb-12">
                                            <p class="font-bold text-slate-900 dark:text-white">PIHAK PERTAMA</p>
                                            <p class="text-[10px] text-slate-500">PT FLUSTRA TEKNOLOGI NUSANTARA</p>
                                            <div class="my-6">
                                                <span class="inline-block px-3 py-1 bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800 rounded text-[10px] font-bold">TEROTORISASI ELEKTRONIK</span>
                                            </div>
                                            <p class="font-bold text-slate-800 dark:text-white underline">DIREKSI OPERASIONAL</p>
                                        </td>
                                        <td class="w-1/2 align-top pb-12">
                                            <p class="font-bold text-slate-900 dark:text-white">PIHAK KEDUA</p>
                                            <p class="text-[10px] text-slate-500">MITRA PELANGGAN</p>
                                            <div class="my-6">
                                                @if($k['customer_ack_at'])
                                                    <div class="inline-block p-2 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-300 dark:border-emerald-700 rounded text-left">
                                                        <p class="text-[9px] font-bold text-emerald-700 dark:text-emerald-400">✓ DISETUJUI SECARA DIGITAL</p>
                                                        <p class="text-[8px] text-emerald-600 dark:text-emerald-500 font-mono">{{ $k['customer_ack_name'] ?? auth()->user()->name }}</p>
                                                        <p class="text-[8px] text-slate-400 font-mono">{{ \Illuminate\Support\Carbon::parse($k['customer_ack_at'])->format('d/m/Y H:i') }} | IP: {{ $k['customer_ack_ip'] ?? 'Portal Verified' }}</p>
                                                    </div>
                                                @else
                                                    <span class="inline-block px-3 py-1 bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-800 rounded text-[10px]">MENUNGGU PERSETUJUAN</span>
                                                @endif
                                            </div>
                                            <p class="font-bold text-slate-800 dark:text-white underline">{{ $k['customer_ack_name'] ?? auth()->user()->name }}</p>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                        </div>
                    </div>
                </div>
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
