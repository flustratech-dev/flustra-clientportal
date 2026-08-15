<?php

namespace App\Services\Erp;

use RuntimeException;
use Throwable;

/**
 * Kegagalan panggilan ke flustra-erp.
 *
 * Yang penting di sini bukan pesannya, melainkan `retryable`. Ada dua jenis
 * kegagalan yang perlu diperlakukan berbeda:
 *
 *  - ERP mati, lambat, atau balas 5xx  → boleh dicoba ulang, keadaannya bisa
 *    membaik sendiri.
 *  - ERP balas 4xx (mis. 422 karena payload salah, 401 karena token salah)
 *    → mengirim ulang muatan yang sama seratus kali tidak akan mengubah
 *    jawabannya. Lebih baik langsung ditandai gagal supaya ada yang memeriksa.
 */
class ErpException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable = true,
        public readonly ?int $statusCode = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode ?? 0, $previous);
    }

    public static function notConfigured(): self
    {
        // Boleh dicoba ulang: biasanya ini env yang belum diisi saat deploy, dan
        // akan benar setelah diperbaiki tanpa perlu mengirim ulang manual.
        return new self('Integrasi ERP belum dikonfigurasi (ERP_API_TOKEN kosong).', retryable: true);
    }
}
