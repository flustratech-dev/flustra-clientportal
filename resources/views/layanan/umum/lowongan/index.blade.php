@extends('layouts.app')
@section('title', 'Lowongan & Lamaran')
@section('page_title', 'Lowongan & Lamaran')
@section('lebar', 'max-w-3xl mx-auto')

@section('content')
<div class="space-y-5 max-w-3xl mx-auto">

    @include('partials.erp-offline')

    @if($errors->any())
        <div class="erp-card !p-3 border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/40">
            <ul class="text-xs text-red-700 dark:text-red-400 space-y-1">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    @if(empty($vacancies))
        <div class="erp-card text-center py-14">
            <svg class="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v1m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">
                {{ empty($erpError) ? 'Belum ada lowongan yang dibuka.' : 'Daftar lowongan belum bisa ditampilkan.' }}
            </p>
            <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                {{ empty($erpError)
                    ? 'Posisi yang kami buka akan muncul di sini. Silakan periksa lagi lain waktu.'
                    : 'Coba muat ulang halaman ini beberapa saat lagi.' }}
            </p>
        </div>
    @else
        @foreach($vacancies as $v)
            <div class="erp-card" x-data="{ lamar: false }">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-slate-800 dark:text-white">{{ $v['title'] }}</p>
                        <p class="text-[11px] text-slate-500 mt-0.5">
                            @if($v['position']) {{ $v['position'] }} &middot; @endif
                            @if($v['company']) {{ $v['company'] }} &middot; @endif
                            Dibuka {{ $v['posted_date'] ? \Illuminate\Support\Carbon::parse($v['posted_date'])->format('d M Y') : '—' }}
                        </p>
                    </div>
                </div>

                @if($v['description'])
                    <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed mt-3 whitespace-pre-line">{{ $v['description'] }}</p>
                @endif

                <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700">
                    @if(in_array($v['id'], $dilamar))
                        <p class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold">
                            Anda sudah melamar untuk posisi ini. Lihat statusnya di
                            <a href="{{ route('riwayat.index', ['type' => 'job_application']) }}" class="underline">Riwayat</a>.
                        </p>
                    @else
                        <button @click="lamar = !lamar" class="text-[11px] font-semibold text-blue-500 hover:underline cursor-pointer">
                            <span x-show="!lamar">Lamar posisi ini</span>
                            <span x-show="lamar" x-cloak>Batalkan</span>
                        </button>

                        <form x-show="lamar" x-cloak x-transition method="POST"
                              action="{{ route('umum.lowongan.apply', $v['id']) }}"
                              enctype="multipart/form-data" class="mt-3 space-y-3">
                            @csrf
                            <input type="hidden" name="title" value="{{ $v['title'] }}">

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="erp-label">Nama lengkap <span class="text-red-500">*</span></label>
                                    <input type="text" name="full_name" value="{{ old('full_name', auth()->user()->name) }}" required class="erp-input">
                                </div>
                                <div>
                                    <label class="erp-label">Email <span class="text-red-500">*</span></label>
                                    <input type="email" name="email" value="{{ old('email', auth()->user()->email) }}" required class="erp-input">
                                </div>
                                <div>
                                    <label class="erp-label">Nomor HP</label>
                                    <input type="tel" name="phone" value="{{ old('phone', auth()->user()->phone) }}" class="erp-input">
                                </div>
                                <div>
                                    <label class="erp-label">CV / Resume <span class="text-red-500">*</span></label>
                                    <input type="file" name="resume" accept=".pdf,.doc,.docx" required class="erp-input">
                                </div>
                            </div>

                            <p class="text-[10px] text-slate-400 leading-relaxed">
                                PDF atau Word, maksimal {{ round(config('portal.max_upload_kb') / 1024) }} MB.
                                Data lamaran yang tidak lolos kami hapus otomatis setelah 12 bulan — lihat
                                <a href="{{ route('privasi') }}" class="text-blue-500 hover:underline">kebijakan privasi</a>.
                            </p>

                            <div class="flex justify-end">
                                <button type="submit" class="btn-primary">Kirim Lamaran</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    @endif
</div>
@endsection
