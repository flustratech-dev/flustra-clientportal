<?php

namespace Tests\Feature;

use App\Mail\NotifikasiPortalMail;
use App\Models\Notification;
use App\Models\PartnerLink;
use App\Models\Submission;
use App\Models\User;
use App\Services\Erp\ErpEventApplier;
use App\Services\NotifikasiMitra;
use App\Services\WhatsAppGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotifikasiTriChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifikasi_mitra_mengirim_ke_tiga_kanal_sekaligus(): void
    {
        Mail::fake();
        Http::fake([
            'https://wa.flustra.id/*' => Http::response(['status' => 'queued'], 200),
        ]);

        config([
            'whatsapp.enabled' => true,
            'whatsapp.url'     => 'https://wa.flustra.id',
            'whatsapp.key'     => 'test-key',
        ]);

        $user = User::factory()->create([
            'email' => 'budi@example.com',
            'phone' => '081234567890',
        ]);

        $notif = NotifikasiMitra::kirim(
            user: $user,
            judul: 'Pengajuan kerja sama disetujui',
            isi: 'Selamat, akun Anda telah aktif.',
            tipe: 'success',
            url: '/beranda',
            nomorReferensi: 'REG-001',
            namaPerusahaan: 'PT Maju Bersama',
        );

        // 1. Kanal 1: In-App Database
        $this->assertDatabaseHas('notifications', [
            'id'      => $notif->id,
            'user_id' => $user->id,
            'title'   => 'Pengajuan kerja sama disetujui',
            'type'    => 'success',
        ]);

        // 2. Kanal 2: WhatsApp Gateway
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/v1/messages/text')
                && $request['to'] === '6281234567890';
        });

        // 3. Kanal 3: Email Mailable
        Mail::assertSent(NotifikasiPortalMail::class, function (NotifikasiPortalMail $mail) use ($user) {
            return $mail->hasTo($user->email)
                && $mail->judul === 'Pengajuan kerja sama disetujui'
                && $mail->nomorReferensi === 'REG-001'
                && $mail->namaPerusahaan === 'PT Maju Bersama';
        });
    }

    public function test_kegagalan_wa_atau_email_tidak_pernah_menggagalkan_proses(): void
    {
        Http::fake([
            'https://wa.flustra.id/*' => Http::response(['error' => 'Gateway Down'], 500),
        ]);

        config([
            'whatsapp.enabled' => true,
            'whatsapp.url'     => 'https://wa.flustra.id',
            'whatsapp.key'     => 'test-key',
        ]);

        $user = User::factory()->create([
            'email' => 'error-test@example.com',
            'phone' => '081234567890',
        ]);

        // Tidak boleh melempar exception meski gateway 500
        $notif = NotifikasiMitra::kirim(
            user: $user,
            judul: 'Uji Ketahanan',
            isi: 'Pesan tetap masuk ke lonceng.',
        );

        $this->assertInstanceOf(Notification::class, $notif);
        $this->assertDatabaseHas('notifications', [
            'id'    => $notif->id,
            'title' => 'Uji Ketahanan',
        ]);
    }

    public function test_erp_event_applier_memicu_notifikasi_tri_channel_saat_klaim_disetujui(): void
    {
        Mail::fake();
        Http::fake();

        $user = User::factory()->create([
            'email'        => 'mitra@example.com',
            'phone'        => '081234567891',
            'account_type' => 'umum',
        ]);

        $link = PartnerLink::create([
            'user_id'          => $user->id,
            'partner_type'     => 'customer',
            'company_name'     => 'PT Sinar Jaya',
            'evidence_type'    => 'invite_code',
            'status'           => 'pending',
            'erp_partner_id'   => 123,
            'erp_partner_code' => 'CUST-123',
        ]);

        $applier = app(ErpEventApplier::class);
        $result = $applier->claimVerified($link, 123, 'CUST-123');

        $this->assertTrue($result);
        $this->assertEquals('verified', $link->fresh()->status);
        $this->assertEquals('pelanggan', $user->fresh()->account_type);

        // Pastikan email terkirim
        Mail::assertSent(NotifikasiPortalMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && str_contains($mail->judul, 'disetujui');
        });
    }

    public function test_endpoint_polling_notifikasi_realtime_merespons_dengan_benar(): void
    {
        $user = User::factory()->create();

        Notification::create([
            'user_id'    => $user->id,
            'title'      => 'Tagihan Baru',
            'body'       => 'Tagihan #INV-001 diterbitkan',
            'type'       => 'info',
            'url'        => '/layanan/tagihan',
            'read_at'    => null,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('notifikasi.poll'));

        $response->assertOk()
            ->assertJsonStructure([
                'unread',
                'items' => [
                    '*' => ['id', 'title', 'body', 'type', 'url', 'is_read', 'time'],
                ],
            ])
            ->assertJsonPath('unread', 1)
            ->assertJsonPath('items.0.title', 'Tagihan Baru');
    }

    public function test_template_email_notifikasi_terender_dengan_valid(): void
    {
        $mailable = new NotifikasiPortalMail(
            namaPenerima: 'Budi Hartono',
            judul: 'Konfirmasi Pembayaran Diverifikasi',
            isi: 'Pembayaran tagihan INV-2026-001 sebesar Rp 5.000.000 telah lunas.',
            tipe: 'success',
            actionUrl: 'https://portal.flustra.id/layanan/pembayaran',
            actionText: 'Lihat Bukti Kuitansi',
            nomorReferensi: 'PAY-2026-009',
            namaPerusahaan: 'PT Maju Terus',
        );

        $html = $mailable->render();

        $this->assertStringContainsString('Budi Hartono', $html);
        $this->assertStringContainsString('Konfirmasi Pembayaran Diverifikasi', $html);
        $this->assertStringContainsString('PAY-2026-009', $html);
        $this->assertStringContainsString('PT Maju Terus', $html);
        $this->assertStringContainsString('Lihat Bukti Kuitansi', $html);
        $this->assertStringContainsString('Flustra Client Portal', $html);
    }

    public function test_admin_bisa_menguji_pengiriman_whatsapp(): void
    {
        Http::fake([
            'https://wa.flustra.id/*' => Http::response([
                'status' => 'ok',
                'data'   => ['id' => 'msg-12345'],
            ], 200),
        ]);

        config([
            'whatsapp.enabled' => true,
            'whatsapp.url'     => 'https://wa.flustra.id',
            'whatsapp.key'     => 'test-admin-key',
        ]);

        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.whatsapp.test'), [
                'phone'   => '081234567890',
                'message' => 'Pesan uji coba',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('to', '6281234567890');
    }

    public function test_admin_bisa_menguji_pengiriman_email_enterprise(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.email.test'), [
                'email'   => 'tester@example.com',
                'subject' => 'Tes Pengiriman SMTP',
                'message' => 'Isi email pengujian SMTP',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'ok');

        Mail::assertSent(NotifikasiPortalMail::class, function ($mail) {
            return $mail->hasTo('tester@example.com')
                && $mail->judul === 'Tes Pengiriman SMTP';
        });
    }
}

