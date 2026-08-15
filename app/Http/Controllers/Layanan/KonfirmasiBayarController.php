<?php

namespace App\Http\Controllers\Layanan;

use App\Jobs\SyncSubmissionToErp;
use App\Models\ActivityLog;
use App\Models\Submission;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Konfirmasi Pembayaran — alur bernilai tertinggi di seluruh portal.
 *
 * Sebelum ini, pelanggan mengirim bukti transfer lewat WhatsApp dan staf
 * mengetiknya ulang ke ERP. Tabel `payment_confirmations` bahkan tidak punya
 * kolom pengirim, jadi tidak ada cara membedakan input staf dari pelanggan.
 * Sejak halaman ini ada, barisnya lahir dengan `source = portal` dan nama
 * pengirimnya sendiri.
 *
 * Berkas buktinya disimpan dulu di disk privat portal, baru diunggah ke ERP
 * oleh job. Kalau ERP mati saat pelanggan menekan Kirim, buktinya tetap aman
 * dan tetap terkirim nanti — yang tidak boleh terjadi adalah pelanggan diminta
 * memfoto ulang struk transfernya.
 */
class KonfirmasiBayarController extends LayananPelangganController
{
    /** Metode yang diterima ERP. Di luar ini akan ditolak validasinya di sana. */
    public const METODE = [
        'bank_transfer' => 'Transfer Bank',
        'qris'          => 'QRIS',
        'va_manual'     => 'Virtual Account',
    ];

    public function create(Request $request): View
    {
        // Hanya tagihan yang masih punya sisa. Menawarkan invoice lunas untuk
        // dikonfirmasi hanya akan menghasilkan pekerjaan verifikasi sia-sia
        // bagi tim Finance.
        $hasil = $this->tarik(
            fn () => $this->erp->invoices($this->user(), [], 1),
            ['data' => [], 'meta' => []],
        );

        $invoices = array_values(array_filter(
            $hasil['data'],
            fn (array $i) => ($i['remaining_amount'] ?? 0) > 0
        ));

        return $this->halaman('layanan.pembayaran.create', [
            'invoices' => $invoices,
            'terpilih' => (int) $request->input('invoice'),
            'metode'   => self::METODE,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'invoice_id'     => ['required', 'integer', 'min:1'],
            'invoice_number' => ['required', 'string', 'max:100'],
            'amount'         => ['required', 'numeric', 'min:1'],
            'payment_date'   => ['required', 'date', 'before_or_equal:today'],
            'payment_method' => ['required', 'in:'.implode(',', array_keys(self::METODE))],
            'notes'          => ['nullable', 'string', 'max:1000'],
            'proof_file'     => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.config('portal.max_upload_kb')],
        ], [
            'proof_file.required'         => 'Bukti transfer wajib diunggah agar tim kami bisa memverifikasi.',
            'payment_date.before_or_equal' => 'Tanggal pembayaran tidak boleh di masa depan.',
            'amount.min'                  => 'Nominal pembayaran harus lebih dari nol.',
        ]);

        $user = $this->user();
        $file = $request->file('proof_file');

        // Disk privat, nama diacak oleh store(). Nama aslinya disimpan terpisah
        // di submission_attachments supaya tetap bisa ditampilkan ke pengguna.
        $path = $file->store('bukti-bayar', 'private');

        $submission = DB::transaction(function () use ($user, $data, $path, $file) {
            $submission = Submission::create([
                'user_id'          => $user->id,
                'partner_link_id'  => $this->erp->link($user)->id,
                'type'             => 'payment_confirmation',
                'reference_number' => Submission::generateReference(),
                'title'            => 'Konfirmasi pembayaran tagihan '.$data['invoice_number'],
                'summary'          => self::METODE[$data['payment_method']],
                'amount'           => $data['amount'],
                'erp_module'       => 'payment_confirmations',
                'erp_reference'    => $data['invoice_number'],
                'payload'          => [
                    'invoice_id'     => $data['invoice_id'],
                    'invoice_number' => $data['invoice_number'],
                    'amount'         => $data['amount'],
                    'payment_date'   => $data['payment_date'],
                    'payment_method' => $data['payment_method'],
                    'notes'          => $data['notes'] ?? null,
                ],
                'status'         => 'submitted',
                'submitted_at'   => now(),
                'last_status_at' => now(),
                'sync_state'     => 'pending',
            ]);

            $submission->attachments()->create([
                'disk'          => 'private',
                'path'          => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime'          => $file->getClientMimeType(),
                'size'          => $file->getSize(),
            ]);

            $submission->histories()->create([
                'to_status'  => 'submitted',
                'note'       => 'Bukti pembayaran diterima dan sedang diteruskan ke tim Finance kami.',
                'actor_type' => 'portal',
                'actor_name' => $user->name,
                'created_at' => now(),
            ]);

            return $submission;
        });

        ActivityLog::log(
            'payment_confirmation_submitted',
            'Mengirim konfirmasi pembayaran Rp '.number_format((float) $data['amount'], 0, ',', '.')
                .' untuk tagihan '.$data['invoice_number'].'.'
        );

        SyncSubmissionToErp::dispatch($submission->id);

        return redirect()->route('riwayat.show', $submission)->with(
            'success',
            'Konfirmasi pembayaran terkirim dengan nomor '.$submission->reference_number
                .'. Tim Finance kami akan memverifikasinya dan Anda akan diberi tahu hasilnya.'
        );
    }
}
