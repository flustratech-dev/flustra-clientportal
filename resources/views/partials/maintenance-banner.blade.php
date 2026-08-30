{{--
    Banner pemberitahuan pemeliharaan floating top (Sama persis dengan Flustra Office).

    Dua sumber: dinyalakan admin portal sendiri, atau didorong dari flustra-erp
    lewat webhook `maintenance.changed`. Lihat App\Services\Maintenance.
--}}
@php
    $pemberitahuan = \App\Services\Maintenance::aktif();
@endphp

@if($pemberitahuan)
    <div class="fixed top-6 inset-x-0 z-[9999] flex justify-center pointer-events-none px-4">
        <div x-data="{
                show: false,
                closed: sessionStorage.getItem('portal_maintenance_dismissed_{{ md5($pemberitahuan['judul'].$pemberitahuan['pesan']) }}') === '1',
                dismiss() {
                    this.show = false;
                    sessionStorage.setItem('portal_maintenance_dismissed_{{ md5($pemberitahuan['judul'].$pemberitahuan['pesan']) }}', '1');
                }
             }"
             x-init="if(!closed) { setTimeout(() => show = true, 100) }"
             x-show="show"
             x-transition:enter="transition-all ease-out duration-700"
             x-transition:enter-start="opacity-0 -translate-y-24 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition-all ease-in duration-500"
             x-transition:leave-start="opacity-100 translate-y-0 scale-100"
             x-transition:leave-end="opacity-0 -translate-y-24 scale-95"
             class="pointer-events-auto w-full max-w-xl rounded-2xl shadow-2xl shadow-black/20 overflow-hidden border border-white/20 @if($pemberitahuan['tingkat'] === 'critical' || $pemberitahuan['tingkat'] === 'error') bg-gradient-to-br from-red-600 to-red-800 @elseif($pemberitahuan['tingkat'] === 'warning') bg-gradient-to-br from-amber-500 to-amber-700 @else bg-gradient-to-br from-blue-600 to-blue-800 @endif text-white"
             x-cloak>

            <div class="px-5 py-4 relative">
                {{-- Decorative background glow --}}
                <div class="absolute -top-10 -right-10 w-32 h-32 bg-white/10 rounded-full blur-2xl pointer-events-none"></div>

                <div class="flex items-start justify-between gap-4 relative z-10">
                    <div class="flex items-start gap-3.5">
                        {{-- Severity Icon --}}
                        <div class="shrink-0 mt-0.5 bg-white/20 p-2 rounded-xl">
                            @if($pemberitahuan['tingkat'] === 'critical' || $pemberitahuan['tingkat'] === 'error')
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" /></svg>
                            @elseif($pemberitahuan['tingkat'] === 'warning')
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            @else
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            @endif
                        </div>

                        {{-- Banner Content --}}
                        <div class="flex-1 min-w-0 pr-2">
                            <h4 class="text-sm font-bold text-white leading-tight drop-shadow-sm">{{ $pemberitahuan['judul'] }}</h4>
                            <p class="text-xs text-white/90 mt-1 leading-relaxed">{{ $pemberitahuan['pesan'] }}</p>

                            <div class="flex flex-wrap items-center gap-2 mt-2.5">
                                @if($pemberitahuan['jadwal'])
                                    <span class="inline-flex items-center gap-1.5 text-[10px] font-semibold text-white bg-white/20 px-2 py-0.5 rounded-lg backdrop-blur-sm border border-white/10">
                                        📅 {{ \Illuminate\Support\Carbon::parse($pemberitahuan['jadwal'])->translatedFormat('d F Y, H:i') }} WIB
                                    </span>
                                @endif
                                <span class="inline-flex items-center gap-1 text-[9px] font-bold uppercase tracking-wider text-white bg-white/20 px-2 py-0.5 rounded-lg backdrop-blur-sm border border-white/10">
                                    🔧 {{ $pemberitahuan['sumber'] === 'erp' ? 'Sistem ERP' : 'Portal Klien' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <button @click="dismiss()" class="shrink-0 text-white/70 hover:text-white hover:bg-white/20 p-1.5 rounded-lg transition-colors focus:outline-none cursor-pointer" aria-label="Tutup pemberitahuan">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
