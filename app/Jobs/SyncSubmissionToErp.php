<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\PartnerLink;
use App\Models\Submission;
use App\Models\User;
use App\Services\Erp\ErpClient;
use App\Services\Erp\ErpCustomerApi;
use App\Services\Erp\ErpEventApplier;
use App\Services\Erp\ErpException;
use App\Services\Erp\ErpPublicApi;
use App\Services\Erp\ErpVendorApi;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

/**
 * Meneruskan satu pengajuan portal ke flustra-erp.
 *
 * Kenapa lewat antrean, bukan langsung di controller: kegagalan ERP tidak boleh
 * menggagalkan aksi pengguna. Pengguna sudah menekan "Kirim", datanya sudah
 * aman di `submissions`, dan itu janji yang bisa ditepati portal sendirian.
 * Apakah ERP sedang hidup atau tidak bukan urusan orang yang sedang mengisi
 * formulir.
 *
 * Percobaan ulang mundur bertahap: 1 menit, 5, 15, 1 jam, 6 jam. Rentang itu
 * menutup dua jenis gangguan sekaligus — deploy ERP yang memakan semenit, dan
 * server mati yang baru ditangani orang keesokan paginya.
 *
 * Aman dikirim ulang: setiap endpoint tulis di ERP idempoten terhadap
 * `portal_reference`, jadi kiriman kedua mengembalikan data lama alih-alih
 * membuat baris baru.
 */
