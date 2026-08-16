@extends('layouts.app')
@section('title', 'Kondisi Portal')
@section('page_title', 'Kondisi Portal')

@section('content')
<div class="space-y-5">

    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('admin.maintenance') }}" class="btn-secondary">Pengumuman</a>
        <a href="{{ route('admin.lihat-sebagai') }}" class="btn-secondary">Lihat Sebagai Mitra</a>
        @if($ringkasan['gagal_sinkron'] > 0)
            <form action="{{ route('admin.antre-ulang-semua') }}" method="POST"
                  onsubmit="return confirm('Antre ulang {{ $ringkasan['gagal_sinkron'] }} pengajuan yang gagal?');">
                @csrf
                <button type="submit" class="btn-primary">Antre Ulang Semua ({{ $ringkasan['gagal_sinkron'] }})</button>
            </form>
        @endif
    </div>

    {{-- Sambungan ke ERP: pertanyaan pertama saat ada yang mengeluh --}}
    <div class="erp-card !p-3.5 {{ $erpSehat['sehat'] ? 'border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/30' : 'border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/30' }}">
        <div class="flex items-start gap-2.5">
            <span class="w-2 h-2 rounded-full mt-1.5 shrink-0 {{ $erpSehat['sehat'] ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
            <div class="min-w-0">
                <p class="text-xs font-bold {{ $erpSehat['sehat'] ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400' }}">
                    Sambungan ke sistem internal: {{ $erpSehat['sehat'] ? 'normal' : 'bermasalah' }}
                </p>
                <p class="text-[11px] mt-0.5 {{ $erpSehat['sehat'] ? 'text-emerald-600 dark:text-emerald-500' : 'text-red-600 dark:text-red-500' }}">
                    {{ $erpSehat['pesan'] }}
                </p>
            </div>
        </div>
    </div>

    {{-- Angka pokok --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        @foreach([
            ['Pengguna', $ringkasan['pengguna'], 'text-slate-700 dark:text-slate-200'],
            ['Mitra Terverifikasi', $ringkasan['mitra_terverif'], 'text-emerald-500'],
            ['Klaim Menunggu', $ringkasan['klaim_menunggu'], 'text-amber-500'],
            ['Total Pengajuan', $ringkasan['pengajuan'], 'text-slate-700 dark:text-slate-200'],
            ['Gagal Terkirim', $ringkasan['gagal_sinkron'], 'text-red-500'],
            ['Belum Terkirim', $ringkasan['belum_terkirim'], 'text-amber-500'],
            ['Antrean Job', $ringkasan['antrean_job'], 'text-blue-500'],
            ['Job Gagal', $ringkasan['job_gagal'], 'text-red-500'],
        ] as [$label, $angka, $warna])
            <div class="erp-card">
                <p class="erp-label !mb-1">{{ $label }}</p>
                <p class="text-2xl font-bold {{ $warna }}">{{ $angka }}</p>
            </div>
        @endforeach
    </div>

    {{-- Lalu lintas 24 jam --}}
    <div class="erp-card">
        <h3 class="text-xs font-bold text-slate-800 dark:text-white mb-1">Lalu Lintas 24 Jam Terakhir</h3>
        <p class="text-[11px] text-slate-500 mb-3">
            Panggilan keluar = portal menghubungi sistem internal. Masuk = sistem internal mengabari portal.
        </p>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            @foreach([
                ['Keluar', $lalulintas['keluar'], 'text-slate-700 dark:text-slate-200'],
                ['Keluar Gagal', $lalulintas['keluar_gagal'], $lalulintas['keluar_gagal'] > 0 ? 'text-red-500' : 'text-slate-400'],
                ['Masuk', $lalulintas['masuk'], 'text-slate-700 dark:text-slate-200'],
                ['Masuk Ditolak', $lalulintas['masuk_ditolak'], $lalulintas['masuk_ditolak'] > 0 ? 'text-amber-500' : 'text-slate-400'],
            ] as [$label, $angka, $warna])
                <div>
                    <p class="erp-label !mb-1">{{ $label }}</p>
                    <p class="text-lg font-bold font-mono {{ $warna }}">{{ $angka }}</p>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Pengajuan yang gagal terkirim --}}
    <div class="erp-card">
        <h3 class="text-xs font-bold text-slate-800 dark:text-white mb-1">Pengajuan yang Gagal Terkirim</h3>
        <p class="text-[11px] text-slate-500 mb-3">
            Datanya aman di portal — yang gagal hanya meneruskannya ke sistem internal. Antre ulang setelah
            penyebabnya diperbaiki.
        </p>

        @forelse($gagal as $s)
            <div class="flex flex-wrap items-start justify-between gap-3 p-3 rounded-xl border border-slate-100 dark:border-slate-700 mb-2">
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-slate-800 dark:text-white">
                        <span class="font-mono">{{ $s->reference_number }}</span> &middot; {{ $s->type_label }}
                    </p>
                    <p class="text-[11px] text-slate-500 mt-0.5">
                        {{ $s->user->name ?? '—' }} &middot; {{ $s->submitted_at?->format('d M Y H:i') }}
                        &middot; {{ $s->sync_attempts }} percobaan
                    </p>
                    @if($s->sync_error)
                        <p class="text-[11px] text-red-600 dark:text-red-400 mt-1 break-words">{{ \Illuminate\Support\Str::limit($s->sync_error, 200) }}</p>
                    @endif
                </div>
                <form action="{{ route('admin.antre-ulang', $s->id) }}" method="POST" class="shrink-0">
                    @csrf
                    <button type="submit" class="btn-secondary !py-1">Antre Ulang</button>
                </form>
            </div>
        @empty
            <p class="text-xs text-slate-400 py-6 text-center">Tidak ada pengajuan yang gagal terkirim.</p>
        @endforelse
    </div>

    {{-- Log API terakhir --}}
    <div class="erp-card !p-0 overflow-x-auto">
        <div class="p-4 pb-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-xs font-bold text-slate-800 dark:text-white">Lalu Lintas Terakhir</h3>
                <p class="text-[11px] text-slate-500 mt-0.5">
                    Dipakai saat menelusuri keluhan semacam &ldquo;pengajuan saya tidak muncul&rdquo;.
                </p>
            </div>
            @if($logTerakhir->isNotEmpty())
                <form action="{{ route('admin.log.hapus') }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus semua riwayat log lalu lintas API?');" class="shrink-0">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-2.5 py-1 text-[11px] font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30 rounded-lg border border-red-200 dark:border-red-800/60 transition-colors flex items-center gap-1.5 cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Hapus Log
                    </button>
                </form>
            @endif
        </div>
        <table class="w-full whitespace-nowrap text-left text-xs">
            <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-400 border-y border-slate-200 dark:border-slate-700">
                <tr>
                    <th class="p-3">Waktu</th>
                    <th class="p-3">Arah</th>
                    <th class="p-3">Endpoint</th>
                    <th class="p-3">Kode</th>
                    <th class="p-3">Durasi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                @forelse($logTerakhir as $log)
                    <tr>
                        <td class="p-3 text-slate-500">{{ $log->created_at?->format('d M H:i:s') }}</td>
                        <td class="p-3">
                            <span class="px-1.5 py-0.5 rounded-full text-[9px] font-bold {{ $log->direction === 'outbound' ? 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400' : 'bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400' }}">
                                {{ $log->direction === 'outbound' ? 'Portal → ERP' : 'ERP → Portal' }}
                            </span>
                        </td>
                        <td class="p-3 font-mono text-[11px] max-w-xs truncate" title="{{ $log->endpoint }}">{{ $log->method }} {{ \Illuminate\Support\Str::limit($log->endpoint, 48) }}</td>
                        <td class="p-3 font-mono {{ $log->status_code && $log->status_code >= 400 ? 'text-red-500 font-bold' : '' }}">{{ $log->status_code ?? '—' }}</td>
                        <td class="p-3 text-slate-500">{{ $log->duration_ms !== null ? $log->duration_ms.' ms' : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-8 text-center text-slate-400">Belum ada lalu lintas tercatat.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
