<?php

namespace App\Services\Erp;

use App\Models\ApiSyncLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Satu-satunya pintu keluar portal menuju flustra-erp.
 *
 * Semua panggilan lewat sini supaya tiga hal selalu terjadi tanpa perlu
 * diingat di setiap pemanggil:
 *
 *  1. Token dibawa di header Authorization, dan panggilan menolak berangkat
 *     kalau tokennya kosong (lebih baik gagal jelas daripada mengetuk ERP
 *     tanpa autentikasi lalu bingung membaca 503-nya).
 *  2. Setiap permintaan membawa X-Portal-Request-Id. ERP idempoten terhadap
 *     portal_reference, jadi mengirim ulang saat respons tidak sampai itu aman.
 *  3. Setiap panggilan — berhasil maupun gagal — tercatat di api_sync_logs.
 *     Tanpa itu, menelusuri "pengajuan saya tidak muncul di ERP" berarti
 *     menebak-nebak.
 *
 * Kelas ini tidak tahu apa-apa soal Submission dan tidak pernah mengubah
 * statusnya. Itu urusan SyncSubmissionToErp.
 */
class ErpClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>  Body respons yang sudah di-decode.
     *
     * @throws ErpException
     */
    public function post(string $path, array $payload = [], ?int $submissionId = null): array
    {
        return $this->send('POST', $path, $payload, $submissionId);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws ErpException
     */
    public function get(string $path, array $query = [], ?int $submissionId = null): array
    {
        return $this->send('GET', $path, $query, $submissionId);
    }

    /**
     * Ambil badan respons apa adanya, tanpa decode JSON.
     *
     * Untuk dokumen biner — sejauh ini hanya PDF invoice. Isinya tidak ikut ke
     * `api_sync_logs`; yang tercatat cuma endpoint, status, dan durasinya.
     *
     * @param  array<string, mixed>  $query
     *
     * @throws ErpException
     */
    public function getRaw(string $path, array $query = [], ?int $submissionId = null): string
    {
        $token = (string) config('portal.erp.token');

        if ($token === '') {
            throw ErpException::notConfigured();
        }

        $endpoint  = $this->url($path);
        $requestId = (string) Str::uuid();
        $startedAt = microtime(true);

        try {
            $response = Http::withToken($token)
                ->withHeaders(['X-Portal-Request-Id' => $requestId])
                ->timeout((int) config('portal.erp.upload_timeout', 60))
                ->connectTimeout(10)
                ->get($endpoint, $query);
        } catch (ConnectionException $e) {
            $this->log('GET', $endpoint, null, $query, null, $e->getMessage(), $startedAt, $requestId, $submissionId);

            throw new ErpException(
                'Tidak bisa menghubungi sistem Flustra: '.$e->getMessage(),
                retryable: true,
                previous: $e,
            );
        }

        $this->log(
            'GET', $endpoint, $response->status(), $query, null,
            $response->successful() ? null : Str::limit($response->body(), 500),
            $startedAt, $requestId, $submissionId,
        );

        if (! $response->successful()) {
            throw new ErpException(
                'Sistem Flustra menolak permintaan (HTTP '.$response->status().').',
                retryable: $response->serverError() || $response->status() === 429,
                statusCode: $response->status(),
            );
        }

        return $response->body();
    }

    /**
     * Kirim berkas ke ERP (bukti transfer, faktur vendor, surat jalan).
     *
     * Dipisah dari post() karena tiga hal berbeda: batas waktunya jauh lebih
     * longgar (unggahan bisa lambat di koneksi mitra), bentuk bodinya multipart,
     * dan isi berkasnya TIDAK ikut dicatat ke api_sync_logs — yang disimpan
     * hanya nama dan ukurannya. Menuliskan isi PDF ke kolom log akan
     * menggelembungkan tabel tanpa menolong siapa pun saat menelusuri masalah.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<int, array{name: string, contents: resource|string, filename: string}>  $files
     * @return array<string, mixed>
     *
     * @throws ErpException
     */
    public function postMultipart(string $path, array $payload, array $files, ?int $submissionId = null): array
    {
        return $this->send('POST', $path, $payload, $submissionId, $files);
    }

    // =====================================================================

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array{name: string, contents: resource|string, filename: string}>  $files
     * @return array<string, mixed>
     *
     * @throws ErpException
     */
    protected function send(string $method, string $path, array $data, ?int $submissionId, array $files = []): array
    {
        $token = (string) config('portal.erp.token');

        if ($token === '') {
            throw ErpException::notConfigured();
        }

        $endpoint  = $this->url($path);
        $requestId = (string) Str::uuid();
        $startedAt = microtime(true);

        // Isi berkas tidak ikut ke log — hanya namanya, supaya jejaknya tetap
        // bisa dibaca tanpa membuat tabelnya membengkak.
        $logPayload = $files
            ? $data + ['_berkas' => array_column($files, 'filename')]
            : $data;

        try {
            $request = Http::withToken($token)
                ->withHeaders([
                    'Accept'             => 'application/json',
                    'X-Portal-Request-Id' => $requestId,
                ])
                ->timeout($files
                    ? (int) config('portal.erp.upload_timeout', 60)
                    : (int) config('portal.erp.timeout', 15))
                ->connectTimeout(10);

            foreach ($files as $file) {
                $request = $request->attach($file['name'], $file['contents'], $file['filename']);
            }

            $response = $method === 'GET'
                ? $request->get($endpoint, $data)
                : $request->post($endpoint, $data);
        } catch (ConnectionException $e) {
            // ERP tidak menjawab sama sekali: mati, salah alamat, atau jaringan
            // putus. Semuanya bisa membaik sendiri, jadi boleh dicoba ulang.
            $this->log($method, $endpoint, null, $logPayload, null, $e->getMessage(), $startedAt, $requestId, $submissionId);

            throw new ErpException(
                'Tidak bisa menghubungi sistem Flustra: '.$e->getMessage(),
                retryable: true,
                previous: $e,
            );
        }

        $this->log(
            $method, $endpoint, $response->status(), $logPayload,
            $this->decode($response),
            $response->successful() ? null : Str::limit($response->body(), 500),
            $startedAt, $requestId, $submissionId,
        );

        if ($response->successful()) {
            return is_array($response->json()) ? $response->json() : [];
        }

        throw new ErpException(
            'Sistem Flustra menolak permintaan (HTTP '.$response->status().'): '.Str::limit($response->body(), 300),
            // 4xx = muatannya yang salah, mengulang tidak akan mengubah jawaban.
            // 5xx & 429 = keadaan sementara, boleh dicoba lagi nanti.
            retryable: $response->serverError() || $response->status() === 429,
            statusCode: $response->status(),
        );
    }

    protected function url(string $path): string
    {
        return rtrim((string) config('portal.erp.url'), '/').'/'.ltrim($path, '/');
    }

    /**
     * Simpan body respons hanya bila berbentuk JSON. Kolomnya bertipe json;
     * menjejalkan halaman galat HTML ke sana hanya membuat log sulit dibaca.
     *
     * @return array<string, mixed>|null
     */
    protected function decode(Response $response): ?array
    {
        $decoded = $response->json();

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $responsePayload
     */
    protected function log(
        string $method,
        string $endpoint,
        ?int $statusCode,
        array $payload,
        ?array $responsePayload,
        ?string $error,
        float $startedAt,
        string $requestId,
        ?int $submissionId,
    ): void {
        ApiSyncLog::record([
            'direction'        => 'outbound',
            'endpoint'         => $endpoint,
            'method'           => $method,
            'status_code'      => $statusCode,
            'submission_id'    => $submissionId,
            'request_id'       => $requestId,
            'request_payload'  => $payload,
            'response_payload' => $responsePayload,
            'error'            => $error,
            'duration_ms'      => (int) ((microtime(true) - $startedAt) * 1000),
        ]);
    }
}
