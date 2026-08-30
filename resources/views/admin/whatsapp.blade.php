@extends('layouts.app')

@section('title', 'WhatsApp Gateway')
@section('page_title', 'WhatsApp Gateway')

@section('content')
<div class="max-w-4xl mx-auto py-2" x-data="whatsappGateway()" x-init="load()">

    <div class="mb-4">
        <a href="{{ route('admin.dashboard') }}" class="btn-secondary inline-flex items-center gap-2">
            &larr; Kembali ke Panel Admin
        </a>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden mb-6">
        <div class="px-6 py-5 border-b border-slate-200 dark:border-slate-700 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">Status Koneksi WhatsApp</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Pengiriman WhatsApp Portal Klien ditangani Flustra WA Gateway. Penautan nomor, QR code,
                    dan riwayat pesan dikelola di dashboard gateway.
                </p>
            </div>
            <div>
                <span x-show="status === 'ready'" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 text-xs font-medium">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Terhubung
                </span>
                <span x-show="status === 'disconnected'" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 text-xs font-medium">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Belum ada nomor tertaut
                </span>
                <span x-show="status === 'not_configured'" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400 text-xs font-medium">
                    Belum dikonfigurasi
                </span>
                <span x-show="status === 'offline' || status === 'error'" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 text-xs font-medium">
                    <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Gateway tidak terjangkau
                </span>
                <span x-show="status === 'loading'" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400 text-xs font-medium">
                    Memuat…
                </span>
            </div>
        </div>

        <div class="px-6 py-5 space-y-4">
            <p x-show="message" x-text="message" class="text-sm text-slate-600 dark:text-slate-400"></p>

            <template x-if="sessions">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <p class="text-xs text-slate-500">Sesi terhubung</p>
                        <p class="text-xl font-semibold text-slate-900 dark:text-white">
                            <span x-text="sessions.connected"></span>/<span x-text="sessions.total"></span>
                        </p>
                    </div>
                    <template x-if="usage">
                        <div>
                            <p class="text-xs text-slate-500">Terkirim bulan ini</p>
                            <p class="text-xl font-semibold text-slate-900 dark:text-white" x-text="usage.messages_sent"></p>
                        </div>
                    </template>
                    <template x-if="usage">
                        <div>
                            <p class="text-xs text-slate-500">Gagal bulan ini</p>
                            <p class="text-xl font-semibold text-slate-900 dark:text-white" x-text="usage.messages_failed"></p>
                        </div>
                    </template>
                </div>
            </template>

            <div class="flex flex-wrap gap-2 pt-2">
                <a href="{{ $gatewayUrl }}/sessions" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                    Kelola nomor &amp; scan QR
                </a>
                <a href="{{ $gatewayUrl }}/messages" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-2 rounded-lg border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-700">
                    Lihat riwayat pesan
                </a>
                <button type="button" @click="load()"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-700 cursor-pointer">
                    Muat ulang status
                </button>
            </div>
        </div>
    </div>

    <!-- ==================== UJI COBA WHATSAPP ==================== -->
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden mb-6">
        <div class="px-6 py-5 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Uji kirim</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                Kirim satu pesan ke nomor sendiri untuk memastikan jalur Portal → gateway → WhatsApp
                benar-benar hidup. Jalankan setiap selesai deploy atau ganti nomor.
            </p>
        </div>

        <form class="px-6 py-5 space-y-4" @submit.prevent="kirimUji()">
            <div class="grid md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Nomor tujuan</label>
                    <input type="text" x-model="uji.phone" placeholder="08123456789"
                           class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-900 px-3 py-2 text-sm">
                    <p class="text-xs text-slate-400 mt-1">Format 08xx, 62xx, atau +62xx.</p>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Pesan</label>
                    <textarea x-model="uji.message" rows="2"
                              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-900 px-3 py-2 text-sm"></textarea>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" :disabled="uji.mengirim"
                        class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50 cursor-pointer">
                    <span x-text="uji.mengirim ? 'Mengirim…' : 'Kirim pesan uji'"></span>
                </button>
            </div>

            <template x-if="uji.hasil">
                <div class="rounded-lg px-4 py-3 text-sm"
                     :class="uji.hasil.status === 'ok'
                        ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-300'
                        : 'bg-red-50 text-red-800 dark:bg-red-900/20 dark:text-red-300'">
                    <p x-text="uji.hasil.message"></p>
                    <p x-show="uji.hasil.message_id" class="text-xs mt-1 opacity-75">
                        ID pesan: <span x-text="uji.hasil.message_id"></span> — status pengirimannya bisa dilihat di riwayat pesan gateway.
                    </p>
                </div>
            </template>
        </form>
    </div>

    <!-- ==================== UJI COBA EMAIL ENTERPRISE ==================== -->
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden mb-6">
        <div class="px-6 py-5 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Uji kirim email (SMTP)</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                Kirim satu email HTML Enterprise ke kotak masuk Anda untuk memastikan server email (SMTP)
                berfungsi normal.
            </p>
        </div>

        <form class="px-6 py-5 space-y-4" @submit.prevent="kirimUjiEmail()">
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Email tujuan</label>
                    <input type="email" x-model="ujiEmail.email" placeholder="nama@perusahaan.com" required
                           class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-900 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Subjek email</label>
                    <input type="text" x-model="ujiEmail.subject" placeholder="Uji Coba Notifikasi Email" required
                           class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-900 px-3 py-2 text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Isi pesan</label>
                    <textarea x-model="ujiEmail.message" rows="2" required
                              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-900 px-3 py-2 text-sm"></textarea>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" :disabled="ujiEmail.mengirim"
                        class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 cursor-pointer">
                    <span x-text="ujiEmail.mengirim ? 'Mengirim…' : 'Kirim email uji'"></span>
                </button>
            </div>

            <template x-if="ujiEmail.hasil">
                <div class="rounded-lg px-4 py-3 text-sm"
                     :class="ujiEmail.hasil.status === 'ok'
                        ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-300'
                        : 'bg-red-50 text-red-800 dark:bg-red-900/20 dark:text-red-300'">
                    <p x-text="ujiEmail.hasil.message"></p>
                </div>
            </template>
        </form>
    </div>

    <!-- ==================== KENAPA GATEWAY TERPUSAT ==================== -->
    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 px-6 py-5 text-sm text-slate-600 dark:text-slate-400">
        <p class="font-medium text-slate-900 dark:text-white mb-1">Kenapa pindah ke gateway terpusat?</p>
        <p>
            Sebelumnya WhatsApp berjalan sebagai proses Node di dalam container aplikasi ini dengan sesi
            tersimpan di filesystem container. Karena filesystem itu dibuat ulang setiap deploy,
            nomor harus di-scan ulang setiap kali. Di gateway, sesi disimpan di volume permanen dan
            dicadangkan otomatis, sehingga redeploy tidak lagi memutus koneksi.
        </p>
    </div>
