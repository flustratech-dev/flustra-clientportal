@extends(auth()->check() ? 'layouts.app' : 'layouts.public')
@section('title', 'Syarat & Ketentuan')
@section('page_title', 'Syarat & Ketentuan')

@section('content')
<div class="erp-card space-y-5">
    <div>
        <h1 class="text-lg font-bold text-slate-800 dark:text-white">Syarat &amp; Ketentuan</h1>
        <p class="text-[11px] text-slate-400 mt-1">Terakhir diperbarui: {{ date('d F Y') }}</p>
    </div>

    {{-- Naskah ini kerangka kerja, BUKAN dokumen final. Perlu ditinjau bagian
         hukum/manajemen sebelum portal dibuka ke publik. --}}
    <div class="rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/30 p-3">
        <p class="text-[11px] text-amber-700 dark:text-amber-400">
            <strong>Naskah sementara.</strong> Isi halaman ini masih kerangka dan perlu ditinjau
            sebelum portal dibuka untuk umum.
        </p>
    </div>

    <div class="space-y-4 text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
        <section>
            <h2 class="text-xs font-bold text-slate-800 dark:text-white mb-1">1. Tentang Layanan Ini</h2>
            <p>Portal Klien Flustra adalah sarana bagi pelanggan, vendor, dan calon mitra untuk berurusan
            dengan Flustra secara mandiri: mengonfirmasi pembayaran, menyetujui penawaran, mengirim tagihan,
            memantau pengiriman, dan memperbarui data perusahaan.</p>
        </section>

        <section>
            <h2 class="text-xs font-bold text-slate-800 dark:text-white mb-1">2. Akun Anda</h2>
            <p>Anda bertanggung jawab menjaga kerahasiaan kata sandi dan seluruh aktivitas yang terjadi di
            akun Anda. Beri tahu kami segera bila Anda menduga akun Anda diakses pihak lain. Satu akun
            mewakili satu orang; jangan berbagi kredensial dengan rekan kerja — mintakan akun terpisah.</p>
        </section>

        <section>
            <h2 class="text-xs font-bold text-slate-800 dark:text-white mb-1">3. Verifikasi Mitra</h2>
            <p>Pendaftaran terbuka untuk siapa saja, tetapi akses ke data perusahaan hanya diberikan setelah
            kami memverifikasi bahwa Anda berhak mewakili perusahaan tersebut. Kami berhak menolak,
            menunda, atau mencabut verifikasi bila bukti yang diberikan tidak memadai atau hubungan kerja
            sama berakhir.</p>
        </section>

        <section>
            <h2 class="text-xs font-bold text-slate-800 dark:text-white mb-1">4. Kebenaran Data</h2>
            <p>Data yang Anda kirim melalui portal ini — termasuk bukti pembayaran, tagihan, dan dokumen
            pendukung — dianggap Anda sampaikan dengan benar. Memberikan keterangan palsu dapat
            mengakibatkan penangguhan akun dan konsekuensi hukum.</p>
        </section>

        <section>
            <h2 class="text-xs font-bold text-slate-800 dark:text-white mb-1">5. Status Pengajuan</h2>
            <p>Pengajuan yang Anda kirim melalui portal berstatus permohonan dan memerlukan pemeriksaan tim
            kami. Terkirimnya sebuah pengajuan tidak dengan sendirinya berarti disetujui.</p>
        </section>

        <section>
            <h2 class="text-xs font-bold text-slate-800 dark:text-white mb-1">6. Perubahan Ketentuan</h2>
            <p>Kami dapat memperbarui ketentuan ini sewaktu-waktu. Perubahan yang berdampak besar akan kami
            beri tahukan lewat notifikasi di portal.</p>
        </section>
    </div>
</div>
@endsection
