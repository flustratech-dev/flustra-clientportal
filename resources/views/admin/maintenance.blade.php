@extends('layouts.app')

@section('title', 'Pemeliharaan Sistem')
@section('page_title', 'Pemeliharaan Sistem')
@section('lebar', 'max-w-5xl mx-auto')

@section('content')
<div class="space-y-6 max-w-5xl mx-auto py-2"
     x-data="{
         bannerActive: {{ ($systemSettings['maintenance_banner_active'] ?? '0') === '1' || ($systemSettings['maintenance_active'] ?? '0') === '1' ? 'true' : 'false' }},
         lockdownActive: {{ ($systemSettings['maintenance_lockdown'] ?? '0') === '1' ? 'true' : 'false' }},
         emailSent: {{ ($systemSettings['maintenance_email_sent'] ?? '0') === '1' ? 'true' : 'false' }},
         emailSending: false,
         waSent: {{ ($systemSettings['maintenance_wa_sent'] ?? '0') === '1' ? 'true' : 'false' }},
         waSending: false,
         bannerToggling: false,
         lockdownToggling: false,
         severity: '{{ $systemSettings['maintenance_severity'] ?? 'info' }}',
         title: '{{ addslashes($systemSettings['maintenance_title'] ?? '') }}',

         toggleBanner() {
             if (this.bannerActive && this.title.trim() === '') {
                 this.bannerActive = false;
                 Swal.fire('Peringatan', 'Harap isi dan <b>Simpan Konfigurasi Maintenance</b> (Judul, Deskripsi, Jadwal) terlebih dahulu sebelum mengaktifkan banner.', 'warning');
                 return;
             }

             this.bannerToggling = true;
             fetch('{{ route("admin.maintenance.banner") }}', {
                 method: 'POST',
                 headers: {
                     'Content-Type': 'application/json',
                     'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                     'Accept': 'application/json'
                 },
                 body: JSON.stringify({ is_active: this.bannerActive })
             })
             .then(r => r.json())
             .then(data => {
                 this.bannerToggling = false;
                 if (data.success) {
                     const alpineRoot = document.documentElement.__x_data;
                     if (alpineRoot && alpineRoot.toasts) {
                         alpineRoot.toasts.push({
                             id: Date.now(),
                             title: 'Banner Maintenance',
                             body: this.bannerActive ? 'Pop-up banner maintenance berhasil diaktifkan.' : 'Pop-up banner maintenance berhasil dinonaktifkan.',
                             visible: true
                         });
                     }
                     if (!this.bannerActive) {
                         this.emailSent = false;
                         this.waSent = false;
                     }
                 }
             })
             .catch(() => { this.bannerToggling = false; });
         },

         toggleLockdown() {
             if (this.lockdownActive) {
                 Swal.fire({
                     title: 'Konfirmasi Kunci Akses',
                     html: `<p class='text-sm text-slate-600'>Anda akan mengaktifkan <b>Lockdown Maintenance</b>.</p><p class='text-sm text-red-600 font-bold mt-2'>Hanya akun berstatus Superadmin yang dapat masuk (login) selama mode ini aktif!</p><p class='text-sm text-slate-600 mt-2'>Lanjutkan?</p>`,
                     icon: 'warning',
                     showCancelButton: true,
                     confirmButtonColor: '#ef4444',
                     confirmButtonText: 'Ya, Aktifkan Kunci!',
                     cancelButtonText: 'Batal'
                 }).then((result) => {
                     if (result.isConfirmed) {
                         this.executeToggleLockdown(true);
                     } else {
                         this.lockdownActive = false;
                     }
                 });
             } else {
                 this.executeToggleLockdown(false);
             }
         },

         executeToggleLockdown(isActive) {
             this.lockdownToggling = true;
             fetch('{{ route("admin.maintenance.lockdown") }}', {
                 method: 'POST',
                 headers: {
                     'Content-Type': 'application/json',
                     'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                     'Accept': 'application/json'
                 },
                 body: JSON.stringify({ is_active: isActive })
             })
             .then(r => r.json())
             .then(data => {
                 this.lockdownToggling = false;
                 if (data.success) {
                     const alpineRoot = document.documentElement.__x_data;
                     if (alpineRoot && alpineRoot.toasts) {
                         alpineRoot.toasts.push({
                             id: Date.now(),
                             title: 'Keamanan Sistem',
                             body: isActive ? 'Lockdown berhasil diaktifkan. Login dibekukan.' : 'Lockdown berhasil dinonaktifkan. Sistem normal.',
                             visible: true
                         });
                     }
                 } else {
                     Swal.fire('Gagal', data.error || 'Terjadi kesalahan.', 'error');
                     this.lockdownActive = !isActive;
                 }
             })
             .catch(() => {
                 this.lockdownToggling = false;
                 this.lockdownActive = !isActive;
             });
         },

         sendWA() {
             Swal.fire({
                 title: 'Konfirmasi Pengiriman WhatsApp',
                 html: 'Anda akan mengirim pesan WhatsApp pemberitahuan maintenance ke <b>seluruh pengguna aktif</b> yang memiliki nomor telepon.<br><br>Lanjutkan?',
                 icon: 'warning',
                 showCancelButton: true,
                 confirmButtonText: 'Ya, Kirim WA',
                 cancelButtonText: 'Batal',
                 confirmButtonColor: '#22c55e',
             }).then((result) => {
                 if (result.isConfirmed) {
                     this.waSending = true;
                     fetch('{{ route("admin.maintenance.wa") }}', {
                         method: 'POST',
                         headers: {
                             'Content-Type': 'application/json',
                             'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                             'Accept': 'application/json'
                         },
                         body: '{}'
                     })
                     .then(r => r.json())
                     .then(data => {
                         this.waSending = false;
                         if (data.success) {
                             this.waSent = true;
                             Swal.fire({
                                 title: 'WhatsApp Terkirim!',
                                 html: `Pesan WA maintenance berhasil dikirim ke <b>${data.count} pengguna</b>.`,
                                 icon: 'success',
                                 confirmButtonColor: '#22c55e',
                             });
                         } else {
                             Swal.fire('Gagal', data.error || 'Terjadi kesalahan.', 'error');
                         }
                     })
                     .catch(() => {
                         this.waSending = false;
                         Swal.fire('Gagal', 'Terjadi kesalahan saat mengirim pesan WhatsApp.', 'error');
                     });
                 }
             });
         },

         sendEmails() {
             Swal.fire({
                 title: 'Konfirmasi Pengiriman Email',
                 html: 'Anda akan mengirim email pemberitahuan maintenance ke <b>seluruh pengguna aktif</b>.<br><br>Lanjutkan?',
                 icon: 'warning',
                 showCancelButton: true,
                 confirmButtonText: 'Ya, Kirim Email',
                 cancelButtonText: 'Batal',
                 confirmButtonColor: '#2563eb',
             }).then((result) => {
                 if (result.isConfirmed) {
                     this.emailSending = true;
                     fetch('{{ route("admin.maintenance.email") }}', {
                         method: 'POST',
                         headers: {
                             'Content-Type': 'application/json',
                             'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                             'Accept': 'application/json'
                         },
                         body: '{}'
                     })
                     .then(r => r.json())
                     .then(data => {
                         this.emailSending = false;
                         if (data.success) {
                             this.emailSent = true;
                             Swal.fire({
                                 title: 'Email Terkirim!',
                                 html: `Email maintenance berhasil dikirim ke <b>${data.count} pengguna</b>.`,
                                 icon: 'success',
                                 confirmButtonColor: '#2563eb',
                             });
                         } else {
                             Swal.fire('Gagal', data.error || 'Terjadi kesalahan.', 'error');
                         }
                     })
                     .catch(() => {
                         this.emailSending = false;
                         Swal.fire('Gagal', 'Terjadi kesalahan saat mengirim email.', 'error');
                     });
                 }
             });
         },

         completeMaintenance() {
             Swal.fire({
                 title: 'Akhiri Maintenance Sistem?',
                 html: 'Tindakan ini akan:<br><ul class=\'text-left mt-2 space-y-1 text-sm list-disc pl-5\'><li>Mematikan Pop-up Banner</li><li>Mengirim Notifikasi Email Selesai (jika sebelumnya email aktif)</li><li>Mengirim Notifikasi WA Selesai (jika sebelumnya WA aktif)</li><li>Mengirim In-App Notification (Bel)</li></ul><br>Lanjutkan?',
                 icon: 'question',
                 showCancelButton: true,
                 confirmButtonText: 'Ya, Akhiri & Beritahu',
                 cancelButtonText: 'Batal',
                 confirmButtonColor: '#10b981',
             }).then((result) => {
                 if (result.isConfirmed) {
                     Swal.fire({
                         title: 'Memproses...',
                         html: 'Sedang mematikan banner dan mengirim notifikasi selesai...',
                         allowOutsideClick: false,
                         didOpen: () => { Swal.showLoading(); }
                     });

                     fetch('{{ route("admin.maintenance.complete") }}', {
                         method: 'POST',
                         headers: {
                             'Content-Type': 'application/json',
                             'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                             'Accept': 'application/json'
                         },
                         body: '{}'
                     })
                     .then(r => r.json())
                     .then(data => {
                         if (data.success) {
                             this.bannerActive = false;
                             this.emailSent = false;
                             this.waSent = false;
                             
                             Swal.fire({
                                 title: 'Maintenance Selesai!',
                                 html: `Sistem sudah berjalan normal.<br>Terkirim <b>${data.email_count} Email</b> dan <b>${data.wa_count} WA</b> notifikasi selesai.`,
                                 icon: 'success',
                                 confirmButtonColor: '#10b981',
                             }).then(() => {
                                 window.location.reload();
                             });
                         } else {
                             Swal.fire('Gagal', data.error || 'Terjadi kesalahan.', 'error');
                         }
                     })
                     .catch(() => {
                         Swal.fire('Gagal', 'Terjadi kesalahan saat memproses permintaan.', 'error');
                     });
                 }
             });
         }
     }">

    <div class="flex items-center justify-between">
        <a href="{{ route('admin.dashboard') }}" class="btn-secondary inline-flex items-center gap-2">
            &larr; Kembali ke Panel Admin
        </a>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/40 p-4 text-sm text-emerald-800 dark:text-emerald-300">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/40 p-4">
            <ul class="text-xs text-red-700 dark:text-red-400 space-y-1">
                @foreach($errors->all() as $e)<li>• {{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Pemberitahuan dari ERP (Baca Saja) --}}
    @if($dariErp['aktif'])
        <div class="rounded-2xl border border-amber-200 dark:border-amber-800 bg-amber-50/80 dark:bg-amber-950/30 p-5 shadow-sm">
            <div class="flex items-start gap-3">
                <span class="p-2 rounded-xl bg-amber-100 dark:bg-amber-900/50 text-amber-600 dark:text-amber-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" /></svg>
                </span>
                <div class="flex-1">
                    <p class="text-xs font-bold text-amber-800 dark:text-amber-300 uppercase tracking-wider">Pemberitahuan Aktif dari Flustra Office (Sistem ERP)</p>
                    <p class="text-sm font-semibold text-slate-900 dark:text-white mt-1">{{ $dariErp['judul'] }}</p>
                    <p class="text-xs text-slate-600 dark:text-slate-400 mt-1 leading-relaxed">{{ $dariErp['pesan'] }}</p>
                    @if($dariErp['jadwal'])
                        <p class="text-xs text-amber-700 dark:text-amber-400 mt-2 font-medium">
                            📅 Dijadwalkan: {{ \Carbon\Carbon::parse($dariErp['jadwal'])->translatedFormat('d F Y, H:i') }} WIB
                            @if($dariErp['durasi']) &bull; ⏱️ Durasi: {{ $dariErp['durasi'] }} @endif
                        </p>
                    @endif
                    <p class="text-[11px] text-slate-400 mt-2">Dikelola dan dimatikan langsung dari Flustra Office.</p>
                </div>
            </div>
        </div>
    @endif

    <!-- ==================== CARD 1: KONFIGURASI JADWAL MAINTENANCE ==================== -->
    <form action="{{ route('admin.maintenance.update') }}" method="POST"
          class="bg-white dark:bg-slate-800 rounded-3xl shadow-sm border border-slate-200/80 dark:border-slate-700/80 p-6 space-y-5">
        @csrf
        <div>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white mb-1">Konfigurasi Jadwal Maintenance</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                Atur jadwal pemeliharaan portal, deskripsi profesional, dan tingkat urgensi. Konfigurasi ini digunakan untuk banner notifikasi, pesan WhatsApp, dan email resmi.
            </p>
        </div>

        <!-- Row 1: Judul & Jadwal -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-1.5">
                <label for="maintenance_title" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Judul Maintenance</label>
                <input type="text" name="title" id="maintenance_title"
                       value="{{ $systemSettings['maintenance_title'] ?? '' }}"
                       placeholder="Contoh: Pemeliharaan Server Terjadwal"
                       required
                       @input="title = $event.target.value"
                       class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-700/50 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-medium focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all">
            </div>
            <div class="space-y-1.5">
                <label for="maintenance_scheduled_at" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Jadwal Pelaksanaan</label>
                <input type="datetime-local" name="scheduled_at" id="maintenance_scheduled_at"
                       value="{{ $systemSettings['maintenance_scheduled_at'] ?? '' }}"
                       required
                       class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-700/50 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-medium focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all">
            </div>
        </div>

        <!-- Row 2: Durasi & Urgensi -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
            <div class="space-y-1.5">
                <label for="maintenance_duration" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Estimasi Durasi</label>
                <input type="text" name="estimated_duration" id="maintenance_duration"
                       value="{{ $systemSettings['maintenance_estimated_duration'] ?? '' }}"
                       placeholder="Contoh: 2 Jam 30 Menit"
                       required
                       class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-700/50 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-medium focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all">
            </div>

            <!-- Segmented Control Tingkat Urgensi -->
            <div class="space-y-1.5">
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Tingkat Urgensi</label>
                <input type="hidden" name="severity" :value="severity">
                <div class="flex items-center bg-slate-100 dark:bg-slate-900 p-1 rounded-lg w-full h-[38px]">
                    <button type="button" @click="severity = 'info'"
                            :class="severity === 'info' ? 'bg-white dark:bg-slate-700 text-blue-600 dark:text-blue-400 shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'"
                            class="flex-1 flex items-center justify-center gap-1.5 h-full rounded-md text-xs font-semibold transition-all cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        Info
                    </button>
                    <button type="button" @click="severity = 'warning'"
                            :class="severity === 'warning' ? 'bg-white dark:bg-slate-700 text-amber-600 dark:text-amber-400 shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'"
                            class="flex-1 flex items-center justify-center gap-1.5 h-full rounded-md text-xs font-semibold transition-all cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        Penting
                    </button>
                    <button type="button" @click="severity = 'critical'"
                            :class="severity === 'critical' ? 'bg-white dark:bg-slate-700 text-red-600 dark:text-red-400 shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'"
                            class="flex-1 flex items-center justify-center gap-1.5 h-full rounded-md text-xs font-semibold transition-all cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" /></svg>
                        Darurat
                    </button>
                </div>
            </div>
        </div>

        <!-- Row 3: Deskripsi Detail -->
        <div class="space-y-1.5">
            <label for="maintenance_description" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Deskripsi Pekerjaan</label>
            <textarea name="description" id="maintenance_description" rows="3"
                      placeholder="Jelaskan alasan dan lingkup pemeliharaan sistem..."
                      required
                      class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-700/50 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-medium focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all resize-none">{{ $systemSettings['maintenance_description'] ?? ($systemSettings['maintenance_message'] ?? '') }}</textarea>
        </div>

        <!-- Submit -->
        <div class="pt-4 border-t border-slate-100 dark:border-slate-700/60 flex justify-end">
            <button type="submit" class="btn-primary">
                Simpan Konfigurasi
            </button>
        </div>
    </form>

    <!-- ==================== CARD 2: KONTROL NOTIFIKASI & KOMUNIKASI ==================== -->
    <div class="bg-white dark:bg-slate-800 rounded-3xl shadow-sm border border-slate-200/80 dark:border-slate-700/80 p-6 space-y-5">
        <div>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white mb-1">Kontrol Notifikasi & Komunikasi</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                Pusat kendali untuk mengaktifkan banner peringatan dan mengirim pesan massal ke seluruh mitra.
            </p>
        </div>

        <!-- 4-Column Grid for Controls -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">

            <!-- 1. Pop-up Banner -->
            <div class="flex flex-col justify-between p-4 rounded-2xl border border-slate-100 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/40 relative overflow-hidden">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 transition-colors"
                         :class="bannerActive ? 'bg-emerald-100 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400' : 'bg-white dark:bg-slate-700 shadow-sm text-slate-400'">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer select-none">
                        <input type="checkbox" class="sr-only peer" x-model="bannerActive" @change="toggleBanner()" :disabled="bannerToggling">
                        <div class="w-11 h-6 bg-slate-200 dark:bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:after:border-slate-600 peer-checked:bg-emerald-500"></div>
                    </label>
                </div>
                <div>
                    <span class="block text-xs font-semibold text-slate-900 dark:text-white">Pop-up Banner</span>
                    <span class="block text-[10px] text-slate-400 mt-1 leading-relaxed">Tampilkan banner notifikasi melayang di bagian atas layar seluruh halaman portal.</span>
                </div>
            </div>

            <!-- 2. Email Notification -->
            <div class="flex flex-col justify-between p-4 rounded-2xl border border-slate-100 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/40">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 transition-colors"
                         :class="emailSent ? 'bg-blue-100 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400' : 'bg-white dark:bg-slate-700 shadow-sm text-slate-400'">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                    </div>
                    <div x-show="emailSent" class="shrink-0 flex flex-col items-end">
                        <span class="inline-flex items-center gap-1 text-[9px] font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/30 px-2 py-0.5 rounded-md uppercase tracking-wide">
                            ✅ Terkirim
                        </span>
                        @if($systemSettings['maintenance_email_sent_at'] ?? null)
                            <span class="text-[9px] text-slate-400 font-medium mt-1">{{ \Carbon\Carbon::parse($systemSettings['maintenance_email_sent_at'])->timezone('Asia/Jakarta')->translatedFormat('H:i') }} WIB</span>
                        @endif
                    </div>
                </div>
                <div>
                    <span class="block text-xs font-semibold text-slate-900 dark:text-white">Email Siaran</span>
                    <span class="block text-[10px] text-slate-400 mt-1 leading-relaxed mb-3">Kirim email HTML resmi ke seluruh mitra portal.</span>
                    <button type="button" @click="sendEmails()" :disabled="emailSending"
                            class="w-full px-3 py-2 rounded-xl text-xs font-semibold text-white transition-all shadow-sm flex items-center justify-center gap-1.5 cursor-pointer"
                            :class="emailSending ? 'bg-slate-400 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-700'">
                        <span x-show="!emailSending">Kirim Email Sekarang</span>
                        <span x-show="emailSending">⏳ Memproses...</span>
                    </button>
                </div>
            </div>

            <!-- 3. WhatsApp Notification -->
            <div class="flex flex-col justify-between p-4 rounded-2xl border border-slate-100 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/40">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 transition-colors"
                         :class="waSent ? 'bg-green-100 dark:bg-green-950/40 text-green-600 dark:text-green-400' : 'bg-white dark:bg-slate-700 shadow-sm text-slate-400'">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                    </div>
                    <div x-show="waSent" class="shrink-0 flex flex-col items-end">
                        <span class="inline-flex items-center gap-1 text-[9px] font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/30 px-2 py-0.5 rounded-md uppercase tracking-wide">
                            ✅ Terkirim
                        </span>
                        @if($systemSettings['maintenance_wa_sent_at'] ?? null)
                            <span class="text-[9px] text-slate-400 font-medium mt-1">{{ \Carbon\Carbon::parse($systemSettings['maintenance_wa_sent_at'])->timezone('Asia/Jakarta')->translatedFormat('H:i') }} WIB</span>
                        @endif
                    </div>
                </div>
                <div>
                    <span class="block text-xs font-semibold text-slate-900 dark:text-white">Pesan WhatsApp</span>
                    <span class="block text-[10px] text-slate-400 mt-1 leading-relaxed mb-3">Kirim notifikasi WA ke nomor HP mitra yang terdaftar.</span>
                    <button type="button" @click="sendWA()" :disabled="waSending"
                            class="w-full px-3 py-2 rounded-xl text-xs font-semibold text-white transition-all shadow-sm flex items-center justify-center gap-1.5 cursor-pointer"
                            :class="waSending ? 'bg-slate-400 cursor-not-allowed' : 'bg-emerald-600 hover:bg-emerald-700'">
                        <span x-show="!waSending">Kirim Pesan WA</span>
                        <span x-show="waSending">⏳ Memproses...</span>
                    </button>
                </div>
            </div>

            <!-- 4. Lockdown Login -->
            <div class="flex flex-col justify-between p-4 rounded-2xl border bg-red-50/30 dark:bg-red-950/20 relative overflow-hidden transition-all"
                 :class="lockdownActive ? 'border-red-400 dark:border-red-500/50 shadow-md shadow-red-500/10' : 'border-red-100 dark:border-red-900/30'">
                <div class="flex items-start justify-between mb-3 relative z-10">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 transition-colors"
                         :class="lockdownActive ? 'bg-red-500 text-white shadow-sm' : 'bg-white dark:bg-slate-800 shadow-sm text-red-400 dark:text-red-500'">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer select-none">
                        <input type="checkbox" class="sr-only peer" x-model="lockdownActive" @change="toggleLockdown()" :disabled="lockdownToggling">
                        <div class="w-11 h-6 bg-red-200/50 dark:bg-red-900/30 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:after:border-slate-600 peer-checked:bg-red-500"></div>
                    </label>
                </div>
                <div class="relative z-10">
                    <span class="block text-xs font-bold text-red-700 dark:text-red-400">Kunci Akses Portal</span>
                    <span class="block text-[10px] text-red-600/80 dark:text-red-400/70 mt-1 leading-relaxed">Membekukan login mitra. Hanya akun Superadmin yang diizinkan masuk.</span>
                </div>
            </div>

        </div>
    </div>

    <!-- ==================== CARD 3: AKHIRI PEMELIHARAAN SISTEM ==================== -->
    <div class="bg-gradient-to-br from-emerald-50 to-teal-50 dark:from-emerald-950/20 dark:to-teal-950/20 rounded-3xl shadow-sm border border-emerald-100 dark:border-emerald-800/60 p-6"
         x-show="bannerActive">
        <div class="flex items-start justify-between flex-wrap gap-4">
            <div>
                <h3 class="text-sm font-bold text-emerald-800 dark:text-emerald-400 mb-1 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    Akhiri Pemeliharaan Sistem
                </h3>
                <p class="text-xs text-emerald-600 dark:text-emerald-500/80 leading-relaxed font-medium">
                    Sistem sudah selesai diperbaiki dan berjalan normal? Klik tombol di samping untuk mematikan banner dan sekaligus <b>mengirim notifikasi selesai</b> (Email, WhatsApp, dan Bel) ke seluruh mitra.
                </p>
            </div>
            <button type="button" @click="completeMaintenance()"
                    class="shrink-0 px-5 py-2.5 rounded-xl text-xs font-bold text-white transition-all shadow-lg shadow-emerald-500/30 bg-emerald-600 hover:bg-emerald-700 hover:-translate-y-0.5 cursor-pointer">
                ✅ Selesaikan &amp; Beritahu Semua
            </button>
        </div>
    </div>

    <!-- ==================== CARD 4: LIVE PREVIEW BANNER ==================== -->
    <div class="bg-white dark:bg-slate-800 rounded-3xl shadow-sm border border-slate-200/80 dark:border-slate-700/80 p-6">
        <h3 class="text-base font-semibold text-slate-900 dark:text-white mb-2">Preview Banner Melayang</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-4 leading-relaxed">
            Tampilan banner pop-up yang akan dilihat langsung oleh mitra di seluruh halaman portal saat toggle diaktifkan.
        </p>

        <div class="rounded-2xl overflow-hidden shadow-sm border border-slate-100 dark:border-slate-700/60">
            <div :class="severity === 'critical' ? 'bg-gradient-to-r from-red-600 via-red-700 to-red-800' : (severity === 'warning' ? 'bg-gradient-to-r from-amber-500 via-amber-600 to-amber-700' : 'bg-gradient-to-r from-blue-600 via-blue-700 to-blue-800')">
                <div class="px-5 py-4">
                    <div class="flex items-start gap-3.5">
                        <div class="shrink-0 mt-0.5 bg-white/20 p-2 rounded-xl">
                            <svg x-show="severity === 'critical'" class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" /></svg>
                            <svg x-show="severity === 'warning'" class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <svg x-show="severity === 'info'" class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                        <div class="flex-1 min-w-0 pr-2">
                            <h4 class="text-sm font-bold text-white leading-tight" x-text="title || 'Judul maintenance akan muncul di sini'"></h4>
                            <p class="text-xs text-white/90 mt-1 leading-relaxed">
                                {{ Str::limit($systemSettings['maintenance_description'] ?? ($systemSettings['maintenance_message'] ?? 'Deskripsi detail pemeliharaan akan tampil di sini.'), 150) }}
                            </p>
                            <div class="flex flex-wrap items-center gap-2 mt-2">
                                @if($systemSettings['maintenance_scheduled_at'] ?? null)
                                    <span class="text-[10px] font-semibold text-white/90 bg-white/15 px-2 py-0.5 rounded-md">📅 {{ \Carbon\Carbon::parse($systemSettings['maintenance_scheduled_at'])->timezone('Asia/Jakarta')->translatedFormat('d F Y, H:i') }} WIB</span>
                                @endif
                                @if($systemSettings['maintenance_estimated_duration'] ?? null)
                                    <span class="text-[10px] font-semibold text-white/90 bg-white/15 px-2 py-0.5 rounded-md">⏱️ {{ $systemSettings['maintenance_estimated_duration'] }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
