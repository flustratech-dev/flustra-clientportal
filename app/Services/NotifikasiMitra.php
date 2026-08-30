<?php

namespace App\Services;

use App\Mail\NotifikasiPortalMail;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Satu pintu untuk memberi tahu pengguna portal secara terpadu (Tri-Channel).
 *
 * 1. 🔔 Lonceng di dalam portal (In-App Database Notification) — catatan permanen.
 * 2. 💬 WhatsApp Gateway (flustra-wa) — kabar instan ke nomor HP mitra.
 * 3. 📧 Email Enterprise (HTML Branded Mailable) — surat resmi ke kotak masuk email.
 *
 * Prinsip: Kegagalan WhatsApp atau Email tidak pernah menggagalkan apa pun.
 * Semua kegagalan dicatat rapi ke log dan transaksi database tetap berjalan 100%.
 */
class NotifikasiMitra
{
    /**
     * Kirim notifikasi serempak ke 3 kanal (Lonceng + WA + Email).
     *
     * @param  string|null  $waPesan  Isi pesan WhatsApp. Null = pakai template standar.
     * @param  bool         $kirimEmail  Kirim email jika user memiliki alamat email.
     * @param  string|null  $nomorReferensi  Nomor referensi / pengajuan terkait.
     * @param  string|null  $namaPerusahaan  Nama perusahaan / mitra terkait.
     */
    public static function kirim(
        User $user,
        string $judul,
        string $isi,
        string $tipe = 'info',
        ?string $url = null,
        ?string $waPesan = null,
        bool $kirimEmail = true,
        ?string $nomorReferensi = null,
        ?string $namaPerusahaan = null,
    ): Notification {
        // 1. Channel 1: In-App Notification (Database Lonceng)
        $notifikasi = Notification::send($user->id, $judul, $isi, $tipe, $url);

        // 2. Channel 2: WhatsApp Gateway (flustra-wa)
        if ($user->phone) {
            $pesanWa = $waPesan ?: self::pesan($judul, $isi, $nomorReferensi);
            try {
                WhatsAppGateway::send($user->phone, $pesanWa);
            } catch (\Throwable $e) {
                Log::warning('Gagal memicu pengiriman WhatsApp', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        // 3. Channel 3: Email Enterprise Mailable
        if ($kirimEmail && $user->email) {
            try {
                $actionUrl = $url ? (str_starts_with($url, 'http') ? $url : url($url)) : config('app.url');

                Mail::to($user->email)->send(new NotifikasiPortalMail(
                    namaPenerima: $user->name,
                    judul: $judul,
                    isi: $isi,
                    tipe: $tipe,
                    actionUrl: $actionUrl,
                    actionText: 'Buka di Portal',
                    nomorReferensi: $nomorReferensi,
                    namaPerusahaan: $namaPerusahaan,
                    subjekEmail: "[Portal Flustra] {$judul}",
                ));
            } catch (\Throwable $e) {
                Log::warning('Gagal memicu pengiriman Email Notifikasi', [
                    'user_id' => $user->id,
                    'email'   => $user->email,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        return $notifikasi;
    }

    /**
     * Susun pesan WhatsApp dengan pembuka dan penutup yang seragam.
     */
    public static function pesan(string $judul, string $isi, ?string $nomor = null): string
    {
        return "*Portal Klien Flustra*\n\n"
            .$judul."\n\n"
            .$isi
            .($nomor ? "\n\nNomor pengajuan: *".$nomor.'*' : '')
            ."\n\nBuka portal: ".config('app.url');
    }
}
