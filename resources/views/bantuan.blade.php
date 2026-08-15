@php
    $masuk = auth()->check();
    $email = config('portal.contact.email');
    $wa    = config('portal.contact.whatsapp');
@endphp

@extends($masuk ? 'layouts.app' : 'layouts.public')
@section('title', 'Bantuan')
@section('page_title', 'Bantuan')
@section('breadcrumb_title', 'Bantuan')

@section('content')
<div class="space-y-5 max-w-2xl">

    <div>
        <h1 class="text-lg font-bold text-slate-800 dark:text-white">Ada yang bisa kami bantu?</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
            Sebagian besar pertanyaan sudah terjawab di bawah. Kalau belum, hubungi kami langsung.
        </p>
    </div>

    {{-- Kontak --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        @if($wa)
        <a href="https://wa.me/{{ preg_replace('/\D/', '', $wa) }}" target="_blank" rel="noopener noreferrer"
           class="erp-card floating-card flex items-center gap-3 hover:border-emerald-300 dark:hover:border-emerald-700">
            <span class="w-9 h-9 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 4v-4z"/>
                </svg>
            </span>
            <span class="min-w-0">
                <span class="block text-xs font-bold text-slate-800 dark:text-white">WhatsApp</span>
                <span class="block text-[11px] text-slate-500 truncate">{{ $wa }}</span>
            </span>
        </a>
        @endif

        @if($email)
        <a href="mailto:{{ $email }}"
           class="erp-card floating-card flex items-center gap-3 hover:border-blue-300 dark:hover:border-blue-700">
            <span class="w-9 h-9 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </span>
            <span class="min-w-0">
                <span class="block text-xs font-bold text-slate-800 dark:text-white">Email</span>
                <span class="block text-[11px] text-slate-500 truncate">{{ $email }}</span>
            </span>
        </a>
        @endif
    </div>

    {{-- FAQ --}}
    <div class="erp-card !p-0 divide-y divide-slate-100 dark:divide-slate-800" x-data="{ buka: null }">
        @foreach([
            ['Kenapa saya belum bisa melihat tagihan saya?',
             'Akun baru bertipe Umum. Untuk membuka data tagihan, penawaran, dan pengiriman, ajukan verifikasi sebagai pelanggan lewat menu Ajukan Kerja Sama. Kami perlu memastikan Anda memang mewakili perusahaan tersebut sebelum membuka datanya.'],
            ['Berapa lama verifikasi kerja sama diproses?',
             'Biasanya satu hari kerja. Pemeriksaannya dilakukan orang, bukan otomatis, karena yang dibuka adalah data keuangan perusahaan Anda.'],
            ['Bukti apa yang bisa saya pakai untuk verifikasi?',
             'Salah satu dari: nomor invoice terakhir dari kami, nomor purchase order, kode undangan yang kami berikan, atau NPWP perusahaan. Cukup satu.'],
            ['Pengajuan saya ditolak. Apakah akun saya hangus?',
             'Tidak. Akun Anda tetap aktif dan Anda boleh mengajukan lagi kapan saja dengan bukti yang lebih sesuai. Alasan penolakan bisa dilihat di halaman Riwayat.'],
            ['Saya perusahaan yang sekaligus pelanggan dan vendor. Perlu dua akun?',
             'Tidak perlu. Satu akun bisa diverifikasi untuk kedua peran, dan Anda tinggal berpindah tampilan lewat halaman Profil.'],
            ['Bagaimana saya tahu pengajuan saya sudah diproses?',
             'Setiap pengajuan punya nomor dan halaman status sendiri di menu Riwayat, lengkap dengan riwayat prosesnya. Anda juga mendapat notifikasi setiap ada perubahan.'],
            ['Apakah data saya bisa dilihat perusahaan lain?',
             'Tidak. Setiap akun hanya bisa membuka data mitra yang sudah diverifikasi untuknya, dan pembatasannya diterapkan di sisi server, bukan sekadar disembunyikan di tampilan.'],
        ] as $i => [$tanya, $jawab])
            <div>
                <button @click="buka = buka === {{ $i }} ? null : {{ $i }}"
                        class="w-full flex items-center justify-between gap-3 px-4 py-3.5 text-left hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors cursor-pointer">
                    <span class="text-xs font-semibold text-slate-800 dark:text-white">{{ $tanya }}</span>
                    <svg class="w-4 h-4 text-slate-400 shrink-0 transition-transform" :class="buka === {{ $i }} ? 'rotate-180' : ''"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="buka === {{ $i }}" x-cloak
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="px-4 pb-4">
                    <p class="text-[11px] text-slate-600 dark:text-slate-400 leading-relaxed">{{ $jawab }}</p>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Form pertanyaan. Hanya untuk yang sudah masuk: jawabannya dikirim ke
         halaman Riwayat miliknya sendiri, jadi harus jelas siapa penanyanya. --}}
    @if($masuk)
        <div class="erp-card">
            <h3 class="text-xs font-bold text-slate-800 dark:text-white">Masih ada yang ingin ditanyakan?</h3>
            <p class="text-[11px] text-slate-500 mt-1 mb-4">
                Tulis di sini. Jawaban tim kami akan muncul di halaman
                <a href="{{ route('riwayat.index') }}" class="text-blue-500 hover:underline">Riwayat</a> Anda,
                jadi tidak perlu menunggu balasan email.
            </p>

            @if($errors->any())
                <div class="mb-3 p-3 rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/40">
                    <ul class="text-xs text-red-700 dark:text-red-400 space-y-1">
                        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('umum.pertanyaan.store') }}" method="POST" class="space-y-3">
                @csrf
                <div>
                    <label class="erp-label">Pokok pertanyaan <span class="text-red-500">*</span></label>
                    <input type="text" name="subject" value="{{ old('subject') }}" required class="erp-input"
                           placeholder="Contoh: Invoice INV/2026/08/9001 belum muncul di portal">
                </div>
                <div>
                    <label class="erp-label">Penjelasan <span class="text-red-500">*</span></label>
                    <textarea name="message" rows="4" required class="erp-input"
                              placeholder="Jelaskan sedetail yang Anda bisa agar tim kami tidak perlu bertanya balik.">{{ old('message') }}</textarea>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="btn-primary">Kirim Pertanyaan</button>
                </div>
            </form>
        </div>
    @endif

    @unless($masuk)
        <div class="text-center">
            <a href="{{ route('welcome') }}" class="btn-secondary">&larr; Kembali ke beranda</a>
        </div>
    @endunless

</div>
@endsection
