{{-- Ditampilkan saat data tidak bisa diambil dari sistem internal.
     Halamannya tetap terbuka; yang kosong hanya isinya. --}}
@if(!empty($erpError))
    <div class="erp-card !p-3.5 border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/30 flex gap-2.5">
        <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/>
        </svg>
        <p class="text-xs text-amber-700 dark:text-amber-400 leading-relaxed">{{ $erpError }}</p>
    </div>
@endif
