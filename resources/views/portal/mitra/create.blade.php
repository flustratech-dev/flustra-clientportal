@extends('layouts.app')
@section('title', 'Ajukan Kerja Sama')
@section('page_title', 'Ajukan Kerja Sama')
@section('page_subtitle', 'Buka layanan pelanggan atau vendor dengan satu bukti kemitraan.')
@section('lebar', 'max-w-2xl mx-auto')
@section('kembali_url', route('beranda'))
@section('kembali_label', 'Beranda')

@section('content')
<div class="space-y-5">

    <div class="erp-card !p-3.5 border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-950/30">
        <p class="text-xs text-blue-700 dark:text-blue-400 leading-relaxed">
            Kami perlu memastikan Anda benar-benar mewakili perusahaan yang Anda sebutkan, sebelum membuka
            data tagihan dan dokumennya. Cukup satu bukti — nomor invoice, nomor purchase order, atau kode
            undangan dari kami. Pemeriksaannya dilakukan orang, biasanya dalam satu hari kerja.
        </p>
    </div>

    @if($errors->any())
        <div class="erp-card !p-3 border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/40">
            <ul class="text-xs text-red-700 dark:text-red-400 space-y-1">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    @if($existing->isNotEmpty())
        <div class="erp-card">
            <h3 class="text-xs font-bold text-slate-800 dark:text-white mb-3">Pengajuan Anda</h3>
            <div class="space-y-2">
                @foreach($existing as $link)
                    <div class="flex flex-wrap items-center justify-between gap-2 p-3 rounded-xl border border-slate-100 dark:border-slate-700">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold text-slate-800 dark:text-white">{{ $link->company_name }}</p>
                            <p class="text-[11px] text-slate-500">Sebagai {{ $link->partner_type_label }}</p>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold shrink-0 {{ $link->status_color }}">
                            {{ $link->status_label }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if($existing->count() >= 2)
        <div class="erp-card text-center py-8">
            <p class="text-xs text-slate-500">
                Anda sudah mengajukan untuk kedua jenis mitra. Tidak ada yang perlu diajukan lagi.
            </p>
        </div>
    @else

    <form action="{{ route('mitra.store') }}" method="POST" enctype="multipart/form-data"
          x-data="{ jenis: '{{ old('partner_type', $existing->has('customer') ? 'vendor' : 'customer') }}', bukti: '{{ old('evidence_type', 'invoice_number') }}' }"
          class="erp-card space-y-4">
        @csrf

        {{-- Jenis mitra --}}
        <div>
            <label class="erp-label">Anda mengajukan diri sebagai <span class="text-red-500">*</span></label>
            <div class="grid grid-cols-2 gap-2">
                @foreach([
                    ['customer', 'Pelanggan', 'Membeli produk atau jasa dari Flustra.'],
                    ['vendor', 'Vendor / Supplier', 'Memasok barang atau jasa ke Flustra.'],
                ] as [$val, $judul, $ket])
                    @continue($existing->has($val))
                    <label class="flex flex-col gap-1 p-3 rounded-xl border cursor-pointer transition-colors"
                           :class="jenis === '{{ $val }}' ? 'border-blue-500 bg-blue-50 dark:bg-blue-950/30' : 'border-slate-200 dark:border-slate-700 hover:border-slate-300'">
                        <input type="radio" name="partner_type" value="{{ $val }}" x-model="jenis" class="sr-only">
                        <span class="text-xs font-bold text-slate-800 dark:text-white">{{ $judul }}</span>
                        <span class="text-[10px] text-slate-500 leading-snug">{{ $ket }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        {{-- Data perusahaan --}}
        <div class="pt-4 border-t border-slate-100 dark:border-slate-700 space-y-4">
            <h3 class="text-xs font-bold text-slate-800 dark:text-white">Data Perusahaan</h3>

            <div>
                <label class="erp-label">Nama Perusahaan <span class="text-red-500">*</span></label>
                <input type="text" name="company_name" value="{{ old('company_name') }}" required class="erp-input"
                       placeholder="Sesuai yang tercatat di dokumen kami">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="erp-label">NPWP</label>
                    <input type="text" name="npwp" value="{{ old('npwp') }}" class="erp-input" placeholder="Opsional">
                </div>
                <div>
                    <label class="erp-label">Contact Person</label>
                    <input type="text" name="contact_person" value="{{ old('contact_person') }}" class="erp-input"
                           placeholder="Nama PIC di perusahaan Anda">
                </div>
                <div>
                    <label class="erp-label">Telepon Perusahaan</label>
                    <input type="tel" name="phone" value="{{ old('phone') }}" class="erp-input">
                </div>
                <div>
                    <label class="erp-label" x-text="jenis === 'vendor' ? 'Email Penagihan' : 'Email Penerima Invoice'"></label>
                    <input type="email" name="billing_email" value="{{ old('billing_email') }}" class="erp-input">
                </div>
            </div>

            <div>
                <label class="erp-label">Alamat</label>
                <textarea name="address" rows="2" class="erp-input">{{ old('address') }}</textarea>
            </div>
        </div>

        {{-- Bukti --}}
        <div class="pt-4 border-t border-slate-100 dark:border-slate-700 space-y-4">
            <h3 class="text-xs font-bold text-slate-800 dark:text-white">Bukti Hubungan dengan Flustra</h3>

            <div>
                <label class="erp-label">Jenis bukti <span class="text-red-500">*</span></label>
                <select name="evidence_type" x-model="bukti" class="erp-input">
                    <option value="invite_code">Kode Undangan dari Flustra</option>
                    <option value="invoice_number" x-show="jenis === 'customer'">Nomor Invoice</option>
                    <option value="po_number" x-show="jenis === 'vendor'">Nomor Purchase Order</option>
                    <option value="npwp">NPWP Perusahaan</option>
                </select>
            </div>

            <div>
                <label class="erp-label">
                    <span x-show="bukti === 'invite_code'">Kode undangan</span>
                    <span x-show="bukti === 'invoice_number'" x-cloak>Nomor invoice terakhir</span>
                    <span x-show="bukti === 'po_number'" x-cloak>Nomor purchase order</span>
                    <span x-show="bukti === 'npwp'" x-cloak>NPWP</span>
                    <span class="text-red-500">*</span>
                </label>
                <input type="text" name="evidence_value" value="{{ old('evidence_value') }}" required class="erp-input"
                       x-bind:placeholder="{
                            invite_code: 'Contoh: FLS-8H2K9',
                            invoice_number: 'Contoh: INV-202607-0031',
                            po_number: 'Contoh: PO/2026/07/0014',
                            npwp: 'Contoh: 01.234.567.8-901.000'
                       }[bukti]">
                <p class="text-[10px] text-slate-400 mt-1">
                    Belum punya salah satunya? Hubungi kami lewat halaman
                    <a href="{{ route('bantuan') }}" class="text-blue-500 hover:underline">Bantuan</a> untuk meminta kode undangan.
                </p>
            </div>

            <div>
                <label class="erp-label">Berkas Pendukung</label>
                <input type="file" name="evidence_file" accept=".jpg,.jpeg,.png,.pdf" class="erp-input">
                <p class="text-[10px] text-slate-400 mt-1">
                    Opsional. JPG, PNG, atau PDF, maksimal {{ round(config('portal.max_upload_kb') / 1024) }} MB.
                </p>
            </div>
        </div>

        <div class="pt-4 flex justify-end gap-2 border-t border-slate-100 dark:border-slate-700">
            <a href="{{ route('beranda') }}" class="btn-secondary">Batal</a>
            <button type="submit" class="btn-primary">Kirim Pengajuan</button>
        </div>
    </form>
    @endif

</div>
@endsection