class SyncSubmissionToErp implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    public int $timeout = 90;

    /** Satu pengajuan tidak perlu dua job berjalan bersamaan. */
    public int $uniqueFor = 3600;

    public function __construct(public int $submissionId)
    {
    }

    public function uniqueId(): string
    {
        return (string) $this->submissionId;
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900, 3600, 21600];
    }

    public function handle(ErpClient $erp, ErpEventApplier $applier): void
    {
        $submission = $this->submission();

        // Sudah tersinkron lebih dulu — biasanya lewat webhook yang mendahului
        // atau lewat perintah rekonsiliasi. Tidak ada yang perlu dikerjakan.
        if (! $submission || $submission->sync_state === 'synced') {
            return;
        }

        $submission->forceFill(['sync_attempts' => min($this->attempts(), 255)])->save();

        try {
            match ($submission->type) {
                'partner_claim'        => $this->kirimKlaimMitra($erp, $applier, $submission),
                'payment_confirmation' => $this->kirimKonfirmasiBayar($submission),
                'quotation_decision'   => $this->kirimKeputusanPenawaran($submission),
                'sales_return'         => $this->kirimRetur($submission),
                'contract_ack'         => $this->kirimPersetujuanKontrak($submission),
                'profile_change'       => $this->kirimPerubahanData($submission),

                // Sisi vendor (Fase 4)
                'po_confirmation'      => $this->kirimKonfirmasiPo($submission),
                'vendor_bill'          => $this->kirimTagihanVendor($submission),
                'shipping_doc'         => $this->kirimSuratJalan($submission),
                'return_dispute'       => $this->kirimSanggahan($submission),

                // Layanan umum (Fase 5)
                'job_application'      => $this->kirimLamaran($submission),
                'rfq'                  => $this->kirimRfq($submission),
                'inquiry'              => $this->kirimPertanyaan($submission),
                default                => throw new ErpException(
                    'Jenis pengajuan "'.$submission->type.'" belum punya jalur sinkronisasi.',
                    retryable: false,
                ),
            };
        } catch (ErpException $e) {
            $submission->forceFill(['sync_error' => Str::limit($e->getMessage(), 1000)])->save();

            // Muatan yang ditolak ERP tidak akan berubah kalau dikirim ulang.
            // Menghabiskan lima percobaan lagi hanya menunda orang mengetahuinya.
            if (! $e->retryable) {
                $this->fail($e);

                return;
            }

            throw $e;
        }
    }

    /**
     * POST /claims — muara klaim mitra di ERP adalah `portal_partner_claims`,
     * antrean yang dibaca staf di menu "Portal Mitra".
     */
    protected function kirimKlaimMitra(ErpClient $erp, ErpEventApplier $applier, Submission $submission): void
    {
        $link = $submission->partnerLink;

        if (! $link) {
            throw new ErpException('Pengajuan klaim tidak lagi punya baris partner_links.', retryable: false);
        }

        $user  = $submission->user;
        $email = $user?->email ?: $link->billing_email;

        if (! $email) {
            throw new ErpException('Klaim tanpa alamat email pemohon tidak bisa diverifikasi staf.', retryable: false);
        }

        $response = $erp->post('/claims', [
            'portal_user_id'   => $submission->user_id,
            'portal_link_id'   => $link->id,
            'portal_reference' => $submission->reference_number,
            'partner_type'     => $link->partner_type,

            'applicant_name'   => $user?->name ?: ($link->contact_person ?: $link->company_name),
            'applicant_email'  => $email,
            'applicant_phone'  => $user?->phone ?: $link->phone,

            'company_name'     => $link->company_name,
            'npwp'             => $link->npwp,
            'address'          => $link->address,
            'contact_person'   => $link->contact_person,

            'evidence_type'     => $link->evidence_type,
            'evidence_value'    => $link->evidence_value,
            'evidence_file_url' => $this->tautanBukti($link),
        ], $submission->id);

        $data = $response['data'] ?? [];

        $submission->forceFill([
            'sync_state'   => 'synced',
            'synced_at'    => now(),
            'sync_error'   => null,
            'sync_attempts' => min($this->attempts(), 255),
            'erp_module'   => 'portal_partner_claims',
        ])->save();

        // Kiriman ulang bisa mendapati klaimnya sudah diputus staf — misalnya
        // ketika respons pertama tidak pernah sampai ke portal. Pakai jawaban
        // itu langsung daripada menunggu webhook yang mungkin sudah lewat.
        $status = $data['status'] ?? 'pending';

        if ($status === 'verified' && ! empty($data['erp_partner_id'])) {
            $applier->claimVerified($link, (int) $data['erp_partner_id']);

            return;
        }

        if ($status === 'rejected') {
            $applier->claimRejected($link, ($data['rejected_reason'] ?? null) ?: 'Tanpa alasan tertulis.');

            return;
        }

        if ($submission->status === 'submitted') {
            $submission->transitionTo(
                'received',
                'Pengajuan sudah diterima sistem Flustra dan menunggu pemeriksaan tim kami.',
                'erp',
            );
        }
    }

    // =====================================================================
    // Layanan pelanggan (Fase 3)
    // =====================================================================

    /**
     * POST /customers/{id}/payment-confirmations — multipart, karena bukti
     * transfernya ikut naik.
     *
     * Berkasnya dibaca dari disk privat portal saat job berjalan, bukan
     * dititipkan lewat antrean. Isi PDF 5 MB di dalam payload job berarti tabel
     * `jobs` menyimpan berkas, dan setiap percobaan ulang menyalinnya lagi.
     */
    protected function kirimKonfirmasiBayar(Submission $submission): void
    {
        $user    = $this->penggunaAtauGagal($submission);
        $payload = $submission->payload ?? [];
        $bukti   = $submission->attachments()->first();

        if (! $bukti) {
            throw new ErpException('Konfirmasi pembayaran tanpa bukti transfer tidak bisa diverifikasi.', retryable: false);
        }

        $disk = Storage::disk($bukti->disk ?: 'private');

        if (! $disk->exists($bukti->path)) {
            throw new ErpException('Berkas bukti transfer sudah tidak ada di penyimpanan portal.', retryable: false);
        }

        $response = app(ErpCustomerApi::class)->storePaymentConfirmation(
            $user,
            [
                'invoice_id'        => $payload['invoice_id'] ?? null,
                'amount'            => $payload['amount'] ?? null,
                'payment_date'      => $payload['payment_date'] ?? null,
                'payment_method'    => $payload['payment_method'] ?? null,
                'notes'             => $payload['notes'] ?? null,
                'submitted_by_name' => $user->name,
                'portal_reference'  => $submission->reference_number,
            ],
            [[
                'name'     => 'proof_file',
                'contents' => $disk->get($bukti->path),
                'filename' => $bukti->original_name ?: basename($bukti->path),
            ]],
            $submission->id,
        );

        $this->tandaiTersinkron($submission, $response, 'Bukti pembayaran Anda sudah diterima tim Finance kami dan menunggu verifikasi.');
    }

    /**
     * POST /customers/{id}/quotations/{qid}/decision.
     */
    protected function kirimKeputusanPenawaran(Submission $submission): void
    {
        $user    = $this->penggunaAtauGagal($submission);
        $payload = $submission->payload ?? [];

        $response = app(ErpCustomerApi::class)->decideQuotation(
            $user,
            (int) ($payload['quotation_id'] ?? 0),
            [
                'decision'         => $payload['decision'] ?? null,
                'note'             => $payload['note'] ?? null,
                'actor_name'       => $payload['actor_name'] ?? $user->name,
                'actor_ip'         => $payload['actor_ip'] ?? null,
                'portal_reference' => $submission->reference_number,
            ],
            $submission->id,
        );

        // Keputusan penawaran selesai begitu tercatat — tidak ada tahap
        // verifikasi staf sesudahnya, jadi langsung 'approved'.
        $this->tandaiTersinkron(
            $submission,
            $response,
            ($payload['decision'] ?? null) === 'accepted'
                ? 'Persetujuan Anda sudah tercatat di sistem kami.'
                : 'Penolakan Anda sudah tercatat di sistem kami.',
            statusAkhir: 'approved',
        );
    }

    /**
     * POST /customers/{id}/sales-returns.
     *
     * Foto barang dikirim sebagai `evidence_file_url` — tautan bertanda tangan
     * ke disk privat portal, bukan berkasnya. ERP tidak menyimpan salinan
     * berkas milik pihak luar; staf membukanya lewat tautan yang kedaluwarsa
     * sendiri setelah `portal.evidence_url_days`.
     */
    protected function kirimRetur(Submission $submission): void
    {
        $user    = $this->penggunaAtauGagal($submission);
        $payload = $submission->payload ?? [];
        $foto    = $submission->attachments()->first();

        $response = app(ErpCustomerApi::class)->storeSalesReturn(
            $user,
            [
                'invoice_id'        => $payload['invoice_id'] ?? null,
                'product_id'        => $payload['product_id'] ?? null,
                'qty'               => $payload['qty'] ?? null,
                'reason_type'       => $payload['reason_type'] ?? null,
                'reason'            => $payload['reason'] ?? null,
                'requested_by_name' => $user->name,
                'portal_reference'  => $submission->reference_number,
                'evidence_file_url' => $foto ? $this->tautanLampiran($foto->id) : null,
            ],
            $submission->id,
        );

        $this->tandaiTersinkron($submission, $response, 'Pengajuan retur Anda sudah diterima dan menunggu pemeriksaan tim kami.');
    }

    /**
     * POST /{customers|vendors}/{id}/contracts/{cid}/acknowledge.
     *
     * Jalur API-nya dipilih dari `partner_type` pengajuan, bukan dari
     * `account_type` pengguna: peran aktif bisa sudah berpindah sejak
     * pengajuan dibuat.
     */
    protected function kirimPersetujuanKontrak(Submission $submission): void
    {
        $user    = $this->penggunaAtauGagal($submission);
        $payload = $submission->payload ?? [];

        $api = $submission->partnerLink?->partner_type === 'vendor'
            ? app(ErpVendorApi::class)
            : app(ErpCustomerApi::class);

        $response = $api->acknowledgeContract(
            $user,
            (int) ($payload['contract_id'] ?? 0),
            [
                'actor_name' => $payload['actor_name'] ?? $user->name,
                'actor_ip'   => $payload['actor_ip'] ?? null,
            ],
            $submission->id,
        );

        $this->tandaiTersinkron(
            $submission,
            $response,
            'Persetujuan Anda atas kontrak ini sudah tercatat di sistem kami.',
            statusAkhir: 'approved',
        );
    }

    /**
     * POST /change-requests. Tidak pernah menimpa data mitra langsung —
     * yang dibuat hanya antrean yang harus disetujui staf.
     *
     * Dipakai kedua tipe mitra. Jalur API-nya dipilih dari `partner_type`
     * pengajuan itu sendiri, bukan dari `account_type` pengguna: peran aktif
     * bisa sudah berpindah sejak pengajuan dibuat, dan pengajuan harus tetap
     * berangkat sebagai peran yang membuatnya.
     */
    protected function kirimPerubahanData(Submission $submission): void
    {
        $user    = $this->penggunaAtauGagal($submission);
        $payload = $submission->payload ?? [];
        $vendor  = $submission->partnerLink?->partner_type === 'vendor';

        $api = $vendor ? app(ErpVendorApi::class) : app(ErpCustomerApi::class);

        $response = $api->storeChangeRequest(
            $user,
            (array) ($payload['changes'] ?? []),
            $submission->reference_number,
            $payload['reason'] ?? null,
            $submission->id,
        );

        $this->tandaiTersinkron(
            $submission,
            $response,
            ! empty($payload['touches_bank'])
                ? 'Pengajuan Anda sudah diterima. Karena menyangkut data rekening, tim kami memverifikasinya lewat kontak resmi sebelum menerapkan.'
                : 'Pengajuan perubahan data Anda sudah diterima dan menunggu peninjauan.',
        );
    }

    // =====================================================================
    // Layanan vendor (Fase 4)
    // =====================================================================

    /** POST /vendors/{id}/purchase-orders/{poid}/confirm. */
    protected function kirimKonfirmasiPo(Submission $submission): void
    {
        $user    = $this->penggunaAtauGagal($submission);
        $payload = $submission->payload ?? [];

        $response = app(ErpVendorApi::class)->confirmPurchaseOrder(
            $user,
            (int) ($payload['purchase_order_id'] ?? 0),
            [
                'status'        => $payload['status'] ?? null,
                'promised_date' => $payload['promised_date'] ?? null,
                'notes'         => $payload['notes'] ?? null,
                'actor_name'    => $user->name,
            ],
            $submission->id,
        );

        // Konfirmasi kesanggupan selesai begitu tercatat — tidak ada tahap
        // persetujuan staf sesudahnya.
        $this->tandaiTersinkron(
            $submission,
            $response,
            ($payload['status'] ?? null) === 'accepted'
                ? 'Kesanggupan Anda sudah tercatat di sistem kami.'
                : 'Penolakan Anda sudah tercatat dan tim pembelian kami diberi tahu.',
            statusAkhir: 'approved',
        );
    }

    /**
     * POST /vendors/{id}/bills — multipart, faktur aslinya ikut naik.
     *
     * ERP membandingkan nominalnya dengan nilai PO dan menandai selisihnya
     * sendiri; portal tidak menghitung apa pun di sini.
     */
    protected function kirimTagihanVendor(Submission $submission): void
    {
        $user    = $this->penggunaAtauGagal($submission);
        $payload = $submission->payload ?? [];
        $faktur  = $submission->attachments()->first();

        if (! $faktur) {
            throw new ErpException('Tagihan vendor tanpa faktur asli tidak bisa ditinjau.', retryable: false);
        }

        $disk = Storage::disk($faktur->disk ?: 'private');

        if (! $disk->exists($faktur->path)) {
            throw new ErpException('Berkas faktur sudah tidak ada di penyimpanan portal.', retryable: false);
        }

        // Rincian item dikirim rata (items[0][quantity], …) karena bodinya
        // multipart — array bersarang tidak punya bentuk baku di multipart,
        // dan Laravel di sisi ERP membacanya kembali jadi array.
        $rata = [];
        foreach (array_values((array) ($payload['items'] ?? [])) as $i => $item) {
            $rata["items[$i][product_id]"] = $item['product_id'] ?? null;
            $rata["items[$i][quantity]"]   = $item['quantity'] ?? null;
            $rata["items[$i][price]"]      = $item['price'] ?? null;
        }

        $response = app(ErpVendorApi::class)->storeBill(
            $user,
            array_filter([
                'purchase_order_id' => $payload['purchase_order_id'] ?? null,
                'bill_number'       => $payload['bill_number'] ?? null,
                'bill_date'         => $payload['bill_date'] ?? null,
                'due_date'          => $payload['due_date'] ?? null,
                'amount'            => $payload['amount'] ?? null,
                'portal_reference'  => $submission->reference_number,
            ] + $rata, fn ($v) => $v !== null),
            [[
                'name'     => 'document',
                'contents' => $disk->get($faktur->path),
                'filename' => $faktur->original_name ?: basename($faktur->path),
            ]],
            $submission->id,
        );

        $selisih = ($response['data']['has_discrepancy'] ?? false)
            ? ' Nilainya berbeda dari purchase order, jadi tim kami akan mengonfirmasinya lebih dulu.'
            : '';

        $this->tandaiTersinkron(
            $submission,
            $response,
            'Tagihan Anda sudah diterima dan menunggu peninjauan tim kami.'.$selisih,
        );
    }

    /**
     * POST /vendors/{id}/shipping-documents.
     *
     * Selesai begitu tercatat: surat jalan adalah pemberitahuan, bukan
     * permintaan — tidak ada layar staf yang menyetujui atau menolaknya, jadi
     * tidak ada yang akan pernah memajukan statusnya dari 'received'.
     * Perjalanan barangnya sendiri terbaca di kartu "Surat Jalan", yang
     * menampilkan status pencocokan dengan penerimaan gudang langsung dari ERP.
     */
    protected function kirimSuratJalan(Submission $submission): void
    {
        $user    = $this->penggunaAtauGagal($submission);
        $payload = $submission->payload ?? [];
        $berkas  = $submission->attachments()->first();

        $response = app(ErpVendorApi::class)->storeShippingDocument(
            $user,
            [
                'purchase_order_id' => $payload['purchase_order_id'] ?? null,
                'document_number'   => $payload['document_number'] ?? null,
                'shipped_date'      => $payload['shipped_date'] ?? null,
                'estimated_arrival' => $payload['estimated_arrival'] ?? null,
                'courier'           => $payload['courier'] ?? null,
                'tracking_number'   => $payload['tracking_number'] ?? null,
                'notes'             => $payload['notes'] ?? null,
                'document_url'      => $berkas ? $this->tautanLampiran($berkas->id) : null,
                'submitted_by_name' => $user->name,
                'portal_reference'  => $submission->reference_number,
            ],
            $submission->id,
        );

        $this->tandaiTersinkron(
            $submission,
            $response,
            'Surat jalan Anda sudah diterima tim gudang kami. Status kedatangan barangnya bisa Anda pantau di kartu Surat Jalan.',
            statusAkhir: 'approved',
        );
    }

    /**
     * POST /vendors/{id}/returns/{rid}/dispute.
     *
     * Sanggahan tidak mengubah retur atau nota debitnya. Statusnya berhenti
     * di 'under_review' — bukan 'approved' — karena yang menentukan hasilnya
     * adalah keputusan staf yang datang belakangan lewat webhook.
     */
    protected function kirimSanggahan(Submission $submission): void
    {
        $user    = $this->penggunaAtauGagal($submission);
        $payload = $submission->payload ?? [];
        $berkas  = $submission->attachments()->first();

        $response = app(ErpVendorApi::class)->disputeReturn(
            $user,
            (int) ($payload['return_id'] ?? 0),
            [
                'reason'            => $payload['reason'] ?? null,
                'submitted_by_name' => $user->name,
                'portal_reference'  => $submission->reference_number,
                'evidence_file_url' => $berkas ? $this->tautanLampiran($berkas->id) : null,
            ],
            $submission->id,
        );

        $this->tandaiTersinkron(
            $submission,
            $response,
            'Sanggahan Anda sudah diterima dan sedang ditinjau tim kami.',
            statusAkhir: 'under_review',
        );
    }

    // =====================================================================
    // Layanan umum (Fase 5)
    // =====================================================================

    /** POST /applications — multipart, CV-nya ikut naik. */
    protected function kirimLamaran(Submission $submission): void
    {
        $payload = $submission->payload ?? [];
        $cv      = $submission->attachments()->first();

        if (! $cv) {
            throw new ErpException('Lamaran tanpa CV tidak bisa ditinjau.', retryable: false);
        }

        $disk = Storage::disk($cv->disk ?: 'private');

        if (! $disk->exists($cv->path)) {
            throw new ErpException('Berkas CV sudah tidak ada di penyimpanan portal.', retryable: false);
        }

        $response = app(ErpPublicApi::class)->storeApplication(
            [
                'job_vacancy_id'   => $payload['job_vacancy_id'] ?? null,
                'full_name'        => $payload['full_name'] ?? null,
                'email'            => $payload['email'] ?? null,
                'phone'            => $payload['phone'] ?? null,
                'portal_reference' => $submission->reference_number,
            ],
            [[
                'name'     => 'resume',
                'contents' => $disk->get($cv->path),
                'filename' => $cv->original_name ?: basename($cv->path),
            ]],
            $submission->id,
        );

        $this->tandaiTersinkron($submission, $response, 'Lamaran Anda sudah diterima tim HR kami.');
    }

    /** POST /leads — masuk sebagai prospek di pipeline CRM. */
    protected function kirimRfq(Submission $submission): void
    {
        $payload = $submission->payload ?? [];

        $response = app(ErpPublicApi::class)->storeLead(
            [
                'company_name'     => $payload['company_name'] ?? null,
                'contact_name'     => $payload['contact_name'] ?? null,
                'email'            => $payload['email'] ?? null,
                'phone'            => $payload['phone'] ?? null,
                'needs'            => $payload['needs'] ?? null,
                'estimated_value'  => $payload['estimated_value'] ?? null,
                'portal_reference' => $submission->reference_number,
            ],
            $submission->id,
        );

        $this->tandaiTersinkron($submission, $response, 'Permintaan Anda sudah diterima tim penjualan kami.');
    }

    /** POST /inquiries — kotak masuk pertanyaan, bukan tiket helpdesk. */
    protected function kirimPertanyaan(Submission $submission): void
    {
        $user    = $this->penggunaAtauGagal($submission);
        $payload = $submission->payload ?? [];

        $response = app(ErpPublicApi::class)->storeInquiry(
            $user,
            [
                'portal_reference' => $submission->reference_number,
                'sender_name'      => $user->name,
                'sender_email'     => $user->email,
                'sender_phone'     => $user->phone,
                'partner_type'     => $payload['partner_type'] ?? null,
                'subject'          => $payload['subject'] ?? null,
                'message'          => $payload['message'] ?? null,
            ],
            $submission->id,
        );

        $this->tandaiTersinkron($submission, $response, 'Pertanyaan Anda sudah sampai ke tim kami.');
    }

    // =====================================================================

    /**
     * Catat hasil pengiriman dan majukan status pengajuan.
     *
     * @param  array<string, mixed>  $response
     */
    protected function tandaiTersinkron(
        Submission $submission,
        array $response,
        string $catatan,
        string $statusAkhir = 'received',
    ): void {
        $data = $response['data'] ?? [];

        $submission->forceFill(array_filter([
            'sync_state'    => 'synced',
            'synced_at'     => now(),
            'sync_error'    => null,
            'sync_attempts' => min($this->attempts(), 255),
            'erp_record_id' => $data['erp_record_id'] ?? $submission->erp_record_id,
            'erp_reference' => $data['erp_reference'] ?? $submission->erp_reference,
        ], fn ($v) => $v !== null))->save();

        if ($submission->status === 'submitted') {
            $submission->transitionTo($statusAkhir, $catatan, 'erp');
        }
    }

    /**
     * Pengguna pemilik pengajuan. Tanpa dia, id mitra tidak bisa ditentukan —
     * dan id mitra tidak boleh datang dari mana pun selain activeLink-nya.
     */
    protected function penggunaAtauGagal(Submission $submission): User
    {
        $user = $submission->user;

        if (! $user) {
            throw new ErpException('Akun pemilik pengajuan sudah tidak ada.', retryable: false);
        }

        return $user;
    }

    /**
     * Tautan bukti untuk staf ERP.
     *
     * Berkasnya tetap di disk privat — yang dikirim hanya URL bertanda tangan
     * yang kedaluwarsa sendiri. ERP ada di aplikasi lain dan stafnya tidak
     * punya sesi portal, jadi tanda tangan URL inilah satu-satunya kunci.
     */
    protected function tautanBukti(PartnerLink $link): ?string
    {
        if (! $link->evidence_file_path) {
            return null;
        }

        return URL::temporarySignedRoute(
            'berkas.bukti-klaim',
            now()->addDays((int) config('portal.evidence_url_days', 7)),
            ['link' => $link->id],
        );
    }

    /** Tautan bertanda tangan ke satu lampiran pengajuan, untuk staf ERP. */
    protected function tautanLampiran(int $attachmentId): string
    {
        return URL::temporarySignedRoute(
            'berkas.lampiran',
            now()->addDays((int) config('portal.evidence_url_days', 7)),
            ['attachment' => $attachmentId],
        );
    }

    /**
     * Semua percobaan habis. Pengajuan ditandai gagal supaya muncul di layar
     * pemantauan, tapi statusnya TIDAK diturunkan: dari sisi pengguna, kirimnya
     * memang berhasil — yang gagal adalah urusan portal meneruskannya.
     */
    public function failed(Throwable $e): void
    {
        $submission = $this->submission();

        if (! $submission || $submission->sync_state === 'synced') {
            return;
        }

        $sudahPernahGagal = $submission->sync_state === 'failed';

        $submission->forceFill([
            'sync_state' => 'failed',
            'sync_error' => Str::limit($e->getMessage(), 1000),
        ])->save();

        Log::error('Sinkronisasi pengajuan portal ke ERP gagal total.', [
            'submission_id'    => $submission->id,
            'reference_number' => $submission->reference_number,
            'type'             => $submission->type,
            'attempts'         => $this->attempts(),
            'error'            => $e->getMessage(),
        ]);

        // Cukup satu kali diberitahukan. Perintah rekonsiliasi akan mencoba
        // lagi secara berkala; pengguna tidak perlu tahu setiap percobaannya.
        if ($sudahPernahGagal) {
            return;
        }

        $submission->transitionTo(
            $submission->status,
            'Pengiriman ke sistem kami tertunda. Pengajuan Anda tersimpan dan akan ditindaklanjuti tim kami secara manual.',
            'system',
        );

        Notification::send(
            $submission->user_id,
            'Pengajuan Anda tersimpan, pengirimannya tertunda',
            'Pengajuan '.$submission->reference_number.' belum bisa kami teruskan ke sistem internal. '
                .'Datanya aman dan tim kami akan menindaklanjuti — Anda tidak perlu mengirim ulang.',
            'warning',
            route('riwayat.show', $submission),
        );
    }

    /**
     * Global scope 'milik_sendiri' dilepas dengan sengaja: worker antrean
     * berjalan tanpa pengguna yang sedang masuk.
     */
    protected function submission(): ?Submission
    {
        return Submission::withoutGlobalScope('milik_sendiri')
            ->with(['partnerLink', 'user'])
            ->find($this->submissionId);
    }
}
