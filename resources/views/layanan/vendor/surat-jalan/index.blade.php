@extends('layouts.app')
@section('title', 'Dokumen Pengiriman')
@section('page_title', 'Dokumen Pengiriman')
@section('lebar', 'max-w-2xl mx-auto')

@section('content')
<div class="space-y-5 max-w-2xl mx-auto">

    @include('partials.erp-offline')

    @if($errors->any())
        <div class="erp-card !p-3 border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/40">
            <ul class="text-xs text-red-700 dark:text-red-400 space-y-1">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="erp-card !p-3.5 border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-950/30">
        <p class="text-xs text-blue-700 dark:text-blue-400 leading-relaxed">
            Kirimkan surat jalan sebelum barangnya tiba, supaya tim gudang kami bisa menyiapkan penerimaan.
            Ini bukan pencatatan penerimaan barang — jumlah yang diterima tetap dihitung tim kami saat barangnya sampai.
        </p>
    </div>

    <form action="{{ route('vendor.surat-jalan.store') }}" method="POST" enctype="multipart/form-data"
          x-data="{
              po: {{ Js::from(collect($orders)->keyBy('id')) }},
              dipilih: '{{ old('purchase_order_id', '') }}',
              get pesanan() { return this.po[this.dipilih] ?? null; }
          }"
          class="erp-card space-y-4">
        @csrf

        <div>
            <label class="erp-label">Purchase order terkait</label>
            <select name="purchase_order_id" x-model="dipilih" class="erp-input">
                <option value="">— Tidak terkait PO tertentu —</option>
                @foreach($orders as $po)
                    <option value="{{ $po['id'] }}">{{ $po['po_number'] }}</option>
                @endforeach
            </select>
            <input type="hidden" name="po_number" :value="pesanan ? pesanan.po_number : ''">
            <p class="text-[10px] text-slate-400 mt-1">
                Menyebutkan PO-nya membantu gudang mencocokkan kiriman lebih cepat.
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="erp-label">Nomor surat jalan <span class="text-red-500">*</span></label>
                <input type="text" name="document_number" value="{{ old('document_number') }}" required class="erp-input"
                       placeholder="Sesuai surat jalan Anda">
            </div>
            <div>
                <label class="erp-label">Tanggal kirim <span class="text-red-500">*</span></label>
                <input type="date" name="shipped_date" value="{{ old('shipped_date', date('Y-m-d')) }}" required
                       max="{{ date('Y-m-d') }}" class="erp-input">
            </div>
            <div>
                <label class="erp-label">Perkiraan tiba</label>
                <input type="date" name="estimated_arrival" value="{{ old('estimated_arrival') }}" class="erp-input">
            </div>
            <div>
                <label class="erp-label">Kurir / ekspedisi</label>
                <input type="text" name="courier" value="{{ old('courier') }}" class="erp-input" placeholder="Contoh: Kirim sendiri, JNE Trucking">
            </div>
        </div>

        <div>
            <label class="erp-label">Nomor resi / kendaraan</label>
            <input type="text" name="tracking_number" value="{{ old('tracking_number') }}" class="erp-input">
        </div>

        <div>
            <label class="erp-label">Salinan surat jalan</label>
            <input type="file" name="document" accept=".jpg,.jpeg,.png,.pdf" class="erp-input">
            <p class="text-[10px] text-slate-400 mt-1">
                Opsional. JPG, PNG, atau PDF, maksimal {{ round(config('portal.max_upload_kb') / 1024) }} MB.
            </p>
        </div>

        <div>
            <label class="erp-label">Catatan</label>
            <textarea name="notes" rows="2" class="erp-input"
                      placeholder="Opsional. Contoh: dikirim dalam 2 kali pengiriman.">{{ old('notes') }}</textarea>
        </div>

        <div class="pt-4 flex justify-end gap-2 border-t border-slate-100 dark:border-slate-700">
            <a href="{{ route('beranda') }}" class="btn-secondary">Batal</a>
            <button type="submit" class="btn-primary">Kirim Surat Jalan</button>
        </div>
    </form>

    @if(!empty($documents))
        <div class="erp-card">
            <h3 class="text-xs font-bold text-slate-800 dark:text-white mb-3">Surat Jalan yang Pernah Anda Kirim</h3>
            <div class="space-y-2">
                @foreach($documents as $d)
                    <div class="flex flex-wrap items-center justify-between gap-2 p-3 rounded-xl border border-slate-100 dark:border-slate-700">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold text-slate-800 dark:text-white font-mono">{{ $d['document_number'] }}</p>
                            <p class="text-[11px] text-slate-500">
                                {{ $d['shipped_date'] ? \Illuminate\Support\Carbon::parse($d['shipped_date'])->format('d M Y') : '—' }}
                                @if($d['po_number']) &middot; {{ $d['po_number'] }} @endif
                                @if($d['courier']) &middot; {{ $d['courier'] }} @endif
                            </p>
                        </div>
                        @include('layanan.partials.status-badge', [
                            'status' => $d['status'] === 'matched' ? 'delivered' : 'shipped',
                            'label'  => $d['status_label'],
                        ])
                    </div>
                @endforeach
            </div>

            @include('layanan.partials.pagination', ['meta' => $meta])
        </div>
    @endif
</div>
@endsection
