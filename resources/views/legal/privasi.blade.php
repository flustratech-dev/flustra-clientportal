@extends(auth()->check() ? 'layouts.app' : 'layouts.public')
@section('title', 'Kebijakan Privasi')
@section('page_title', 'Kebijakan Privasi')

@section('content')
<div class="erp-card space-y-5">
    <div>
        <h1 class="text-lg font-bold text-slate-800 dark:text-white">Kebijakan Privasi</h1>
        <p class="text-[11px] text-slate-400 mt-1">Terakhir diperbarui: {{ date('d F Y') }}</p>
    </div>

    <div class="rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/30 p-3">
        <p class="text-[11px] text-amber-700 dark:text-amber-400">
            <strong>Naskah sementara.</strong> Isi halaman ini masih kerangka dan perlu ditinjau
            sebelum portal dibuka untuk umum.
        </p>
    </div>

    <div class="space-y-4 text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
        <section>
            <h2 class="text-xs font-bold text-slate-800 dark:text-white mb-1">Data yang kami simpan</h2>
            <ul class="list-disc list-inside space-y-1">
                <li>Identitas akun: nama, email, nomor telepon, dan foto profil bila Anda mengunggahnya.</li>
                <li>Data perusahaan yang Anda ajukan: nama perusahaan, NPWP, alamat, kontak, dan rekening bank untuk vendor.</li>
                <li>Berkas yang Anda unggah: bukti transfer, faktur, surat jalan, dan dokumen pendukung lain.</li>
                <li>Catatan aktivitas: waktu masuk, alamat IP, dan jenis peramban, untuk keperluan keamanan.</li>
            </ul>
        </section>

        <section>
            <h2 class="text-xs font-bold text-slate-800 dark:text-white mb-1">Untuk apa data ini dipakai</h2>
            <p>Semata-mata untuk menjalankan hubungan kerja sama Anda dengan Flustra: memverifikasi identitas,
            memproses pembayaran dan tagihan, mengirimkan pesanan, serta memenuhi kewajiban pembukuan dan
            perpajakan. Kami tidak menjual data Anda dan tidak memakainya untuk iklan.</p>
        </section>

        <section>
            <h2 class="text-xs font-bold text-slate-800 dark:text-white mb-1">Siapa yang bisa melihatnya</h2>
            <p>Hanya Anda dan staf Flustra yang berwenang menangani urusan terkait. Akun mitra lain tidak
            pernah bisa melihat data Anda; pembatasan ini diterapkan di sisi server, bukan sekadar
            disembunyikan dari tampilan.</p>
        </section>

        <section>
            <h2 class="text-xs font-bold text-slate-800 dark:text-white mb-1">Berapa lama disimpan</h2>
            <p>Data transaksi disimpan selama hubungan kerja sama berjalan dan sesudahnya sepanjang diwajibkan
            ketentuan pembukuan dan perpajakan. Data pelamar kerja yang tidak diterima dihapus otomatis
            setelah 12 bulan.</p>
        </section>

        <section>
            <h2 class="text-xs font-bold text-slate-800 dark:text-white mb-1">Berkas unggahan</h2>
            <p>Berkas yang Anda unggah disimpan di penyimpanan tertutup, tidak dapat diakses lewat tautan
            langsung, dan hanya bisa dibuka melalui tautan bertanda tangan yang berlaku singkat.</p>
        </section>

        <section>
            <h2 class="text-xs font-bold text-slate-800 dark:text-white mb-1">Hak Anda</h2>
            <p>Anda berhak meminta salinan, koreksi, atau penghapusan data pribadi Anda. Hubungi kami lewat
            halaman <a href="{{ route('bantuan') }}" class="text-blue-500 hover:underline">Bantuan</a>.
            Sebagian data mungkin tetap kami simpan bila diwajibkan peraturan.</p>
        </section>
    </div>
</div>
@endsection
