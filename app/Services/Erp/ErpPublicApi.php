<?php

namespace App\Services\Erp;

use App\Models\User;

/**
 * Endpoint ERP yang terbuka untuk akun portal bertipe 'umum'.
 *
 * Bedanya dari `ErpCustomerApi`/`ErpVendorApi`: tidak ada `activeLink` yang
 * perlu digali, karena tidak ada data mitra yang disentuh. Lowongan kerja
 * memang publik, dan lamaran/RFQ/pertanyaan adalah kiriman baru yang belum
 * terikat mitra mana pun.
 *
 * Yang tetap dijaga ERP: lowongan `is_internal_only` tidak pernah ikut, dan
 * lamaran hanya boleh masuk ke lowongan yang memang dibuka untuk publik.
 */
class ErpPublicApi
{
    public function __construct(protected ErpClient $client)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function vacancies(): array
    {
        return $this->client->get('/vacancies')['data'] ?? [];
    }

    /**
     * @param  array<int, array{name: string, contents: resource|string, filename: string}>  $files
     * @return array<string, mixed>
     */
    public function storeApplication(array $payload, array $files, ?int $submissionId = null): array
    {
        return $this->client->postMultipart('/applications', $payload, $files, $submissionId);
    }

    /** @return array<string, mixed> */
    public function storeLead(array $payload, ?int $submissionId = null): array
    {
        return $this->client->post('/leads', $payload, $submissionId);
    }

    /** @return array<string, mixed> */
    public function storeInquiry(User $user, array $payload, ?int $submissionId = null): array
    {
        return $this->client->post('/inquiries', $payload + ['portal_user_id' => $user->id], $submissionId);
    }
}
