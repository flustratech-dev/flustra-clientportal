{{--
    Bilah penanda & pemilih instan saat admin membuka halaman layanan mitra.
--}}
@php
    $user = auth()->user();
    $isAdmin = $user?->isAdmin();
    $lihatSebagai = \App\Services\KonteksMitra::sedangLihatSebagai()
        ? \App\Services\KonteksMitra::pilihanAdmin()
        : null;

    $isServicePage = request()->routeIs('layanan.*') || request()->routeIs('vendor.*');

    // Ambil daftar mitra terverifikasi hanya jika user adalah admin dan sedang di halaman layanan atau sedang aktif lihat sebagai
    $semuaMitra = ($isAdmin && ($isServicePage || $lihatSebagai))
        ? \App\Models\PartnerLink::where('status', 'verified')->whereNotNull('erp_partner_id')->orderBy('company_name')->get()
        : collect();
@endphp

@if($lihatSebagai)
    <div class="erp-card !p-3 mb-5 border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/40 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2.5 min-w-0">
            <span class="p-1.5 rounded-lg bg-amber-200/70 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300 shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
            </span>
            <div class="min-w-0">
                <p class="text-xs font-semibold text-amber-900 dark:text-amber-200 truncate">
                    Melihat sebagai <strong>{{ $lihatSebagai->company_name }}</strong> ({{ $lihatSebagai->partner_type_label }})
                    <span class="hidden sm:inline font-normal text-amber-700 dark:text-amber-400">&mdash; Mode Hanya Baca</span>
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            @if($semuaMitra->isNotEmpty())
                <form action="{{ route('admin.lihat-sebagai.pilih-inline') }}" method="POST" class="inline-flex">
                    @csrf
                    <select name="partner_link_id" onchange="this.form.submit()" class="erp-input !py-1 !px-2.5 !text-xs !w-auto cursor-pointer">
                        <option value="">-- Ganti Mitra --</option>
                        @foreach($semuaMitra as $m)
                            <option value="{{ $m->id }}" @selected($lihatSebagai->id === $m->id)>
                                {{ $m->company_name }} ({{ $m->partner_type_label }})
                            </option>
                        @endforeach
                    </select>
                </form>
            @endif

            <form action="{{ route('admin.lihat-sebagai.selesai') }}" method="POST" class="inline-flex">
                @csrf
                <button type="submit" class="px-2.5 py-1 rounded-xl text-xs font-bold bg-amber-200/80 hover:bg-amber-300 dark:bg-amber-900/60 dark:hover:bg-amber-800 text-amber-900 dark:text-amber-200 transition-colors cursor-pointer">
                    Selesai
                </button>
            </form>
        </div>
    </div>
@elseif($isAdmin && $isServicePage)
    <div class="erp-card !p-3 mb-5 border-blue-200 dark:border-blue-800 bg-blue-50/70 dark:bg-blue-950/30 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2.5 min-w-0">
            <span class="p-1.5 rounded-lg bg-blue-100 dark:bg-blue-900/50 text-blue-600 dark:text-blue-400 shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </span>
            <div class="min-w-0">
                <p class="text-xs font-semibold text-blue-900 dark:text-blue-200 truncate">
                    <strong>Mode Superadmin:</strong> Akses halaman terbuka.
                    <span class="font-normal text-blue-700 dark:text-blue-300">Pilih mitra di samping untuk memuat data:</span>
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            @if($semuaMitra->isNotEmpty())
                <form action="{{ route('admin.lihat-sebagai.pilih-inline') }}" method="POST" class="inline-flex">
                    @csrf
                    <select name="partner_link_id" onchange="this.form.submit()" class="erp-input !py-1 !px-2.5 !text-xs !w-auto cursor-pointer">
                        <option value="">-- Pilih Mitra (Lihat Sebagai) --</option>
                        @foreach($semuaMitra as $m)
                            <option value="{{ $m->id }}">
                                {{ $m->company_name }} ({{ $m->partner_type_label }})
                            </option>
                        @endforeach
                    </select>
                </form>
            @else
                <span class="text-xs text-slate-400">Belum ada mitra terverifikasi.</span>
            @endif
        </div>
    </div>
@endif
