@extends('layouts.app')
@section('title', 'Profil')
@section('page_title', 'Profil')
@section('breadcrumb_title', 'Profil')

@section('content')
<div class="space-y-5 max-w-3xl" x-data="{ tab: 'akun' }">

    {{-- Tab --}}
    <div class="flex gap-1 p-1 rounded-2xl bg-slate-100 dark:bg-slate-800 w-fit">
        @foreach(['akun' => 'Akun', 'mitra' => 'Data Mitra', 'dokumen' => 'Dokumen Saya'] as $key => $label)
            <button @click="tab = '{{ $key }}'"
                    class="px-4 py-1.5 rounded-xl text-xs font-semibold transition-all cursor-pointer"
                    :class="tab === '{{ $key }}' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if($errors->any())
        <div class="erp-card !p-3 border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/40">
            <ul class="text-xs text-red-700 dark:text-red-400 space-y-1">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- ========== TAB AKUN ========== --}}
    <div x-show="tab === 'akun'" class="space-y-4">

        <form action="{{ route('profil.akun') }}" method="POST" enctype="multipart/form-data" class="erp-card space-y-4">
            @csrf @method('PUT')

            <h3 class="text-xs font-bold text-slate-800 dark:text-white">Data Diri</h3>

            <div class="flex items-center gap-4">
                <img src="{{ $user->avatar_url }}" alt="" class="w-14 h-14 rounded-full object-cover shrink-0">
                <div class="flex-1 min-w-0">
                    <label class="erp-label">Foto Profil</label>
                    <input type="file" name="avatar" accept=".jpg,.jpeg,.png" class="erp-input">
                    <p class="text-[10px] text-slate-400 mt-1">JPG atau PNG, maksimal 2 MB.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="erp-label">Nama Lengkap <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="erp-input">
                </div>
                <div>
                    <label class="erp-label">Nomor WhatsApp</label>
                    <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}" class="erp-input">
                </div>
            </div>

            <div>
                <label class="erp-label">Email</label>
                <input type="email" value="{{ $user->email }}" disabled class="erp-input opacity-60 cursor-not-allowed">
                <p class="text-[10px] text-slate-400 mt-1">
                    Email dipakai untuk masuk. Hubungi kami lewat Bantuan bila perlu diganti.
                </p>
            </div>

            <div class="flex justify-end pt-3 border-t border-slate-100 dark:border-slate-700">
                <button type="submit" class="btn-primary">Simpan Perubahan</button>
            </div>
        </form>

        <form action="{{ route('profil.sandi') }}" method="POST" class="erp-card space-y-4">
            @csrf @method('PUT')

            <h3 class="text-xs font-bold text-slate-800 dark:text-white">Ganti Kata Sandi</h3>

            <div>
                <label class="erp-label">Kata Sandi Saat Ini <span class="text-red-500">*</span></label>
                <input type="password" name="current_password" required class="erp-input">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="erp-label">Kata Sandi Baru <span class="text-red-500">*</span></label>
                    <input type="password" name="password" required class="erp-input">
                </div>
                <div>
                    <label class="erp-label">Ulangi Kata Sandi Baru <span class="text-red-500">*</span></label>
                    <input type="password" name="password_confirmation" required class="erp-input">
                </div>
            </div>

            <p class="text-[10px] text-slate-400">
                Minimal 8 karakter. Kami menolak kata sandi yang pernah bocor di kebocoran data publik.
            </p>

            <div class="flex justify-end pt-3 border-t border-slate-100 dark:border-slate-700">
                <button type="submit" class="btn-primary">Ganti Kata Sandi</button>
            </div>
        </form>

        <form action="{{ route('profil.logout-others') }}" method="POST" class="erp-card space-y-3">
            @csrf
            <h3 class="text-xs font-bold text-slate-800 dark:text-white">Keamanan Sesi</h3>
            <p class="text-[11px] text-slate-500 leading-relaxed">
                Pernah masuk di komputer bersama atau perangkat yang tidak lagi Anda pakai? Keluarkan
                semua sesi selain yang sedang Anda gunakan sekarang.
            </p>
            <div class="flex flex-wrap gap-2 items-end">
                <div class="flex-1 min-w-48">
                    <label class="erp-label">Kata Sandi Anda</label>
                    <input type="password" name="password" required class="erp-input">
                </div>
                <button type="submit" class="btn-secondary">Keluarkan Perangkat Lain</button>
            </div>
        </form>
    </div>

    {{-- ========== TAB DATA MITRA (adaptif per tipe akun) ========== --}}
    <div x-show="tab === 'mitra'" x-cloak class="space-y-4">

        @if($links->isEmpty())
            <div class="erp-card text-center py-12">
                <svg class="w-11 h-11 mx-auto text-slate-300 dark:text-slate-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">Belum ada data mitra</p>
                <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                    Setelah Anda terverifikasi sebagai pelanggan atau vendor, data perusahaan Anda muncul di sini
                    dan bisa diajukan perubahannya.
                </p>
                <a href="{{ route('mitra.create') }}" class="btn-primary mt-4">Ajukan Kerja Sama</a>
            </div>
        @else
            @foreach($links as $link)
                <div class="erp-card space-y-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-bold text-slate-800 dark:text-white">{{ $link->company_name }}</h3>
                            <p class="text-xs text-slate-500">Sebagai {{ $link->partner_type_label }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $link->status_color }}">
                                {{ $link->status_label }}
                            </span>

                            {{-- Pemilih peran hanya berguna kalau punya lebih dari satu --}}
                            @if($link->isVerified() && $links->where('status', 'verified')->count() > 1 && $user->active_link_id !== $link->id)
                                <form action="{{ route('profil.ganti-peran') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="link_id" value="{{ $link->id }}">
                                    <button type="submit" class="btn-secondary !py-1">Pakai peran ini</button>
                                </form>
                            @endif
                        </div>
                    </div>

                    @if($link->status === 'rejected' && $link->rejected_reason)
                        <div class="rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/30 p-3">
                            <p class="text-[11px] text-red-700 dark:text-red-400">
                                <strong>Alasan penolakan:</strong> {{ $link->rejected_reason }}
                            </p>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-4 border-t border-slate-100 dark:border-slate-700">
                        <div><p class="erp-label">NPWP</p><p class="text-xs font-mono">{{ $link->npwp ?: '—' }}</p></div>
                        <div><p class="erp-label">Contact Person</p><p class="text-xs">{{ $link->contact_person ?: '—' }}</p></div>
                        <div><p class="erp-label">Telepon</p><p class="text-xs">{{ $link->phone ?: '—' }}</p></div>
                        <div>
                            <p class="erp-label">{{ $link->partner_type === 'vendor' ? 'Email Penagihan' : 'Email Penerima Invoice' }}</p>
                            <p class="text-xs">{{ $link->billing_email ?: '—' }}</p>
                        </div>
                        <div class="sm:col-span-2"><p class="erp-label">Alamat</p><p class="text-xs">{{ $link->address ?: '—' }}</p></div>
                    </div>

                    @if($link->isVerified())
                        <div class="pt-4 border-t border-slate-100 dark:border-slate-700">
                            <p class="text-[11px] text-slate-500 leading-relaxed">
                                Perlu mengubah data di atas
                                @if($link->partner_type === 'vendor')
                                    atau nomor rekening
                                @endif
                                ? Perubahan diajukan lebih dulu dan diterapkan setelah tim kami memeriksanya —
                                @if($link->partner_type === 'vendor')
                                    khususnya untuk data rekening, demi mencegah pembayaran salah alamat.
                                @else
                                    supaya data penagihan Anda tetap konsisten dengan dokumen kami.
                                @endif
                            </p>
                            <button type="button" class="btn-secondary mt-2" disabled>
                                Ajukan Perubahan (segera hadir)
                            </button>
                        </div>
                    @endif
                </div>
            @endforeach
        @endif
    </div>

    {{-- ========== TAB DOKUMEN ========== --}}
    <div x-show="tab === 'dokumen'" x-cloak>
        <div class="erp-card text-center py-12">
            <svg class="w-11 h-11 mx-auto text-slate-300 dark:text-slate-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">Belum ada dokumen</p>
            <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                Berkas yang Anda unggah dan dokumen yang kami terbitkan — invoice, surat jalan, kontrak —
                akan terkumpul di sini.
            </p>
        </div>
    </div>

</div>
@endsection
