@extends('layouts.app')
@section('title', 'Kirim Tagihan')
@section('page_title', 'Kirim Tagihan')
@section('page_subtitle', 'Tagihkan purchase order yang sudah Anda sanggupi.')
@section('lebar', 'max-w-2xl mx-auto')
@section('kembali_url', route('vendor.po.index'))
@section('kembali_label', 'Purchase Order')

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

    @if(empty($orders))
        <div class="erp-card text-center py-14">
            <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">
                {{ empty($erpError) ? 'Belum ada purchase order yang bisa ditagihkan.' : 'Daftar purchase order belum bisa ditampilkan.' }}
            </p>
            <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                Tagihan diajukan atas purchase order yang sudah Anda sanggupi.
            </p>
            <a href="{{ route('vendor.po.index') }}" class="btn-secondary mt-4">Lihat Purchase Order</a>
        </div>
    @else

    <form action="{{ route('vendor.tagihan.store') }}" method="POST" enctype="multipart/form-data"
          x-data="{
              po: {{ Js::from(collect($orders)->keyBy('id')) }},
              dipilih: '{{ old('purchase_order_id', $terpilih ?: '') }}',
              items: {{ Js::from(old('items', [['description' => '', 'quantity' => '', 'price' => '']])) }},
              get pesanan() { return this.po[this.dipilih] ?? null; },
              get total() {
                  return this.items.reduce((t, i) => t + (Number(i.quantity) || 0) * (Number(i.price) || 0), 0);
              },
              get selisih() {
                  return this.pesanan ? this.total - Number(this.pesanan.grand_total) : 0;
              },
              tambah() { this.items.push({ description: '', quantity: '', price: '' }); },
              hapus(i) { if (this.items.length > 1) this.items.splice(i, 1); },
              isiDariPo() {
                  if (!this.pesanan) return;
                  this.items = this.pesanan.items.map(it => ({
                      description: it.description,
                      quantity: it.quantity_ordered,
                      price: it.unit_price,
                  }));
              }
          }"
          class="erp-card space-y-4">
        @csrf

        <div>
            <label class="erp-label">Purchase order yang ditagihkan <span class="text-red-500">*</span></label>
            <select name="purchase_order_id" x-model="dipilih" @change="isiDariPo()" required class="erp-input">
                <option value="">— Pilih purchase order —</option>
                @foreach($orders as $po)
                    <option value="{{ $po['id'] }}">
                        {{ $po['po_number'] }} — Rp {{ number_format($po['grand_total'], 0, ',', '.') }}
                    </option>
                @endforeach
            </select>
        </div>

        <template x-if="pesanan">
            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700">
                <div class="flex justify-between text-[11px]">
                    <span class="text-slate-500">Nilai purchase order</span>
                    <span class="font-mono text-slate-700 dark:text-slate-300"
                          x-text="'Rp ' + Number(pesanan.grand_total).toLocaleString('id-ID')"></span>
                </div>
                <input type="hidden" name="po_number" :value="pesanan.po_number">
            </div>
        </template>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="erp-label">Nomor faktur Anda <span class="text-red-500">*</span></label>
                <input type="text" name="bill_number" value="{{ old('bill_number') }}" required class="erp-input"
                       placeholder="Sesuai faktur yang Anda terbitkan">
            </div>
            <div>
                <label class="erp-label">Tanggal faktur <span class="text-red-500">*</span></label>
                <input type="date" name="bill_date" value="{{ old('bill_date', date('Y-m-d')) }}" required
                       max="{{ date('Y-m-d') }}" class="erp-input">
            </div>
        </div>

        <div>
            <label class="erp-label">Jatuh tempo pembayaran</label>
            <input type="date" name="due_date" value="{{ old('due_date') }}" class="erp-input">
            <p class="text-[10px] text-slate-400 mt-1">Opsional. Bila kosong, kami memakai termin yang disepakati.</p>
        </div>

        {{-- Rincian item --}}
        <div class="pt-4 border-t border-slate-100 dark:border-slate-700 space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="text-xs font-bold text-slate-800 dark:text-white">Rincian Tagihan</h3>
                <button type="button" @click="tambah()" class="text-[11px] font-semibold text-blue-500 hover:underline cursor-pointer">
                    + Tambah baris
                </button>
            </div>

            <template x-for="(item, i) in items" :key="i">
                <div class="grid grid-cols-12 gap-2 items-start">
                    <div class="col-span-12 sm:col-span-5">
                        <input type="text" :name="`items[${i}][description]`" x-model="item.description"
                               class="erp-input" placeholder="Nama barang / jasa">
                    </div>
                    <div class="col-span-5 sm:col-span-3">
                        <input type="number" :name="`items[${i}][quantity]`" x-model="item.quantity"
                               step="0.01" min="0.01" required class="erp-input" placeholder="Jumlah">
                    </div>
                    <div class="col-span-6 sm:col-span-3">
                        <input type="number" :name="`items[${i}][price]`" x-model="item.price"
                               step="0.01" min="0" required class="erp-input" placeholder="Harga satuan">
                    </div>
                    <div class="col-span-1 flex items-center justify-center pt-2">
                        <button type="button" @click="hapus(i)" x-show="items.length > 1"
                                class="text-slate-400 hover:text-red-500 cursor-pointer" aria-label="Hapus baris">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </template>

            <div class="flex justify-between items-center pt-2 border-t border-slate-100 dark:border-slate-700">
                <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Total rincian</span>
                <span class="text-sm font-bold font-mono text-slate-900 dark:text-white"
                      x-text="'Rp ' + total.toLocaleString('id-ID')"></span>
            </div>

            {{-- Peringatan selisih ditampilkan lebih dulu di sini supaya vendor
                 bisa memperbaikinya sendiri sebelum mengirim. ERP tetap
                 menghitung ulang dan menandainya. --}}
            <template x-if="pesanan && Math.abs(selisih) > 0.01">
                <div class="p-3 rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/30">
                    <p class="text-[11px] text-amber-700 dark:text-amber-400 leading-relaxed">
                        Total rincian Anda
                        <span x-text="selisih > 0 ? 'lebih besar' : 'lebih kecil'"></span>
                        <span class="font-mono font-semibold" x-text="'Rp ' + Math.abs(selisih).toLocaleString('id-ID')"></span>
                        dari nilai purchase order. Tagihan tetap bisa dikirim, tapi tim kami akan mengonfirmasinya lebih dulu.
                    </p>
                </div>
            </template>
        </div>

        <div class="pt-4 border-t border-slate-100 dark:border-slate-700">
            <label class="erp-label">Nominal tagihan <span class="text-red-500">*</span></label>
            <input type="number" name="amount" value="{{ old('amount') }}" required min="1" step="0.01"
                   class="erp-input" :placeholder="total > 0 ? total : 'Nominal total tagihan'">
            <p class="text-[10px] text-slate-400 mt-1">
                Nominal yang tertera pada faktur Anda, termasuk pajak bila ada.
            </p>
        </div>

        <div>
            <label class="erp-label">Faktur asli <span class="text-red-500">*</span></label>
            <input type="file" name="document" accept=".jpg,.jpeg,.png,.pdf" required class="erp-input">
            <p class="text-[10px] text-slate-400 mt-1">
                JPG, PNG, atau PDF, maksimal {{ round(config('portal.max_upload_kb') / 1024) }} MB.
                Rincian di atas adalah datanya; berkas ini buktinya.
            </p>
        </div>

        <div class="pt-4 flex justify-end gap-2 border-t border-slate-100 dark:border-slate-700">
            <a href="{{ route('vendor.po.index') }}" class="btn-secondary">Batal</a>
            <button type="submit" class="btn-primary">Kirim Tagihan</button>
        </div>
    </form>
    @endif
</div>
@endsection