</div>

<script>
function whatsappGateway() {
    return {
        status: 'loading',
        message: '',
        sessions: null,
        usage: null,

        uji: {
            phone: '',
            message: 'Tes koneksi Flustra WA Gateway dari Portal Klien.',
            mengirim: false,
            hasil: null,
        },

        ujiEmail: {
            email: '',
            subject: 'Uji Coba Notifikasi Email Portal Flustra',
            message: 'Halo! Ini adalah email uji coba dari sistem notifikasi Flustra Client Portal untuk memverifikasi sambungan server email.',
            mengirim: false,
            hasil: null,
        },

        async kirimUji() {
            this.uji.mengirim = true;
            this.uji.hasil = null;

            try {
                const res = await fetch('{{ route('admin.whatsapp.test') }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ phone: this.uji.phone, message: this.uji.message }),
                });

                const data = await res.json();

                this.uji.hasil = data.errors
                    ? { status: 'error', message: Object.values(data.errors).flat().join(' ') }
                    : data;
            } catch (e) {
                this.uji.hasil = { status: 'error', message: 'Tidak bisa menghubungi server Portal.' };
            } finally {
                this.uji.mengirim = false;
            }

            this.load();
        },

        async kirimUjiEmail() {
            this.ujiEmail.mengirim = true;
            this.ujiEmail.hasil = null;

            try {
                const res = await fetch('{{ route('admin.email.test') }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(this.ujiEmail),
                });

                const data = await res.json();

                this.ujiEmail.hasil = data.errors
                    ? { status: 'error', message: Object.values(data.errors).flat().join(' ') }
                    : data;
            } catch (e) {
                this.ujiEmail.hasil = { status: 'error', message: 'Tidak bisa menghubungi server Portal.' };
            } finally {
                this.ujiEmail.mengirim = false;
            }
        },

        async load() {
            this.status = 'loading';

            try {
                const res = await fetch('{{ route('admin.whatsapp.status') }}', {
                    headers: { 'Accept': 'application/json' },
                });
                const data = await res.json();

                this.status = data.status;
                this.message = data.message ?? '';
                this.sessions = data.sessions ?? null;
                this.usage = data.usage ?? null;
            } catch (e) {
                this.status = 'offline';
                this.message = 'Tidak bisa menghubungi server.';
            }
        },
    };
}
</script>
@endsection
