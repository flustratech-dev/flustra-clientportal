{{--
    Bilah penanda saat admin sedang melihat sebagai mitra lain.

    Menyala di setiap halaman dengan sengaja. Admin yang lupa sedang melihat
    data siapa akan salah menyimpulkan — "kok tagihan saya begini" padahal itu
    tagihan orang lain. Warnanya dibuat mencolok justru supaya mengganggu.
--}}
@php
    $lihatSebagai = \App\Services\KonteksMitra::sedangLihatSebagai()
        ? \App\Services\KonteksMitra::pilihanAdmin()
        : null;
@endphp

@if($lihatSebagai)
    <div class="erp-card !p-3 mb-4 border-amber-300 dark:border-amber-700 bg-amber-100 dark:bg-amber-950/50 flex flex-wrap items-center justify-between gap-2">
        <p class="text-[11px] font-semibold text-amber-800 dark:text-amber-300 flex items-center gap-2 min-w-0">
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
            <span class="truncate">
                Melihat sebagai <strong>{{ $lihatSebagai->company_name }}</strong> — hanya baca, aksi kirim dinonaktifkan
            </span>
        </p>
        <form action="{{ route('admin.lihat-sebagai.selesai') }}" method="POST" class="shrink-0">
            @csrf
            <button type="submit" class="text-[11px] font-bold text-amber-800 dark:text-amber-300 hover:underline cursor-pointer">
                Selesai
            </button>
        </form>
    </div>
@endif
