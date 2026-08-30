@extends('layouts.app')
@section('title', 'Lihat Sebagai')
@section('page_title', 'Lihat Sebagai')
@section('lebar', 'max-w-3xl mx-auto')

@section('content')
<div class="space-y-5 max-w-3xl mx-auto">

    <a href="{{ route('admin.dashboard') }}" class="btn-secondary">&larr; Kembali ke Panel Admin</a>

    <div class="erp-card !p-3.5 border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-950/30">
        <p class="text-xs text-blue-700 dark:text-blue-400 leading-relaxed">
            Lihat portal persis seperti yang dilihat mitra — berguna saat menindaklanjuti keluhan tanpa perlu
            meminta sandi mereka. <strong>Hanya baca:</strong> selama konteksnya aktif, seluruh aksi kirim
            ditolak, dan setiap perpindahan tercatat di log aktivitas.
        </p>
    </div>

    @if($terpilih)
        <div class="erp-card !p-3.5 border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/30 flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-amber-700 dark:text-amber-400">
                Sedang melihat sebagai <strong>{{ $terpilih->company_name }}</strong>
                ({{ $terpilih->partner_type_label }} &middot; {{ $terpilih->user->email ?? '—' }})
            </p>
            <form action="{{ route('admin.lihat-sebagai.selesai') }}" method="POST" class="shrink-0">
                @csrf
                <button type="submit" class="btn-secondary">Selesai</button>
            </form>
        </div>
    @endif

    <form method="GET" class="flex flex-wrap gap-2">
        <select name="tipe" class="erp-input !w-auto">
            <option value="">Semua Jenis</option>
            <option value="customer" @selected(request('tipe') === 'customer')>Pelanggan</option>
            <option value="vendor"   @selected(request('tipe') === 'vendor')>Vendor</option>
        </select>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari perusahaan / nama / email…" class="erp-input !w-auto min-w-56">
        <button type="submit" class="btn-secondary">Cari</button>
        @if(request()->hasAny(['q', 'tipe']))
            <a href="{{ route('admin.lihat-sebagai') }}" class="btn-secondary">Reset</a>
        @endif
    </form>

    @forelse($links as $link)
        <div class="erp-card flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xs font-bold text-slate-800 dark:text-white">{{ $link->company_name }}</p>
                <p class="text-[11px] text-slate-500 mt-0.5">
                    {{ $link->partner_type_label }}
                    &middot; {{ $link->user->name ?? '—' }} ({{ $link->user->email ?? '—' }})
                    &middot; ID mitra di ERP: <span class="font-mono">{{ $link->erp_partner_id }}</span>
                </p>
            </div>

            @if($terpilih && $terpilih->id === $link->id)
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold shrink-0 bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400">Sedang Dilihat</span>
            @else
                <form action="{{ route('admin.lihat-sebagai.pilih', $link) }}" method="POST" class="shrink-0">
                    @csrf
                    <button type="submit" class="btn-secondary !py-1">Lihat Sebagai Ini</button>
                </form>
            @endif
        </div>
    @empty
        <div class="erp-card text-center py-14">
            <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">Belum ada mitra terverifikasi.</p>
            <p class="text-xs text-slate-400 mt-1">Mitra muncul di sini setelah klaimnya disetujui staf di Flustra Office.</p>
        </div>
    @endforelse

    <div>{{ $links->links() }}</div>
</div>
@endsection
