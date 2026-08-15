<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

/**
 * Satu pintu untuk memberi tahu pengguna portal.
 *
 * Lonceng di dalam portal selalu terisi — itu catatan permanen yang bisa
 * dibuka kapan saja. WhatsApp hanya pelengkap untuk kabar yang benar-benar
 * perlu segera dibaca: klaim disetujui/ditolak, pembayaran diverifikasi, PO
 * baru masuk. Mengirim WhatsApp untuk setiap perubahan status akan membuat
 * mitra mematikan notifikasinya, dan setelah itu tidak ada yang sampai.
 *
 * Kegagalan WhatsApp tidak pernah menggagalkan apa pun — `WhatsAppGateway`
 * mengembalikan false dan mencatatnya ke log, tidak melempar exception.
 */
class NotifikasiMitra
{
    /**
     * @param  string|null  $waPesan  Isi pesan WhatsApp. Null = lonceng saja.
     */
    public static function kirim(
        User $user,
        string $judul,
        string $isi,
        string $tipe = 'info',
        ?string $url = null,
        ?string $waPesan = null,
    ): Notification {
        $notifikasi = Notification::send($user->id, $judul, $isi, $tipe, $url);

        if ($waPesan && $user->phone) {
            WhatsAppGateway::send($user->phone, $waPesan);
        }

        return $notifikasi;
    }

    /**
     * Susun pesan WhatsApp dengan pembuka dan penutup yang seragam.
     *
     * Nomor pengajuan selalu disertakan: itu yang dipakai mitra saat menelepon,
     * dan pesan tanpa nomor memaksa staf mencari berdasarkan ingatan.
     */
    public static function pesan(string $judul, string $isi, ?string $nomor = null): string
    {
        return "*Portal Klien Flustra*\n\n"
            .$judul."\n\n"
            .$isi
            .($nomor ? "\n\nNomor pengajuan: ".$nomor : '')
            ."\n\nBuka portal: ".config('app.url');
    }
}
