<?php

namespace Tests\Feature;

use App\Models\PartnerLink;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Uji penjagaan penerima webhook.
 *
 * Rute ini satu-satunya jalan bagi dunia luar untuk menaikkan `account_type`
 * seseorang. Setiap penjaganya diuji terpisah supaya tidak ada yang bisa
 * dilonggarkan diam-diam saat berkasnya dirapikan nanti.
 */
class WebhookErpTest extends TestCase
{
    use RefreshDatabase;

    protected const RAHASIA = 'rahasia-uji';

    protected function setUp(): void
    {
        parent::setUp();
        config(['portal.webhook_secret' => self::RAHASIA]);
    }

    /** @param array<string, mixed> $data */
    protected function kirim(string $event, array $data, array $opsi = []): \Illuminate\Testing\TestResponse
    {
        $body = json_encode([
            'event'   => $event,
            'data'    => $data,
            'sent_at' => $opsi['sent_at'] ?? now()->toIso8601String(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $tanda = $opsi['signature']
            ?? 'sha256='.hash_hmac('sha256', $body, $opsi['secret'] ?? self::RAHASIA);

        return $this->call(
            'POST',
            '/api/webhooks/erp',
            [], [], [],
            array_filter([
                'CONTENT_TYPE'          => 'application/json',
                'HTTP_X_ERP_SIGNATURE'  => $tanda,
                'HTTP_X_ERP_REQUEST_ID' => $opsi['request_id'] ?? (string) \Illuminate\Support\Str::uuid(),
            ]),
            $body,
        );
    }

    protected function link(): PartnerLink
    {
        $user = User::create([
            'name'         => 'Uji Webhook',
            'email'        => 'webhook@uji.test',
            'password'     => Hash::make('KataSandiUji#2026x'),
            'account_type' => 'umum',
            'status'       => 'active',
        ]);

        return PartnerLink::create([
            'user_id'        => $user->id,
            'partner_type'   => 'customer',
            'company_name'   => 'PT Uji Webhook',
            'evidence_type'  => 'invite_code',
            'evidence_value' => 'UJI-1',
            'status'         => 'pending',
        ]);
    }

    // =====================================================================

    public function test_rahasia_kosong_menolak_semua_kiriman(): void
    {
        config(['portal.webhook_secret' => '']);

        // 503, bukan 401: bedanya penting saat menelusuri masalah — ini
        // konfigurasi yang belum diisi, bukan penyerang yang salah tebak.
        $this->kirim('claim.rejected', ['portal_link_id' => 1, 'reason' => 'x'])
            ->assertStatus(503);
    }

    public function test_tanda_tangan_salah_ditolak(): void
    {
        $this->kirim('claim.rejected', ['portal_link_id' => 1, 'reason' => 'x'], ['signature' => 'sha256=palsu'])
            ->assertStatus(401);
    }

    public function test_tanda_tangan_dari_rahasia_lain_ditolak(): void
    {
        $this->kirim('claim.rejected', ['portal_link_id' => 1, 'reason' => 'x'], ['secret' => 'rahasia-lain'])
            ->assertStatus(401);
    }

    public function test_kiriman_kedaluwarsa_ditolak(): void
    {
        $this->kirim(
            'claim.rejected',
            ['portal_link_id' => 1, 'reason' => 'x'],
            ['sent_at' => now()->subHour()->toIso8601String()],
        )->assertStatus(422);
    }

    public function test_tanpa_request_id_ditolak(): void
    {
        $this->kirim('claim.rejected', ['portal_link_id' => 1, 'reason' => 'x'], ['request_id' => ''])
            ->assertStatus(422);
    }

    public function test_kiriman_ulang_dengan_request_id_sama_tidak_diproses_dua_kali(): void
    {
        $link = $this->link();
        $id   = 'uji-kembar-1';

        $this->kirim('claim.rejected', ['portal_link_id' => $link->id, 'reason' => 'Bukti kurang.'], ['request_id' => $id])
            ->assertOk();

        $this->kirim('claim.rejected', ['portal_link_id' => $link->id, 'reason' => 'Bukti kurang.'], ['request_id' => $id])
            ->assertOk()
            ->assertJsonPath('message', 'Sudah pernah diproses.');

        // Satu penolakan, satu notifikasi — bukan dua.
        $this->assertSame(1, \App\Models\Notification::where('user_id', $link->user_id)->count());
    }

    public function test_claim_verified_menaikkan_tipe_akun(): void
    {
        $link = $this->link();

        $this->kirim('claim.verified', [
            'portal_link_id' => $link->id,
            'partner_type'   => 'customer',
            'erp_partner_id' => 42,
        ])->assertOk();

        $link->refresh();
        $user = $link->user->fresh();

        $this->assertSame('verified', $link->status);
        $this->assertSame(42, (int) $link->erp_partner_id);
        $this->assertSame('pelanggan', $user->account_type);
        $this->assertSame($link->id, $user->active_link_id);
    }

    public function test_claim_verified_tanpa_erp_partner_id_ditolak(): void
    {
        $link = $this->link();

        $this->kirim('claim.verified', ['portal_link_id' => $link->id, 'partner_type' => 'customer'])
            ->assertStatus(422);

        $this->assertSame('pending', $link->fresh()->status);
    }

    public function test_link_yang_tidak_ada_dibalas_404(): void
    {
        $this->kirim('claim.verified', ['portal_link_id' => 999999, 'erp_partner_id' => 1])
            ->assertStatus(404);
    }

    public function test_event_tidak_dikenal_ditolak(): void
    {
        $this->kirim('tidak.ada', [])->assertStatus(422);
    }

    public function test_partner_revoked_menurunkan_akun_ke_umum(): void
    {
        $link = $this->link();

        $this->kirim('claim.verified', [
            'portal_link_id' => $link->id,
            'erp_partner_id' => 7,
        ])->assertOk();

        $this->kirim('partner.revoked', [
            'portal_link_id' => $link->id,
            'reason'         => 'Kerja sama berakhir.',
        ])->assertOk();

        $user = $link->user->fresh();

        $this->assertSame('revoked', $link->fresh()->status);
        $this->assertSame('umum', $user->account_type);
        $this->assertNull($user->active_link_id);
    }

    public function test_submission_status_changed_menambah_timeline(): void
    {
        $link = $this->link();

        $pengajuan = Submission::create([
            'user_id'          => $link->user_id,
            'partner_link_id'  => $link->id,
            'type'             => 'payment_confirmation',
            'reference_number' => 'PRT-TEST-9001',
            'title'            => 'Konfirmasi pembayaran uji',
            'status'           => 'received',
            'submitted_at'     => now(),
            'sync_state'       => 'synced',
        ]);

        $this->kirim('submission.status_changed', [
            'portal_reference' => 'PRT-TEST-9001',
            'status'           => 'approved',
            'reason'           => 'Pembayaran cocok.',
            'erp_reference'    => 'INV/2026/08/0001',
        ])->assertOk();

        $pengajuan->refresh();

        $this->assertSame('approved', $pengajuan->status);
        $this->assertSame('INV/2026/08/0001', $pengajuan->erp_reference);
        $this->assertTrue($pengajuan->histories()->where('to_status', 'approved')->exists());
    }

    public function test_status_yang_tidak_dikenal_ditolak(): void
    {
        $this->kirim('submission.status_changed', [
            'portal_reference' => 'PRT-TEST-1',
            'status'           => 'dihapus-selamanya',
        ])->assertStatus(422);
    }
}
