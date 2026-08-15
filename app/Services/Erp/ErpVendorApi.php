<?php

namespace App\Services\Erp;

use App\Models\PartnerLink;
use App\Models\User;

/**
 * Seluruh endpoint sisi vendor di ERP.
 *
 * Kembarannya `ErpCustomerApi`, dan alasannya sama persis: setiap metode
 * menerima `User`, bukan `vendorId`. Id vendornya digali sendiri dari
 * `activeLink`, jadi tidak ada satu pun jalan bagi controller untuk mengoper
 * id yang datang dari request. ERP tetap memeriksa ulang lewat
 * `PortalPartnerResolver`.
 *
 * Yang berbeda dari sisi pelanggan hanyalah taruhannya. Di sini ada nomor
 * rekening: vendor yang bisa mengubahnya sendiri tanpa dilihat manusia adalah
 * jalur penipuan pembayaran paling umum. Karena itu `storeChangeRequest`
 * satu-satunya jalan untuk menyentuh data vendor, dan ia tidak pernah menulis
 * apa pun — hanya membuat antrean yang harus disetujui staf.
 */
class ErpVendorApi
{
    /**
     * Kolom data vendor yang boleh diajukan perubahannya.
     *
     * Disalin dari `PortalChangeRequest::ALLOWED_FIELDS['vendor']` di ERP.
     * Tiga kolom terakhir adalah data rekening — ditandai terpisah di
     * `FIELD_REKENING` supaya formulirnya bisa memperingatkan dengan jujur
     * bahwa perubahan itu diperiksa lebih ketat.
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
        'email'          => 'Email',
        'bank_name'      => 'Nama Bank',
        'bank_account'   => 'Nomor Rekening',
        'bank_holder'    => 'Nama Pemilik Rekening',
    ];

    /** @var array<int, string> */
    public const FIELD_REKENING = ['bank_name', 'bank_account', 'bank_holder'];

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
    public function purchaseOrders(User $user, int $page = 1): array
    {
        return $this->paginated($this->get($user, '/purchase-orders', ['page' => $page]));
    }

    /**
     * Tagihan yang pernah dikirim vendor ini, plus uang mukanya.
     *
     * `advances` sengaja ikut di respons yang sama: halaman Status Pembayaran
     * butuh keduanya, dan dua panggilan HTTP untuk satu halaman hanya
     * memperlambat tanpa alasan.
     *
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>, advances: array<int, array<string, mixed>>}
     */
    public function bills(User $user, int $page = 1): array
    {
        $response = $this->get($user, '/bills', ['page' => $page]);

        return $this->paginated($response) + ['advances' => $response['advances'] ?? []];
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function returns(User $user, int $page = 1): array
    {
        return $this->paginated($this->get($user, '/returns', ['page' => $page]));
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function shippingDocuments(User $user, int $page = 1): array
    {
        return $this->paginated($this->get($user, '/shipping-documents', ['page' => $page]));
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
    public function confirmPurchaseOrder(User $user, int $poId, array $payload, ?int $submissionId = null): array
    {
        return $this->post($user, '/purchase-orders/'.$poId.'/confirm', $payload, $submissionId);
    }

    /**
     * @param  array<int, array{name: string, contents: resource|string, filename: string}>  $files
     * @return array<string, mixed>
     */
    public function storeBill(User $user, array $payload, array $files, ?int $submissionId = null): array
    {
        return $this->client->postMultipart(
            $this->prefix($user).'/bills',
            $payload + ['portal_user_id' => $user->id],
            $files,
            $submissionId,
        );
    }

    /** @return array<string, mixed> */
    public function storeShippingDocument(User $user, array $payload, ?int $submissionId = null): array
    {
        return $this->post($user, '/shipping-documents', $payload, $submissionId);
    }

    /** @return array<string, mixed> */
    public function acknowledgeContract(User $user, int $contractId, array $payload, ?int $submissionId = null): array
    {
        return $this->post($user, '/contracts/'.$contractId.'/acknowledge', $payload, $submissionId);
    }

    /**
     * Sanggahan atas retur pembelian. Tidak mengubah returnya — yang dibuat
     * hanya keberatan yang harus ditinjau staf.
     *
     * @return array<string, mixed>
     */
    public function disputeReturn(User $user, int $returnId, array $payload, ?int $submissionId = null): array
    {
        return $this->post($user, '/returns/'.$returnId.'/dispute', $payload, $submissionId);
    }

    /**
     * Pengajuan perubahan data vendor — termasuk rekening.
     *
     * Tidak pernah menimpa data di ERP. Yang dibuat hanya antrean; staf yang
     * menerapkannya, dan ERP menandai `touches_bank_account` supaya
     * pengajuan rekening muncul dengan peringatan tersendiri di layar mereka.
     *
     * @return array<string, mixed>
     */
    public function storeChangeRequest(User $user, array $changes, string $portalReference, ?string $reason = null, ?int $submissionId = null): array
    {
        $link = $this->link($user);

        return $this->client->post('/change-requests', [
            'portal_user_id'   => $user->id,
            'portal_reference' => $portalReference,
            'partner_type'     => $link->partner_type,
            'partner_id'       => $link->erp_partner_id,
            'changes'          => $changes,
            'reason'           => $reason,
        ], $submissionId);
    }

    // =====================================================================
    // Pembantu
    // =====================================================================

    /**
     * Peran aktif pengguna, dipastikan benar-benar terverifikasi sebagai vendor.
     *
     * @throws ErpException
     */
    public function link(User $user): PartnerLink
    {
        $link = $user->activeLink();

        if (! $link || ! $link->isVerified() || $link->partner_type !== 'vendor') {
            throw new ErpException(
                'Akun ini belum terverifikasi sebagai vendor.',
                retryable: false,
            );
        }

        return $link;
    }

    protected function prefix(User $user): string
    {
        return '/vendors/'.$this->link($user)->erp_partner_id;
    }

    /** @return array<string, mixed> */
    protected function get(User $user, string $path, array $query = []): array
    {
        return $this->client->get(
            $this->prefix($user).$path,
            $query + ['portal_user_id' => $user->id],
        );
    }

    /** @return array<string, mixed> */
    protected function post(User $user, string $path, array $payload, ?int $submissionId = null): array
    {
        return $this->client->post(
            $this->prefix($user).$path,
            $payload + ['portal_user_id' => $user->id],
            $submissionId,
        );
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
