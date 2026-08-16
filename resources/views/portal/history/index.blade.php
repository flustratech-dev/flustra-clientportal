@extends('layouts.app')
@section('title', 'Riwayat & Status')
@section('page_title', 'Riwayat & Status')
@section('lebar', 'max-w-5xl mx-auto')

@section('content')
<div class="space-y-5">

    <form method="GET" class="flex flex-wrap gap-2">
        <select name="type" class="erp-input !w-auto">
            <option value="">Semua Jenis</option>
            @foreach(\App\Models\Submission::TYPE_LABELS as $key => $label)
                <option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="status" class="erp-input !w-auto">
            <option value="">Semua Status</option>
            @foreach([
                'submitted' => 'Terkirim',
                'received' => 'Diterima Sistem',
                'under_review' => 'Sedang Diproses',
                'approved' => 'Disetujui',
                'rejected' => 'Ditolak',
                'cancelled' => 'Dibatalkan',
            ] as $key => $label)
                <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
            @endforeach
        </select>

        <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nomor pengajuan…" class="erp-input !w-auto min-w-52">
        <button type="submit" class="btn-secondary">Filter</button>

        @if(request()->hasAny(['type', 'status', 'q']))
            <a href="{{ route('riwayat.index') }}" class="btn-secondary">Reset</a>
        @endif
    </form>

    @if($submissions->isEmpty())
        <div class="erp-card text-center py-14">
            <svg class="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">
                {{ request()->hasAny(['type', 'status', 'q']) ? 'Tidak ada yang cocok dengan filter ini.' : 'Belum ada pengajuan.' }}
            </p>
            <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                {{ request()->hasAny(['type', 'status', 'q'])
                    ? 'Coba longgarkan filternya.'
                    : 'Setiap pengajuan yang Anda kirim akan muncul di sini beserta status dan riwayat prosesnya.' }}
            </p>
            @unless(request()->hasAny(['type', 'status', 'q']))
                <a href="{{ route('beranda') }}" class="btn-primary mt-4">Lihat Layanan</a>
            @endunless
        </div>
    @else

        {{-- Tabel: desktop --}}
        <div class="erp-card !p-0 overflow-x-auto hidden sm:block">
            <table class="w-full whitespace-nowrap text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-400 border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th class="p-4">Nomor</th>
                        <th class="p-4">Jenis</th>
                        <th class="p-4">Pengajuan</th>
                        <th class="p-4">Dikirim</th>
                        <th class="p-4 text-right">Nominal</th>
                        <th class="p-4">Status</th>
                        <th class="p-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                    @foreach($submissions as $s)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="p-4 font-mono text-[11px] text-slate-500">{{ $s->reference_number }}</td>
                            <td class="p-4">{{ $s->type_label }}</td>
                            <td class="p-4 font-semibold text-slate-800 dark:text-white max-w-xs truncate">{{ $s->title }}</td>
                            <td class="p-4 text-slate-500">{{ $s->submitted_at?->format('d M Y H:i') ?? '—' }}</td>
                            <td class="p-4 text-right font-mono">
                                {{ $s->amount ? 'Rp '.number_format($s->amount, 0, ',', '.') : '—' }}
                            </td>
                            <td class="p-4">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $s->status_color }}">{{ $s->status_label }}</span>
                            </td>
                            <td class="p-4">
                                <a href="{{ route('riwayat.show', $s) }}" class="btn-secondary !py-1">Detail</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Kartu bertumpuk: mobile. Tabel yang menggulir ke samping tidak
             pernah enak dipakai di layar kecil. --}}
        <div class="space-y-2 sm:hidden">
            @foreach($submissions as $s)
                <a href="{{ route('riwayat.show', $s) }}" class="erp-card block">
                    <div class="flex items-start justify-between gap-2 mb-1.5">
                        <span class="text-xs font-bold text-slate-800 dark:text-white leading-snug">{{ $s->title }}</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold shrink-0 {{ $s->status_color }}">{{ $s->status_label }}</span>
                    </div>
                    <p class="text-[11px] text-slate-500">{{ $s->type_label }}</p>
                    <div class="flex items-center justify-between gap-2 mt-2 pt-2 border-t border-slate-100 dark:border-slate-700">
                        <span class="text-[10px] font-mono text-slate-400">{{ $s->reference_number }}</span>
                        <span class="text-[10px] text-slate-400">{{ $s->submitted_at?->format('d M Y') ?? '—' }}</span>
                    </div>
                </a>
            @endforeach
        </div>

        <div>{{ $submissions->links() }}</div>
    @endif
</div>
@endsection
