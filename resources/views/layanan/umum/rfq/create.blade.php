@extends('layouts.app')
@section('title', 'Minta Penawaran')
@section('page_title', 'Minta Penawaran')
@section('page_subtitle', 'Ceritakan kebutuhan Anda, tim penjualan kami yang menyiapkan penawarannya.')
@section('lebar', 'max-w-2xl mx-auto')
@section('kembali_url', route('beranda'))
@section('kembali_label', 'Beranda')

@section('content')
<div class="space-y-5">

    @include('partials.erp-offline')

    <div class="erp-card !p-3.5 border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-950/30">
        <p class="text-xs text-blue-700 dark:text-blue-400 leading-relaxed">
            Ceritakan apa yang Anda butuhkan, dan tim penjualan kami akan menyiapkan penawarannya.
            Semakin jelas kebutuhannya — jenis barang, jumlah, dan target waktu — semakin cepat penawarannya bisa kami kirim.
        </p>
    </div>

    @if($errors->any())
        <div class="erp-card !p-3 border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/40">
            <ul class="text-xs text-red-700 dark:text-red-400 space-y-1">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('umum.rfq.store') }}" method="POST" class="erp-card space-y-4">
        @csrf

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label class="erp-label">Nama perusahaan <span class="text-red-500">*</span></label>
                <input type="text" name="company_name" value="{{ old('company_name') }}" required class="erp-input">
            </div>
            <div>
                <label class="erp-label">Nama Anda <span class="text-red-500">*</span></label>
                <input type="text" name="contact_name" value="{{ old('contact_name', $user->name) }}" required class="erp-input">
            </div>
            <div>
                <label class="erp-label">Email <span class="text-red-500">*</span></label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" required class="erp-input">
            </div>
            <div>
                <label class="erp-label">Nomor HP</label>
                <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}" class="erp-input">
            </div>
            <div>
                <label class="erp-label">Perkiraan nilai</label>
                <input type="number" name="estimated_value" value="{{ old('estimated_value') }}" min="0" step="1000" class="erp-input"
                       placeholder="Opsional, dalam Rupiah">
            </div>
        </div>

        <div>
            <label class="erp-label">Kebutuhan Anda <span class="text-red-500">*</span></label>
            <textarea name="needs" rows="5" required class="erp-input"
                      placeholder="Contoh: 500 rim kertas A4 80gsm per bulan untuk 3 kantor cabang di Jabodetabek, mulai September 2026.">{{ old('needs') }}</textarea>
        </div>

        <div class="pt-4 flex justify-end gap-2 border-t border-slate-100 dark:border-slate-700">
            <a href="{{ route('beranda') }}" class="btn-secondary">Batal</a>
            <button type="submit" class="btn-primary">Kirim Permintaan</button>
        </div>
    </form>

    @if($riwayat->isNotEmpty())
        <div class="erp-card">
            <h3 class="text-xs font-bold text-slate-800 dark:text-white mb-3">Permintaan Sebelumnya</h3>
            <div class="space-y-2">
                @foreach($riwayat as $r)
                    <a href="{{ route('riwayat.show', $r) }}"
                       class="flex flex-wrap items-center justify-between gap-2 p-3 rounded-xl border border-slate-100 dark:border-slate-700 hover:border-blue-300 dark:hover:border-blue-700 transition-colors">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold text-slate-800 dark:text-white">{{ $r->title }}</p>
                            <p class="text-[11px] text-slate-500">{{ $r->submitted_at?->format('d M Y') }}</p>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold shrink-0 {{ $r->status_color }}">{{ $r->status_label }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
