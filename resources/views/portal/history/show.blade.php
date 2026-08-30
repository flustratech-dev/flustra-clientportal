@extends('layouts.app')
@section('title', 'Detail Pengajuan')
@section('page_title', 'Detail Pengajuan')
@section('lebar', 'max-w-3xl mx-auto')
@section('kembali_url', route('riwayat.index'))
@section('kembali_label', 'Riwayat')

@section('content')
<div class="space-y-5">

    {{-- Printable Corporate Letterhead (Hanya muncul saat cetak) --}}
    <div class="print-only mb-6 pb-4 border-b-2 border-slate-900">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img src="{{ asset('images/flustraa.png') }}" class="w-10 h-10 object-contain" alt="Flustra">
                <div>
                    <h1 class="text-sm font-black text-slate-900 uppercase tracking-wider">PT FLUSTRA TEKNOLOGI NUSANTARA</h1>
                    <p class="text-[10px] text-slate-600">Gedung Flustra Tower Lt. 8, Jakarta &bull; support@flustra.id &bull; www.flustra.id</p>
                </div>
            </div>
            <div class="text-right">
                <span class="text-[11px] font-bold font-mono px-2.5 py-1 border border-slate-900 rounded inline-block">BUKTI PENGAJUAN RESMI</span>
                <p class="text-[9px] text-slate-500 mt-1">Dicetak pada: {{ now()->format('d M Y H:i') }} WIB</p>
            </div>
        </div>
    </div>

    {{-- Action Bar Layar (Disembunyikan saat cetak) --}}
    <div class="flex items-center justify-between no-print">
        <div></div>
        <button type="button" onclick="window.print()" class="btn-secondary inline-flex items-center gap-2 text-xs">
            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            <span>Cetak Tanda Terima</span>
        </button>
    </div>

    {{-- Alasan penolakan ditaruh paling atas: itu hal pertama yang dicari
         pengguna saat pengajuannya ditolak. --}}
    @if($submission->status === 'rejected')
        <div class="erp-card border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/30">
            <h3 class="text-xs font-bold text-red-700 dark:text-red-400 mb-1">Pengajuan Ini Ditolak</h3>
            <p class="text-xs text-red-600 dark:text-red-400 leading-relaxed">
                {{ $submission->status_reason ?: 'Tidak ada alasan yang dicantumkan. Silakan hubungi kami untuk penjelasan.' }}
            </p>
            @if($submission->type === 'partner_claim')
                <a href="{{ route('mitra.create') }}" class="btn-primary mt-3 no-print">Ajukan Ulang</a>
            @endif
        </div>
    @endif

    {{-- Ringkasan --}}
    <div class="erp-card space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-sm font-bold text-slate-800 dark:text-white">{{ $submission->title }}</h2>
                <p class="text-xs text-slate-500 mt-0.5">{{ $submission->type_label }}</p>
            </div>
            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold {{ $submission->status_color }}">
                {{ $submission->status_label }}
            </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-4 border-t border-slate-100 dark:border-slate-700">
            <div>
                <p class="erp-label">Nomor Pengajuan</p>
                <p class="text-xs font-mono">{{ $submission->reference_number }}</p>
            </div>
            <div>
                <p class="erp-label">Dikirim</p>
                <p class="text-xs">{{ $submission->submitted_at?->format('d M Y, H:i') ?? '—' }}</p>
            </div>
            @if($submission->amount)
            <div>
                <p class="erp-label">Nominal</p>
                <p class="text-xs font-mono font-semibold">Rp {{ number_format($submission->amount, 0, ',', '.') }}</p>
            </div>
            @endif
            @if($submission->erp_reference)
            <div>
                <p class="erp-label">Nomor Referensi Kami</p>
                <p class="text-xs font-mono">{{ $submission->erp_reference }}</p>
            </div>
            @endif
        </div>

        {{-- Antrean sinkronisasi: pengguna berhak tahu kalau kirimannya masih
             tertahan di sisi kami, bukan dibiarkan menebak. --}}
        @if($submission->sync_state === 'failed')
            <div class="rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/30 p-3">
                <p class="text-[11px] text-amber-700 dark:text-amber-400">
                    Pengajuan Anda tersimpan aman, tapi belum berhasil diteruskan ke sistem kami.
                    Tim kami sudah diberi tahu dan akan menindaklanjuti. Anda tidak perlu mengirim ulang.
                </p>
            </div>
        @elseif($submission->sync_state === 'pending' && $submission->status !== 'draft')
            <div class="rounded-xl border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-950/30 p-3">
                <p class="text-[11px] text-blue-700 dark:text-blue-400">
                    Sedang diteruskan ke sistem kami. Statusnya akan diperbarui otomatis di halaman ini.
                </p>
            </div>
        @endif
    </div>

    {{-- Lampiran --}}
    @if($submission->attachments->isNotEmpty())
    <div class="erp-card">
        <h3 class="text-xs font-bold text-slate-800 dark:text-white mb-3">Lampiran</h3>
        <div class="space-y-2">
            @foreach($submission->attachments as $file)
                <div class="flex items-center gap-3 p-2.5 rounded-xl border border-slate-100 dark:border-slate-700">
                    <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                    </svg>
                    <span class="text-xs text-slate-700 dark:text-slate-300 flex-1 min-w-0 truncate">{{ $file->original_name }}</span>
                    <span class="text-[10px] text-slate-400 shrink-0">{{ $file->readable_size }}</span>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Timeline --}}
    <div class="erp-card">
        <h3 class="text-xs font-bold text-slate-800 dark:text-white mb-4">Riwayat Proses</h3>

        <ol class="relative border-l border-slate-200 dark:border-slate-700 ml-1.5 space-y-5">
            @forelse($submission->histories as $h)
                <li class="ml-5">
                    <span class="absolute -left-[5px] w-2.5 h-2.5 rounded-full {{ $h->dot_color }}"></span>
                    <div class="flex flex-wrap items-baseline gap-x-2">
                        <p class="text-xs font-semibold text-slate-800 dark:text-white">{{ $h->to_status_label }}</p>
                        <span class="text-[10px] text-slate-400">{{ $h->created_at?->format('d M Y, H:i') }}</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-0.5">oleh {{ $h->actor_label }}</p>
                    @if($h->note)
                        <p class="text-[11px] text-slate-600 dark:text-slate-300 mt-1 leading-relaxed">{{ $h->note }}</p>
                    @endif
                </li>
            @empty
                <li class="ml-5 text-xs text-slate-400">Belum ada riwayat tercatat.</li>
            @endforelse
        </ol>
    </div>

    {{-- Printable Verification & Signature Footer --}}
    <div class="print-only pt-6 mt-6 border-t border-slate-300">
        <table class="w-full text-xs">
            <tr>
                <td class="w-1/2 align-top text-slate-600">
                    <p class="font-bold text-slate-800">Catatan Keabsahan:</p>
                    <p class="text-[10px] leading-relaxed mt-1 text-slate-500">
                        Dokumen ini merupakan bukti tanda terima elektronik yang diterbitkan secara sah oleh sistem Flustra Client Portal. Tidak memerlukan tanda tangan basah untuk pengesahan awal pengajuan.
                    </p>
                </td>
                <td class="w-1/2 align-top text-right text-slate-600">
                    <p class="text-[10px] text-slate-400">Verifikasi Otomatis Sistem:</p>
                    <p class="font-mono text-xs font-bold text-slate-800 mt-1">REF: {{ $submission->reference_number }}</p>
                    <div class="mt-4 pt-4 inline-block border-t border-slate-400 text-center min-w-[160px]">
                        <p class="text-[10px] font-bold text-slate-700">FLUSTRA CLIENT HUB</p>
                    </div>
                </td>
            </tr>
        </table>
    </div>

</div>
@endsection
