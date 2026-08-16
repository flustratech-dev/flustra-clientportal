@extends('layouts.app')
@section('title', 'Ajukan Retur')
@section('page_title', 'Ajukan Retur')
@section('page_subtitle', 'Ajukan pengembalian barang atas tagihan yang sudah Anda terima.')
@section('lebar', 'max-w-2xl mx-auto')
@section('kembali_url', route('layanan.tagihan.index'))
@section('kembali_label', 'Tagihan')

@section('content')
<div class="space-y-5">

    @include('partials.erp-offline')

    @if($errors->any())
        <div class="erp-card !p-3 border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/40">
            <ul class="text-xs text-red-700 dark:text-red-400 space-y-1">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    @if(empty($invoices))
        <div class="erp-card text-center py-14">
            <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">
                {{ empty($erpError) ? 'Belum ada tagihan yang bisa diretur.' : 'Daftar tagihan belum bisa ditampilkan.' }}
            </p>
            <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                Retur diajukan atas barang pada tagihan yang sudah kami terbitkan.
            </p>
            <a href="{{ route('layanan.tagihan.index') }}" class="btn-secondary mt-4">Lihat Tagihan</a>
        </div>
    @else

    {{-- Langkah 1: pilih tagihan. Barangnya baru bisa dipilih setelah rincian
         tagihannya dimuat, karena daftar barang datang dari ERP. --}}
    @if(empty($invoice))
        <div class="erp-card space-y-4">
            <div>
                <h3 class="text-xs font-bold text-slate-800 dark:text-white">Pilih tagihan</h3>
                <p class="text-[11px] text-slate-500 mt-1">
                    Pilih tagihan yang memuat barang yang ingin Anda kembalikan.
                </p>
            </div>

            <div class="space-y-2">
                @foreach($invoices as $i)
                    <a href="{{ route('layanan.retur.create', ['invoice' => $i['id']]) }}"
                       class="flex flex-wrap items-center justify-between gap-2 p-3 rounded-xl border border-slate-100 dark:border-slate-700 hover:border-blue-300 dark:hover:border-blue-700 transition-colors">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold text-slate-800 dark:text-white font-mono">{{ $i['invoice_number'] }}</p>
                            <p class="text-[11px] text-slate-500">
                                {{ $i['invoice_date'] ? \Illuminate\Support\Carbon::parse($i['invoice_date'])->format('d M Y') : '—' }}
                                &middot; Rp {{ number_format($i['grand_total'], 0, ',', '.') }}
                            </p>
                        </div>
                        <span class="text-[11px] font-semibold text-blue-500 shrink-0">Pilih &rarr;</span>
                    </a>
                @endforeach
            </div>
        </div>
    @else

        @php
            // Hanya baris yang punya product_id yang bisa diretur. Baris
            // deskripsi bebas (biaya kirim, potongan) tidak punya produk nyata
            // untuk dicatat returnya di ERP.
            $barang = array_values(array_filter($invoice['items'] ?? [], fn ($it) => !empty($it['product_id'])));
        @endphp

        <div class="erp-card !p-3.5 bg-slate-50 dark:bg-slate-800/60">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p class="erp-label">Tagihan</p>
                    <p class="text-xs font-mono font-semibold text-slate-800 dark:text-white">{{ $invoice['invoice_number'] }}</p>
                </div>
                <a href="{{ route('layanan.retur.create') }}" class="text-[11px] text-blue-500 hover:underline">Ganti tagihan</a>
            </div>
        </div>

        @if(empty($barang))
            <div class="erp-card text-center py-10">
                <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">Tidak ada barang yang bisa diretur pada tagihan ini.</p>
                <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                    Tagihan ini hanya berisi baris biaya, bukan barang. Hubungi kami lewat halaman
                    <a href="{{ route('bantuan') }}" class="text-blue-500 hover:underline">Bantuan</a> bila ada yang perlu disesuaikan.
                </p>
            </div>
        @else
        <form action="{{ route('layanan.retur.store') }}" method="POST" enctype="multipart/form-data"
              x-data="{
                  barang: {{ Js::from(collect($barang)->keyBy('product_id')) }},
                  dipilih: '{{ old('product_id', '') }}',
                  get item() { return this.barang[this.dipilih] ?? null; }
              }"
              class="erp-card space-y-4">
            @csrf
            <input type="hidden" name="invoice_id" value="{{ $invoice['id'] }}">
            <input type="hidden" name="invoice_number" value="{{ $invoice['invoice_number'] }}">

            <div>
                <label class="erp-label">Barang yang dikembalikan <span class="text-red-500">*</span></label>
                <select name="product_id" x-model="dipilih" required class="erp-input">
                    <option value="">— Pilih barang —</option>
                    @foreach($barang as $it)
                        <option value="{{ $it['product_id'] }}">
                            {{ $it['description'] }} ({{ rtrim(rtrim(number_format($it['quantity'], 2, ',', '.'), '0'), ',') }} {{ $it['unit'] }})
                        </option>
                    @endforeach
                </select>
                <input type="hidden" name="product_name" :value="item ? item.description : ''">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="erp-label">Jumlah yang diretur <span class="text-red-500">*</span></label>
                    <input type="number" name="qty" value="{{ old('qty') }}" required min="0.01" step="0.01"
                           :max="item ? item.quantity : null" class="erp-input">
                    <p class="text-[10px] text-slate-400 mt-1" x-show="item" x-cloak>
                        Maksimal <span x-text="item ? item.quantity : ''"></span> sesuai yang tercatat di tagihan.
                    </p>
                </div>
                <div>
                    <label class="erp-label">Alasan pengembalian <span class="text-red-500">*</span></label>
                    <select name="reason_type" required class="erp-input">
                        @foreach($alasan as $key => $label)
                            <option value="{{ $key }}" @selected(old('reason_type') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="erp-label">Ceritakan kondisinya <span class="text-red-500">*</span></label>
                <textarea name="reason" rows="3" required class="erp-input"
                          placeholder="Contoh: kemasan penyok dan isinya bocor saat diterima.">{{ old('reason') }}</textarea>
            </div>

            <div>
                <label class="erp-label">Foto barang</label>
                <input type="file" name="photo" accept=".jpg,.jpeg,.png,.pdf" class="erp-input">
                <p class="text-[10px] text-slate-400 mt-1">
                    Opsional tapi sangat membantu. JPG, PNG, atau PDF, maksimal {{ round(config('portal.max_upload_kb') / 1024) }} MB.
                </p>
            </div>

            <div class="pt-4 flex justify-end gap-2 border-t border-slate-100 dark:border-slate-700">
                <a href="{{ route('layanan.tagihan.index') }}" class="btn-secondary">Batal</a>
                <button type="submit" class="btn-primary">Kirim Pengajuan Retur</button>
            </div>
        </form>
        @endif
    @endif
    @endif
</div>
@endsection
