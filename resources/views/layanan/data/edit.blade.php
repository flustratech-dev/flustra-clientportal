@extends('layouts.app')
@section('title', 'Perbarui Data Perusahaan')
@section('page_title', 'Perbarui Data Perusahaan')
@section('page_subtitle', 'Ajukan perubahan data; tim kami memeriksanya sebelum berlaku.')
@section('lebar', 'max-w-2xl mx-auto')
@section('kembali_url', route('beranda'))
@section('kembali_label', 'Beranda')

@section('content')
<div class="space-y-5">

    @include('partials.erp-offline')

    <div class="erp-card !p-3.5 border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-950/30">
        <p class="text-xs text-blue-700 dark:text-blue-400 leading-relaxed">
            Perubahan di halaman ini tidak langsung berlaku. Tim kami memeriksanya lebih dulu supaya data
            perusahaan Anda di sistem kami tetap cocok dengan dokumen resmi. Isi hanya kolom yang ingin diubah.
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

    <form action="{{ route('layanan.data.update') }}" method="POST" class="erp-card space-y-4">
        @csrf
        @method('PUT')

        @foreach($labels as $field => $label)
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

        <div class="pt-4 border-t border-slate-100 dark:border-slate-700">
            <label class="erp-label">Alasan perubahan</label>
            <textarea name="reason" rows="2" class="erp-input"
                      placeholder="Opsional. Contoh: kantor pindah alamat sejak 1 Agustus 2026.">{{ old('reason') }}</textarea>
            <p class="text-[10px] text-slate-400 mt-1">
                Menyebutkan alasannya mempercepat pemeriksaan tim kami.
            </p>
        </div>

        <div class="pt-4 flex justify-end gap-2 border-t border-slate-100 dark:border-slate-700">
            <a href="{{ route('beranda') }}" class="btn-secondary">Batal</a>
            <button type="submit" class="btn-primary">Ajukan Perubahan</button>
        </div>
    </form>
</div>
@endsection
