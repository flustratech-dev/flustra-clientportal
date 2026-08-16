<?php

namespace App\Services\Erp;

use App\Models\PartnerLink;
use App\Models\User;
use App\Services\KonteksMitra;

/**
 * Seluruh endpoint sisi pelanggan di ERP, dibungkus jadi metode bernama.
 *
 * Alasan kelas ini ada, dan kenapa bentuknya begini:
 *
 * Setiap metode menerima `User`, bukan `customerId`. Id pelanggannya digali
 * sendiri dari `activeLink` milik pengguna itu. Dengan begitu tidak ada
 * satu pun jalan bagi controller untuk — sengaja atau tidak — mengoper id
 * yang datang dari request. Itu satu-satunya cara pelanggan A bisa membaca
 * tagihan pelanggan B, dan menutupnya di sini menutupnya untuk semua
 * pemanggil sekaligus.
 *
 * ERP tetap memeriksa ulang setiap id terhadap klaim terverifikasi lewat
 * PortalPartnerResolver. Dua lapis, disengaja: satu lapis pernah gagal di
 * mana-mana.
 */
class ErpCustomerApi
{
    /**
     * Kolom data perusahaan yang boleh diajukan perubahannya, beserta labelnya.
     *
     * Disalin dari daftar putih `PortalChangeRequest::ALLOWED_FIELDS['customer']`
     * di ERP. ERP tetap menyaring ulang — apa pun di luar daftarnya diabaikan
     * diam-diam — jadi daftar di sini hanya untuk membangun formulirnya, bukan
     * penjaga.
     *
     * @var array<string, string>
     */
    public const FIELD_LABELS = [
        'company'        => 'Nama Perusahaan',
        'name'           => 'Nama Terdaftar',
        'npwp'           => 'NPWP',
        'address'        => 'Alamat',
        'contact_person' => 'Contact Person',
        'phone'          => 'Telepon',
        'email'          => 'Email Penagihan',
    ];

    public function __construct(protected ErpClient $client)
    {
    }

    // =====================================================================
    // Baca
    // =====================================================================

