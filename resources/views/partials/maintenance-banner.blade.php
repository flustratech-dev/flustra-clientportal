{{--
    Banner pemberitahuan pemeliharaan.

    Dua sumber: dinyalakan admin portal sendiri, atau didorong dari flustra-erp
    lewat webhook `maintenance.changed`. Lihat App\Services\Maintenance.

    Disisipkan di layout aplikasi DAN layout publik: mitra yang belum masuk pun
    perlu tahu kalau layanan sedang terganggu — kalau tidak, ia akan mencoba
    masuk berkali-kali dan mengira akunnya yang bermasalah.
--}}
@php
    $pemberitahuan = \App\Services\Maintenance::aktif();
@endphp

@if($pemberitahuan)
    <div class="erp-card !p-3.5 mb-4 {{ \App\Services\Maintenance::warna($pemberitahuan['tingkat']) }}">
        <div class="flex items-start gap-2.5">
            <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/>
            </svg>
            <div class="min-w-0">
                <p class="text-xs font-bold">{{ $pemberitahuan['judul'] }}</p>
                <p class="text-[11px] leading-relaxed mt-0.5 opacity-90">{{ $pemberitahuan['pesan'] }}</p>

                @if($pemberitahuan['jadwal'])
                    <p class="text-[10px] mt-1 opacity-75">
                        Dijadwalkan:
                        {{ \Illuminate\Support\Carbon::parse($pemberitahuan['jadwal'])->translatedFormat('d F Y, H:i') }} WIB
                    </p>
                @endif
            </div>
        </div>
    </div>
@endif
