@extends('layouts.app')
@section('title', 'Data & Rekening')
@section('page_title', 'Data & Rekening')
@section('page_subtitle', 'Ajukan perubahan data; perubahan rekening selalu diverifikasi lewat kontak resmi.')
@section('lebar', 'max-w-2xl mx-auto')
@section('kembali_url', route('beranda'))
@section('kembali_label', 'Beranda')

@section('content')
<div class="space-y-5">

    @include('partials.erp-offline')

    <div class="erp-card !p-3.5 border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-950/30">
        <p class="text-xs text-blue-700 dark:text-blue-400 leading-relaxed">
            Perubahan di halaman ini tidak langsung berlaku. Tim kami memeriksanya lebih dulu.
            Isi hanya kolom yang ingin Anda ubah.
        </p>
    </div>

    @if($errors->any())
        <div class="erp-card !p-3 border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/40">
            <ul class="text-xs text-red-700 dark:text-red-400 space-y-1">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    @if($tertunda)
        <div class="erp-card !p-3.5 border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/30">
            <p class="text-xs text-amber-700 dark:text-amber-400 leading-relaxed">
                Anda punya pengajuan perubahan yang masih diperiksa
                (<a href="{{ route('riwayat.show', $tertunda) }}" class="underline font-semibold">{{ $tertunda->reference_number }}</a>).
                Mengirim pengajuan baru sebelum yang ini selesai bisa membuat keduanya bertabrakan.
            </p>
        </div>
    @endif

    <form action="{{ route('vendor.data.update') }}" method="POST"
          x-data="{ rekening: false }" class="erp-card space-y-4">
        @csrf
        @method('PUT')

        <h3 class="text-xs font-bold text-slate-800 dark:text-white">Data Perusahaan</h3>

        @foreach($labels as $field => $label)
            @continue(in_array($field, $rekening, true))
            <div>
                <label class="erp-label">{{ $label }}</label>

                @if($field === 'address')
                    <textarea name="{{ $field }}" rows="2" class="erp-input"
                              placeholder="{{ $nilai[$field] ?: 'Belum diisi' }}">{{ old($field) }}</textarea>
                @else
                    <input type="{{ $field === 'email' ? 'email' : 'text' }}"
                           name="{{ $field }}" value="{{ old($field) }}" class="erp-input"
                           placeholder="{{ $nilai[$field] ?: 'Belum diisi' }}">
                @endif

                @if($nilai[$field])
                    <p class="text-[10px] text-slate-400 mt-1">
                        Saat ini: <span class="text-slate-500 dark:text-slate-400">{{ $nilai[$field] }}</span>
                    </p>
                @endif
            </div>
        @endforeach

        {{-- Bagian rekening dipisah dan ditutup secara bawaan.
             Bukan hiasan: memisahkannya membuat vendor sadar sedang menyentuh
             hal yang berbeda bobotnya, dan mengurangi perubahan rekening yang
             tidak sengaja ikut terkirim bersama perubahan alamat. --}}
        <div class="pt-4 border-t border-slate-100 dark:border-slate-700">
            <button type="button" @click="rekening = !rekening"
                    class="flex items-center gap-2 text-xs font-bold text-slate-800 dark:text-white cursor-pointer">
                <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                Ubah Data Rekening
                <span class="text-[10px] font-normal text-slate-400" x-text="rekening ? '(tutup)' : '(buka)'"></span>
            </button>

            <div x-show="rekening" x-cloak x-transition class="mt-4 space-y-4">
                <div class="p-3 rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/30">
                    <p class="text-[11px] text-amber-700 dark:text-amber-400 leading-relaxed">
                        Perubahan rekening <strong>selalu</strong> kami verifikasi lewat kontak resmi Anda sebelum diterapkan,
                        tanpa pengecualian. Ini melindungi Anda: bila akun portal Anda dibajak, rekening pembayaran
                        tetap tidak bisa diubah diam-diam.
                    </p>
                </div>

                @foreach($rekening as $field)
                    <div>
                        <label class="erp-label">{{ $labels[$field] }}</label>
                        <input type="text" name="{{ $field }}" value="{{ old($field) }}" class="erp-input"
                               autocomplete="off">
                    </div>
                @endforeach

                <p class="text-[10px] text-slate-400">
                    Nomor rekening yang tersimpan sekarang tidak ditampilkan di sini. Bila ragu, tanyakan lewat halaman
                    <a href="{{ route('bantuan') }}" class="text-blue-500 hover:underline">Bantuan</a>.
                </p>
            </div>
        </div>

        <div class="pt-4 border-t border-slate-100 dark:border-slate-700">
            <label class="erp-label">
                Alasan perubahan
                <span x-show="rekening" x-cloak class="text-red-500">* wajib untuk perubahan rekening</span>
            </label>
            <textarea name="reason" rows="2" class="erp-input"
                      placeholder="Contoh: rekening lama ditutup bank per 1 Agustus 2026.">{{ old('reason') }}</textarea>
        </div>

        <div class="pt-4 flex justify-end gap-2 border-t border-slate-100 dark:border-slate-700">
            <a href="{{ route('beranda') }}" class="btn-secondary">Batal</a>
            <button type="submit" class="btn-primary">Ajukan Perubahan</button>
        </div>
    </form>
</div>
@endsection
