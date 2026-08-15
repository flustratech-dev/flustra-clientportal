{{--
    Lencana status untuk data yang datang dari ERP.

    Kelasnya ditulis utuh di sini, bukan dirangkai dari nama warna yang dikirim
    ERP. Tailwind memindai berkas sumber sebagai teks: kelas yang baru terbentuk
    saat runtime (`bg-{{ $warna }}-50`) tidak pernah ikut ter-generate, dan
    lencananya jadi polos tanpa ada yang sadar sampai dilihat orang.

    Dipakai: $status (kode dari ERP), $label (teks yang ditampilkan).
--}}
@php
    $kelas = match ($status) {
        'paid', 'accepted', 'delivered', 'completed', 'active', 'verified'
            => 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400',
        'sent', 'shipped', 'confirmed', 'processing'
            => 'bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400',
        'partial', 'pending', 'ready', 'pending_review'
            => 'bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400',
        'overdue', 'rejected', 'disputed'
            => 'bg-red-50 dark:bg-red-950/40 text-red-600 dark:text-red-400',
        default
            => 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400',
    };
@endphp
<span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $kelas }}">{{ $label }}</span>
