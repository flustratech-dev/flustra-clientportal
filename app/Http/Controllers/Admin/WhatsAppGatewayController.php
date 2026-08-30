<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\NotifikasiPortalMail;
use App\Services\WhatsAppGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Halaman status dan pusat pengujian kanal notifikasi (WhatsApp & Email).
 *
 * Sama persis dengan Flustra Office, ditambah kemampuan live test pengiriman
 * Email HTML Enterprise Mailable untuk memvalidasi konfigurasi SMTP.
 */
class WhatsAppGatewayController extends Controller
{
    public function index(): View
    {
        return view('admin.whatsapp', [
            'gatewayUrl' => rtrim(config('whatsapp.url'), '/'),
            'mailer'     => config('mail.default'),
            'mailHost'   => config('mail.mailers.smtp.host'),
            'mailFrom'   => config('mail.from.address'),
        ]);
    }

    /**
     * Status ringkas dari WhatsApp gateway: sesi terhubung dan kuota pesan.
     */
    public function status(): JsonResponse
    {
        if (! config('whatsapp.key')) {
            return response()->json([
                'status'  => 'not_configured',
                'message' => 'WA_GATEWAY_KEY belum diisi di .env aplikasi ini.',
            ]);
        }

        try {
            $response = Http::baseUrl(rtrim(config('whatsapp.url'), '/'))
                ->withHeader('X-Api-Key', config('whatsapp.key'))
                ->connectTimeout(3)
                ->timeout(8)
                ->acceptJson()
                ->get('/api/v1/health');

            if ($response->failed()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => $response->json('error.message') ?? 'Gateway menolak permintaan.',
                ]);
            }

            $data = $response->json('data');

            return response()->json([
                'status'   => ($data['sessions']['connected'] ?? 0) > 0 ? 'ready' : 'disconnected',
                'sessions' => $data['sessions'] ?? null,
                'usage'    => $data['usage'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Gagal menghubungi Flustra WA Gateway: '.$e->getMessage());

            return response()->json(['status' => 'offline', 'message' => 'Gateway tidak dapat dihubungi.']);
        }
    }

    /**
     * Kirim satu pesan uji ke nomor WhatsApp yang dimasukkan admin.
     */
    public function test(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone'   => ['required', 'string', 'max:25'],
            'message' => ['required', 'string', 'max:1000'],
        ]);

        if (! config('whatsapp.enabled')) {
            return response()->json([
                'status'  => 'error',
                'message' => 'WA_GATEWAY_ENABLED bernilai false di aplikasi ini.',
            ], 422);
        }

        if (! config('whatsapp.key')) {
            return response()->json([
                'status'  => 'error',
                'message' => 'WA_GATEWAY_KEY belum diisi di .env aplikasi ini.',
            ], 422);
        }

        $phone = WhatsAppGateway::normalize($data['phone']);

        if ($phone === null) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Nomor tidak dikenali. Gunakan format 08xx, 62xx, atau +62xx.',
            ], 422);
        }

        try {
            $response = Http::baseUrl(rtrim(config('whatsapp.url'), '/'))
                ->withHeader('X-Api-Key', config('whatsapp.key'))
                ->connectTimeout(5)
                ->timeout(15)
                ->acceptJson()
                ->post('/api/v1/messages/text', array_filter([
                    'session_id' => config('whatsapp.session'),
                    'to'         => $phone,
                    'message'    => $data['message'],
                ]));
        } catch (\Throwable $e) {
            Log::warning('Uji kirim WhatsApp gagal menghubungi gateway', ['error' => $e->getMessage()]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Gateway tidak dapat dihubungi: '.$e->getMessage(),
            ], 502);
        }

        if ($response->failed()) {
            return response()->json([
                'status'      => 'error',
                'message'     => $response->json('error.message') ?? 'Gateway menolak pengiriman pesan.',
                'http_status' => $response->status(),
            ], 422);
        }

        Log::info('Uji kirim WhatsApp Portal Admin', [
            'oleh'       => Auth::id(),
            'ke'         => substr($phone, 0, 4).'****'.substr($phone, -4),
            'message_id' => $response->json('data.id'),
        ]);

        return response()->json([
            'status'     => 'ok',
            'message'    => 'Pesan uji berhasil diterima gateway dan masuk antrean kirim.',
            'to'         => $phone,
            'message_id' => $response->json('data.id'),
        ]);
    }

    /**
     * Kirim satu email uji HTML Enterprise ke alamat email yang dimasukkan admin.
     */
    public function testEmail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'   => ['required', 'email', 'max:150'],
            'subject' => ['nullable', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $subject = $data['subject'] ?: 'Uji Coba Notifikasi Email Portal Flustra';

        try {
            Mail::to($data['email'])->send(new NotifikasiPortalMail(
                namaPenerima: 'Admin Tester',
                judul: $subject,
                isi: $data['message'],
                tipe: 'info',
                actionUrl: config('app.url'),
                actionText: 'Buka Portal',
                nomorReferensi: 'TEST-'.date('YmdHis'),
                namaPerusahaan: 'PT Flustra Tech Nusantara',
                subjekEmail: "[Uji Coba SMTP] {$subject}",
            ));

            Log::info('Uji kirim Email SMTP Portal Admin', [
                'oleh' => Auth::id(),
                'ke'   => $data['email'],
            ]);

            return response()->json([
                'status'  => 'ok',
                'message' => 'Email uji coba HTML Enterprise berhasil dikirim ke '.$data['email'].'. Periksa kotak masuk / spam.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Uji kirim email gagal', ['error' => $e->getMessage()]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal mengirim email: '.$e->getMessage(),
            ], 500);
        }
    }
}
