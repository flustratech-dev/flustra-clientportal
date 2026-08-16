@extends('layouts.app')
@section('title', 'Pengumuman')
@section('page_title', 'Pengumuman')
@section('breadcrumb_title', 'Admin')

@section('content')
<div class="space-y-5 max-w-2xl">

    <a href="{{ route('admin.dashboard') }}" class="btn-secondary">&larr; Kondisi Portal</a>

    @if($errors->any())
        <div class="erp-card !p-3 border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/40">
            <ul class="text-xs text-red-700 dark:text-red-400 space-y-1">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Pemberitahuan dari ERP: baca saja.
         Memberi admin portal tombol untuk mematikannya akan membuat mitra tidak
         tahu sistem internal sedang mati — padahal itu justru saat mereka
         paling perlu tahu. --}}
    @if($dariErp['aktif'])
        <div class="erp-card !p-3.5 border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/30">
            <p class="text-xs font-bold text-amber-700 dark:text-amber-400">Pemberitahuan aktif dari sistem internal</p>
            <p class="text-[11px] text-amber-700 dark:text-amber-400 mt-1 leading-relaxed">
                <strong>{{ $dariErp['judul'] }}</strong> — {{ $dariErp['pesan'] }}
                @if($dariErp['jadwal'])
                    <span class="block mt-1 opacity-80">
                        Dijadwalkan {{ \Illuminate\Support\Carbon::parse($dariErp['jadwal'])->translatedFormat('d F Y, H:i') }} WIB
                    </span>
                @endif
            </p>
            <p class="text-[10px] text-amber-600 dark:text-amber-500 mt-2">
                Dipasang dan dimatikan dari Flustra Office, bukan dari sini.
            </p>
        </div>
    @endif

    <form action="{{ route('admin.maintenance.update') }}" method="POST" class="erp-card space-y-4"
          x-data="{ aktif: {{ old('aktif', $aktif) ? 'true' : 'false' }} }">
        @csrf
        @method('PUT')

        <div>
            <h3 class="text-xs font-bold text-slate-800 dark:text-white">Pengumuman Portal</h3>
            <p class="text-[11px] text-slate-500 mt-1 leading-relaxed">
                Tampil di seluruh halaman portal, termasuk halaman masuk — mitra yang belum login pun perlu tahu
                kalau ada gangguan, supaya tidak mengira akunnya yang bermasalah.
            </p>
        </div>

        <label class="flex items-center gap-2.5 cursor-pointer">
            <input type="checkbox" name="aktif" value="1" x-model="aktif" class="rounded">
            <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">Nyalakan pengumuman</span>
        </label>

        <div x-show="aktif" x-cloak x-transition class="space-y-4">
            <div>
                <label class="erp-label">Judul <span class="text-red-500">*</span></label>
                <input type="text" name="judul" value="{{ old('judul', $judul) }}" maxlength="120" class="erp-input"
                       placeholder="Contoh: Pemeliharaan terjadwal Sabtu malam">
            </div>

            <div>
                <label class="erp-label">Isi pengumuman <span class="text-red-500">*</span></label>
                <textarea name="pesan" rows="3" maxlength="500" class="erp-input"
                          placeholder="Jelaskan apa yang terpengaruh dan sampai kapan. Hindari istilah teknis.">{{ old('pesan', $pesan) }}</textarea>
            </div>

            <div>
                <label class="erp-label">Tingkat</label>
                <select name="tingkat" class="erp-input">
                    <option value="info"     @selected(old('tingkat', $tingkat) === 'info')>Informasi (biru)</option>
                    <option value="warning"  @selected(old('tingkat', $tingkat) === 'warning')>Peringatan (kuning)</option>
                    <option value="critical" @selected(old('tingkat', $tingkat) === 'critical')>Penting (merah)</option>
                </select>
            </div>
        </div>

        <div class="pt-4 flex justify-end border-t border-slate-100 dark:border-slate-700">
            <button type="submit" class="btn-primary">Simpan</button>
        </div>
    </form>
</div>
@endsection
