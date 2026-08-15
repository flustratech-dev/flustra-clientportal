<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiSyncLog;
use App\Models\PartnerLink;
use App\Services\Erp\ErpEventApplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Penerima webhook dari flustra-erp.
 *
 * Ini satu-satunya jalan bagi dunia luar untuk mengubah `account_type` seorang
 * pengguna. Karena itu penjagaannya berlapis dan semuanya wajib lolos sebelum
 * satu baris pun disentuh:
 *
 *  1. Rahasia HMAC harus terpasang. Kosong ⇒ endpoint MATI (503), bukan
 *     terbuka. Menerima webhook tanpa verifikasi berarti siapa pun yang tahu
 *     alamat ini bisa menaikkan tipe akunnya sendiri jadi 'pelanggan' dan
 *     membaca tagihan perusahaan orang lain.
 *  2. Tanda tangan dihitung atas RAW BODY, bukan atas array hasil decode.
 *     Menandatangani ulang hasil decode akan mencocokkan muatan yang sudah
 *     berubah bentuknya, dan itu membuat tanda tangannya tidak berarti.
 *  3. Kiriman yang lebih tua dari `portal.webhook_max_age` ditolak, supaya
 *     permintaan lama yang tercegat tidak bisa diputar ulang.
 *  4. X-Erp-Request-Id yang sudah pernah diproses ditolak — kiriman ulang dari
 *     ERP tidak boleh melahirkan notifikasi ganda.
 *
 * Perbandingan tanda tangan memakai hash_equals(), bukan ===, supaya lama
 * eksekusinya tidak membocorkan seberapa jauh tebakan penyerang benar.
 */
class WebhookController extends Controller
{
    public function __construct(protected ErpEventApplier $applier)
    {
    }

    public function handle(Request $request): JsonResponse
    {
        $secret = (string) config('portal.webhook_secret');

        if ($secret === '') {
            return $this->tolak($request, 503, 'Penerima webhook belum dikonfigurasi.');
        }

        $raw = $request->getContent();

        if (! $this->tandaTanganSah($request, $raw, $secret)) {
            return $this->tolak($request, 401, 'Tanda tangan tidak sah.');
        }

        $requestId = (string) $request->header('X-Erp-Request-Id');

        if ($requestId === '') {
            return $this->tolak($request, 422, 'Header X-Erp-Request-Id wajib ada.');
        }

        $payload = json_decode($raw, true);

        if (! is_array($payload)) {
            return $this->tolak($request, 422, 'Body bukan JSON yang sah.', $requestId);
        }

        if (! $this->masihSegar($payload['sent_at'] ?? null)) {
            return $this->tolak($request, 422, 'Kiriman terlalu lama dan ditolak.', $requestId);
        }

        // Cache::add bersifat atomik, jadi dua kiriman kembar yang tiba
        // bersamaan tidak bisa dua-duanya lolos. Pemeriksaan ke api_sync_logs
        // menyusul untuk kiriman ulang yang datang setelah cache-nya habis.
        $pertamaKali = Cache::add('erp-webhook:'.$requestId, true, now()->addDay());

        if (! $pertamaKali || $this->sudahPernahDiproses($requestId)) {
            // 200, bukan 4xx: kiriman ini valid, hanya sudah pernah dikerjakan.
            // Membalas galat hanya akan membuat ERP mengulanginya lagi.
            return $this->balas($request, 200, 'Sudah pernah diproses.', $requestId, $payload);
        }

        $event = (string) ($payload['event'] ?? '');
        $data  = (array) ($payload['data'] ?? []);

        $hasil = match ($event) {
            'claim.verified'            => $this->klaimDiverifikasi($data),
            'claim.rejected'            => $this->klaimDitolak($data),
            'submission.status_changed' => $this->statusBerubah($data),
            'partner.revoked'           => $this->mitraDicabut($data),
            default                     => ['status' => 422, 'message' => 'Event tidak dikenal: '.($event ?: '(kosong)')],
        };

        return $this->balas($request, $hasil['status'], $hasil['message'], $requestId, $payload, $event);
    }

