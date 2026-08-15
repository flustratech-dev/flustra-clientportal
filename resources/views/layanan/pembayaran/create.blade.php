@extends('layouts.app')
@section('title', 'Konfirmasi Pembayaran')
@section('page_title', 'Konfirmasi Pembayaran')
@section('breadcrumb_title', 'Konfirmasi Pembayaran')

@section('content')
<div class="space-y-5 max-w-2xl">

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
            <svg class="w-12 h-12 mx-auto text-emerald-300 dark:text-emerald-700 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">
                {{ empty($erpError) ? 'Tidak ada tagihan yang perlu dibayar.' : 'Daftar tagihan belum bisa ditampilkan.' }}
            </p>
            <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                {{ empty($erpError)
                    ? 'Semua tagihan Anda sudah lunas. Terima kasih.'
                    : 'Konfirmasi pembayaran butuh daftar tagihan Anda. Coba lagi beberapa saat lagi.' }}
            </p>
            <a href="{{ route('layanan.tagihan.index') }}" class="btn-secondary mt-4">Lihat Tagihan</a>
        </div>
    @else

    <form action="{{ route('layanan.pembayaran.store') }}" method="POST" enctype="multipart/form-data"
          x-data="{
              invoices: {{ Js::from(collect($invoices)->keyBy('id')) }},
              dipilih: '{{ old('invoice_id', $terpilih ?: '') }}',
              get tagihan() { return this.invoices[this.dipilih] ?? null; },
              pilih() {
                  if (this.tagihan) { this.$refs.nominal.value = this.tagihan.remaining_amount; }
              }
          }"
          class="erp-card space-y-4">
        @csrf

        <div>
            <label class="erp-label">Tagihan yang dibayar <span class="text-red-500">*</span></label>
            <select name="invoice_id" x-model="dipilih" @change="pilih()" required class="erp-input">
                <option value="">— Pilih tagihan —</option>
                @foreach($invoices as $i)
                    <option value="{{ $i['id'] }}">
                        {{ $i['invoice_number'] }} — sisa Rp {{ number_format($i['remaining_amount'], 0, ',', '.') }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Kotak konteks: rincian tagihan yang sedang dipilih --}}
        <template x-if="tagihan">
            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700 space-y-1">
                <div class="flex justify-between text-[11px]">
                    <span class="text-slate-500">Total tagihan</span>
                    <span class="font-mono text-slate-700 dark:text-slate-300"
                          x-text="'Rp ' + Number(tagihan.grand_total).toLocaleString('id-ID')"></span>
                </div>
                <div class="flex justify-between text-[11px]">
                    <span class="text-slate-500">Sudah dibayar</span>
                    <span class="font-mono text-slate-700 dark:text-slate-300"
                          x-text="'Rp ' + Number(tagihan.paid_amount).toLocaleString('id-ID')"></span>
                </div>
                <div class="flex justify-between text-xs font-bold pt-1 border-t border-slate-200 dark:border-slate-700">
                    <span class="text-slate-700 dark:text-slate-200">Sisa</span>
                    <span class="font-mono text-slate-900 dark:text-white"
                          x-text="'Rp ' + Number(tagihan.remaining_amount).toLocaleString('id-ID')"></span>
                </div>
                <input type="hidden" name="invoice_number" :value="tagihan.invoice_number">
            </div>
        </template>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="erp-label">Nominal yang ditransfer <span class="text-red-500">*</span></label>
                <input type="number" name="amount" x-ref="nominal" value="{{ old('amount') }}" required min="1" step="0.01"
                       class="erp-input" placeholder="Contoh: 5000000">
                <p class="text-[10px] text-slate-400 mt-1">Isi sesuai nominal yang benar-benar Anda transfer.</p>
            </div>
            <div>
                <label class="erp-label">Tanggal pembayaran <span class="text-red-500">*</span></label>
                <input type="date" name="payment_date" value="{{ old('payment_date', date('Y-m-d')) }}" required
                       max="{{ date('Y-m-d') }}" class="erp-input">
            </div>
        </div>

        <div>
            <label class="erp-label">Metode pembayaran <span class="text-red-500">*</span></label>
            <select name="payment_method" required class="erp-input">
                @foreach($metode as $key => $label)
                    <option value="{{ $key }}" @selected(old('payment_method') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="erp-label">Bukti transfer <span class="text-red-500">*</span></label>
            <input type="file" name="proof_file" accept=".jpg,.jpeg,.png,.pdf" required class="erp-input">
            <p class="text-[10px] text-slate-400 mt-1">
                JPG, PNG, atau PDF, maksimal {{ round(config('portal.max_upload_kb') / 1024) }} MB.
                Pastikan nominal dan tanggalnya terbaca jelas.
            </p>
        </div>

        <div>
            <label class="erp-label">Catatan</label>
            <textarea name="notes" rows="2" class="erp-input"
                      placeholder="Opsional — misalnya nomor referensi transfer.">{{ old('notes') }}</textarea>
        </div>

        <div class="pt-4 flex justify-end gap-2 border-t border-slate-100 dark:border-slate-700">
            <a href="{{ route('layanan.tagihan.index') }}" class="btn-secondary">Batal</a>
            <button type="submit" class="btn-primary">Kirim Konfirmasi</button>
        </div>
    </form>
    @endif
</div>
@endsection
