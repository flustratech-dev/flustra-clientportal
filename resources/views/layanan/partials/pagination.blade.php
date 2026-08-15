{{--
    Penomoran halaman untuk daftar yang datang dari ERP.

    Tidak bisa memakai $paginator->links() bawaan Laravel: datanya array hasil
    panggilan HTTP, bukan hasil query Eloquent. Yang ada hanya meta dari ERP.
--}}
@php
    $halaman = (int) ($meta['current_page'] ?? 1);
    $terakhir = (int) ($meta['last_page'] ?? 1);
    $total = (int) ($meta['total'] ?? 0);
@endphp

@if($terakhir > 1)
    <div class="flex items-center justify-between gap-2 text-xs">
        <span class="text-slate-400 text-[11px]">
            Halaman {{ $halaman }} dari {{ $terakhir }} &middot; {{ $total }} data
        </span>

        <div class="flex gap-1.5">
            @if($halaman > 1)
                <a href="{{ request()->fullUrlWithQuery(['page' => $halaman - 1]) }}" class="btn-secondary !py-1">Sebelumnya</a>
            @endif
            @if($halaman < $terakhir)
                <a href="{{ request()->fullUrlWithQuery(['page' => $halaman + 1]) }}" class="btn-secondary !py-1">Berikutnya</a>
            @endif
        </div>
    </div>
@endif