    // =====================================================================
    // Penanganan per event
    // =====================================================================

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, message: string}
     */
    protected function klaimDiverifikasi(array $data): array
    {
        $link = $this->link($data);

        if (! $link) {
            return ['status' => 404, 'message' => 'portal_link_id tidak ditemukan.'];
        }

        $partnerId = (int) ($data['erp_partner_id'] ?? 0);

        if ($partnerId < 1) {
            return ['status' => 422, 'message' => 'erp_partner_id wajib ada pada claim.verified.'];
        }

        // true: event ini berarti ada staf yang baru saja menekan "Setujui" di
        // ERP, jadi akses yang sebelumnya dicabut memang boleh dibuka lagi.
        $diterapkan = $this->applier->claimVerified(
            $link,
            $partnerId,
            $data['partner_code'] ?? null,
            bolehMembukaYangDicabut: true,
        );

        return ['status' => 200, 'message' => $diterapkan ? 'Klaim diverifikasi.' : 'Sudah berstatus verified.'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, message: string}
     */
    protected function klaimDitolak(array $data): array
    {
        $link = $this->link($data);

        if (! $link) {
            return ['status' => 404, 'message' => 'portal_link_id tidak ditemukan.'];
        }

        $diterapkan = $this->applier->claimRejected(
            $link,
            Str::limit((string) ($data['reason'] ?? ''), 500) ?: 'Tanpa alasan tertulis.',
        );

        return ['status' => 200, 'message' => $diterapkan ? 'Klaim ditolak.' : 'Sudah berstatus rejected.'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, message: string}
     */
    protected function statusBerubah(array $data): array
    {
        $reference = (string) ($data['portal_reference'] ?? '');
        $status    = (string) ($data['status'] ?? '');

        if ($reference === '') {
            return ['status' => 422, 'message' => 'portal_reference wajib ada.'];
        }

        if (! in_array($status, $this->applier->allowedStatuses(), true)) {
            return ['status' => 422, 'message' => 'Status "'.$status.'" tidak dikenal.'];
        }

        $submission = $this->applier->findSubmission($reference);

        if (! $submission) {
            return ['status' => 404, 'message' => 'Pengajuan '.$reference.' tidak ditemukan.'];
        }

        $diterapkan = $this->applier->submissionStatusChanged(
            $submission,
            $status,
            ($data['reason'] ?? null) ? Str::limit((string) $data['reason'], 500) : null,
            $data['erp_reference'] ?? null,
        );

        return ['status' => 200, 'message' => $diterapkan ? 'Status diperbarui.' : 'Status sudah sama.'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, message: string}
     */
    protected function mitraDicabut(array $data): array
    {
        $link = $this->link($data);

        if (! $link) {
            return ['status' => 404, 'message' => 'portal_link_id tidak ditemukan.'];
        }

        $diterapkan = $this->applier->partnerRevoked(
            $link,
            ($data['reason'] ?? null) ? Str::limit((string) $data['reason'], 500) : null,
        );

        return ['status' => 200, 'message' => $diterapkan ? 'Akses mitra dicabut.' : 'Sudah berstatus revoked.'];
    }

    // =====================================================================
    // Penjagaan
    // =====================================================================

    protected function tandaTanganSah(Request $request, string $raw, string $secret): bool
    {
        $header = (string) $request->header('X-Erp-Signature');

        if ($header === '') {
            return false;
        }

        // ERP mengirim "sha256=<hex>"; prefiksnya dilepas kalau ada.
        $diterima = Str::startsWith($header, 'sha256=') ? substr($header, 7) : $header;
        $harusnya = hash_hmac('sha256', $raw, $secret);

        return hash_equals($harusnya, $diterima);
    }

    protected function masihSegar(mixed $sentAt): bool
    {
        if (! is_string($sentAt) || $sentAt === '') {
            return false; // Tanpa timestamp, umurnya tidak bisa diperiksa sama sekali.
        }

        try {
            $waktu = Carbon::parse($sentAt);
        } catch (\Throwable) {
            return false;
        }

        $batas = (int) config('portal.webhook_max_age', 300);

        // Toleransi ke depan disengaja kecil: jam server yang meleset sedikit
        // tidak boleh membuat webhook yang sah ikut tertolak.
        return $waktu->diffInSeconds(now(), absolute: true) <= $batas
            && $waktu->lessThanOrEqualTo(now()->addMinute());
    }

    protected function sudahPernahDiproses(string $requestId): bool
    {
        return ApiSyncLog::where('direction', 'inbound')
            ->where('request_id', $requestId)
            ->exists();
    }

    /** @param array<string, mixed> $data */
    protected function link(array $data): ?PartnerLink
    {
        $id = (int) ($data['portal_link_id'] ?? 0);

        return $id > 0 ? $this->applier->findLink($id) : null;
    }

    // =====================================================================
    // Jejak
    // =====================================================================

    /** @param array<string, mixed>|null $payload */
    protected function balas(
        Request $request,
        int $status,
        string $message,
        ?string $requestId = null,
        ?array $payload = null,
        ?string $event = null,
    ): JsonResponse {
        ApiSyncLog::record([
            'direction'        => 'inbound',
            'endpoint'         => $event ?: $request->path(),
            'method'           => $request->method(),
            'status_code'      => $status,
            'request_id'       => $requestId,
            'request_payload'  => $payload,
            'response_payload' => ['message' => $message],
            'error'            => $status >= 400 ? $message : null,
        ]);

        return response()->json(['message' => $message], $status);
    }

    protected function tolak(Request $request, int $status, string $message, ?string $requestId = null): JsonResponse
    {
        // Muatan kiriman yang ditolak tidak ikut disimpan: kalau tanda tangannya
        // salah, isinya belum tentu datang dari ERP dan tidak layak dipercaya.
        return $this->balas($request, $status, $message, $requestId);
    }
}