    /** @return array<string, mixed> */
    public function summary(User $user): array
    {
        return $this->get($user, '/summary')['data'] ?? [];
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function invoices(User $user, array $filters = [], int $page = 1): array
    {
        return $this->paginated($this->get($user, '/invoices', $filters + ['page' => $page]));
    }

    /** @return array<string, mixed> */
    public function invoice(User $user, int $invoiceId): array
    {
        return $this->get($user, '/invoices/'.$invoiceId)['data'] ?? [];
    }

    /**
     * PDF invoice, dikembalikan mentah untuk dialirkan ulang ke pelanggan.
     *
     * Tidak lewat `get()` karena jawabannya bukan JSON. Portal tidak menyimpan
     * salinannya: PDF-nya dibuat ERP dari template yang sama dengan unduhan
     * staf, jadi dokumen yang diterima pelanggan identik dengan yang dilihat
     * internal.
     */
    public function invoicePdf(User $user, int $invoiceId): string
    {
        return $this->client->getRaw(
            $this->prefix($user).'/invoices/'.$invoiceId.'/pdf',
            ['portal_user_id' => $this->portalUserId($user)],
        );
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function quotations(User $user, int $page = 1): array
    {
        return $this->paginated($this->get($user, '/quotations', ['page' => $page]));
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function salesOrders(User $user, int $page = 1): array
    {
        return $this->paginated($this->get($user, '/sales-orders', ['page' => $page]));
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function deliveries(User $user, int $page = 1): array
    {
        return $this->paginated($this->get($user, '/deliveries', ['page' => $page]));
    }

    /** @return array<int, array<string, mixed>> */
    public function contracts(User $user): array
    {
        return $this->get($user, '/contracts')['data'] ?? [];
    }

    // =====================================================================
    // Tulis — dipanggil dari SyncSubmissionToErp, bukan dari controller
    // =====================================================================

    /** @return array<string, mixed> */
    public function decideQuotation(User $user, int $quotationId, array $payload, ?int $submissionId = null): array
    {
        return $this->post($user, '/quotations/'.$quotationId.'/decision', $payload, $submissionId);
    }

    /** @return array<string, mixed> */
    public function acknowledgeContract(User $user, int $contractId, array $payload, ?int $submissionId = null): array
    {
        return $this->post($user, '/contracts/'.$contractId.'/acknowledge', $payload, $submissionId);
    }

    /** @return array<string, mixed> */
    public function storeSalesReturn(User $user, array $payload, ?int $submissionId = null): array
    {
        return $this->post($user, '/sales-returns', $payload, $submissionId);
    }

    /**
     * @param  array<int, array{name: string, contents: resource|string, filename: string}>  $files
     * @return array<string, mixed>
     */
    public function storePaymentConfirmation(User $user, array $payload, array $files, ?int $submissionId = null): array
    {
        $respons = $this->client->postMultipart(
            $this->prefix($user).'/payment-confirmations',
            $payload + ['portal_user_id' => $this->portalUserId($user)],
            $files,
            $submissionId,
        );

        ErpBacaCache::lupakan($this->kunciMitra($user));

        return $respons;
    }

    // =====================================================================

    /**
     * Pengajuan perubahan data mitra. Endpoint ini tidak berada di bawah
     * /customers/{id} — ERP menerimanya dengan partner_type + partner_id
     * eksplisit — tapi id-nya tetap dari activeLink, bukan dari request.
     *
     * @return array<string, mixed>
     */
    public function storeChangeRequest(User $user, array $changes, string $portalReference, ?string $reason = null, ?int $submissionId = null): array
    {
        $link = $this->link($user);

        $respons = $this->client->post('/change-requests', [
            'portal_user_id'   => $this->portalUserId($user),
            'portal_reference' => $portalReference,
            'partner_type'     => $link->partner_type,
            'partner_id'       => $link->erp_partner_id,
            'changes'          => $changes,
            'reason'           => $reason,
        ], $submissionId);

        ErpBacaCache::lupakan($this->kunciMitra($user));

        return $respons;
    }

    // =====================================================================
    // Pembantu
    // =====================================================================

    /**
     * Peran aktif pengguna, dipastikan benar-benar terverifikasi.
     *
     * Melempar, bukan mengembalikan null: setiap pemanggil di sini butuh id
     * mitra untuk bisa bekerja, dan diam-diam mengembalikan daftar kosong akan
     * menyamarkan salah pasang penjaga rute sebagai "kebetulan tidak ada data".
     *
     * @throws ErpException
     */
    public function link(User $user): PartnerLink
    {
        $link = KonteksMitra::link($user, 'customer');

        if (! $link) {
            throw new ErpException(
                $user->isAdmin()
                    ? 'Pilih dulu mitra yang ingin Anda lihat di halaman "Lihat Sebagai".'
                    : 'Akun ini belum terverifikasi sebagai pelanggan.',
                retryable: false,
            );
        }

        return $link;
    }

    /**
     * Id pengguna yang dikirim ke ERP.
     *
     * Biasanya id pemanggilnya sendiri. Bila admin portal sedang melihat
     * sebagai mitra tertentu, yang dikirim adalah id PEMILIK link-nya — ERP
     * memvalidasi id itu terhadap klaim terverifikasi, dan admin tidak punya
     * klaim apa pun. Lihat catatan pertukaran keamanannya di KonteksMitra.
     */
    protected function portalUserId(User $user): int
    {
        return KonteksMitra::pemilik($user, $this->link($user))->id;
    }

    protected function prefix(User $user): string
    {
        return '/customers/'.$this->link($user)->erp_partner_id;
    }

    /**
     * Pembacaan, lewat cache pendek per mitra.
     *
     * Lihat ErpBacaCache untuk alasan dan batasnya — singkatnya: ini
     * penggabung permintaan berdekatan, bukan salinan data ERP.
     *
     * @return array<string, mixed>
     */
    protected function get(User $user, string $path, array $query = []): array
    {
        $penuh = $query + ['portal_user_id' => $this->portalUserId($user)];
        $jalur = $this->prefix($user).$path;

        return ErpBacaCache::ingat(
            $this->kunciMitra($user),
            $jalur,
            $penuh,
            fn () => $this->client->get($jalur, $penuh),
        );
    }

    /**
     * Penulisan, dan sesudahnya cache baca mitra ini dibuang.
     *
     * Tanpa itu, pelanggan yang baru mengirim bukti transfer bisa kembali ke
     * daftar tagihan dan melihat keadaan dari sebelum ia menekan Kirim —
     * persis momen ketika orang paling ingin memastikan kirimannya masuk.
     *
     * @return array<string, mixed>
     */
    protected function post(User $user, string $path, array $payload, ?int $submissionId = null): array
    {
        $respons = $this->client->post(
            $this->prefix($user).$path,
            $payload + ['portal_user_id' => $this->portalUserId($user)],
            $submissionId,
        );

        ErpBacaCache::lupakan($this->kunciMitra($user));

        return $respons;
    }

    /** Kunci cache dipisah per mitra — isolasi data tidak boleh bocor lewat cache. */
    protected function kunciMitra(User $user): string
    {
        return 'customer:'.$this->link($user)->erp_partner_id;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    protected function paginated(array $response): array
    {
        return [
            'data' => $response['data'] ?? [],
            'meta' => $response['meta'] ?? ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 0],
        ];
    }
}
