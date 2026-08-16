<?php

namespace Tests\Feature;

use App\Jobs\SyncSubmissionToErp;
use App\Models\Notification;
use App\Models\PartnerLink;
use App\Models\Submission;
use App\Models\User;
use App\Services\Erp\ErpClient;
use App\Services\Erp\ErpEventApplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Uji jaring pengaman terakhir portal: **tidak ada pengajuan yang boleh mandek
 * selamanya.**
 *
 * Webhook adalah jalur utama dan hampir selalu cukup. Yang diuji di sini justru
 * keadaan sebaliknya — webhook-nya tidak pernah sampai. Sebelum ada
 * `GET /submissions/{ref}/status` yang umum, satu webhook yang hilang berarti
 * mitra melihat "Diterima Sistem" selamanya walau urusannya sudah selesai
 * berbulan-bulan lalu, dan tidak ada apa pun yang akan memperbaikinya sendiri.
 */
class RekonsiliasiStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function penggunaMitra(string $type = 'vendor'): User
    {
        $user = User::create([
            'name'              => 'Uji Rekonsiliasi',
            'email'             => 'rekonsiliasi@uji.test',
            'password'          => Hash::make('KataSandiUji#2026x'),
            'account_type'      => $type === 'vendor' ? 'vendor' : 'pelanggan',
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);

        $link = PartnerLink::create([
            'user_id'        => $user->id,
            'partner_type'   => $type,
            'company_name'   => 'PT Uji Rekonsiliasi',
            'evidence_type'  => 'invite_code',
            'evidence_value' => 'UJI-R',
            'status'         => 'verified',
            'erp_partner_id' => 77,
            'verified_at'    => now(),
        ]);

        $user->forceFill(['active_link_id' => $link->id])->save();

        return $user->fresh();
    }

    protected function pengajuan(User $user, string $type, string $status, string $syncState = 'synced'): Submission
    {
        return Submission::create([
            'user_id'          => $user->id,
            'partner_link_id'  => $user->active_link_id,
            'type'             => $type,
            'reference_number' => 'PRT-REK-'.rand(10000, 99999),
            'title'            => 'Pengajuan uji rekonsiliasi',
            'payload'          => [],
            'status'           => $status,
            'submitted_at'     => now()->subHours(2),
            'last_status_at'   => now()->subHours(2),
            'sync_state'       => $syncState,
            'synced_at'        => $syncState === 'synced' ? now()->subHours(2) : null,
        ]);
    }

    // =====================================================================
    // Pengajuan yang dulu tidak punya penutup
    // =====================================================================

    public function test_surat_jalan_berakhir_final_bukan_menggantung_di_diterima_sistem(): void
    {
        $user = $this->penggunaMitra('vendor');
        $s    = $this->pengajuan($user, 'shipping_doc', 'submitted', 'pending');

        Http::fake(['*' => Http::response(['data' => ['erp_record_id' => 9]], 201)]);

        (new SyncSubmissionToErp($s->id))->handle(app(ErpClient::class), app(ErpEventApplier::class));

        $s->refresh();

        // Surat jalan adalah pemberitahuan, bukan permintaan: tidak ada layar
        // staf yang menyetujui atau menolaknya, jadi tidak akan pernah ada yang
        // memajukan statusnya dari 'received'.
        $this->assertSame('approved', $s->status);
        $this->assertTrue($s->isFinal(), 'Surat jalan tanpa status final akan mandek selamanya.');
    }

    public function test_keputusan_penawaran_dan_persetujuan_kontrak_juga_berakhir_final(): void
    {
        $user = $this->penggunaMitra('customer');

        Http::fake(['*' => Http::response(['data' => []], 200)]);

        foreach (['quotation_decision', 'contract_ack'] as $type) {
            $s = $this->pengajuan($user, $type, 'submitted', 'pending');

            (new SyncSubmissionToErp($s->id))->handle(app(ErpClient::class), app(ErpEventApplier::class));

            $this->assertTrue(
                $s->fresh()->isFinal(),
                $type.' tidak punya tahap staf sesudahnya, jadi harus berakhir final.'
            );
        }
    }

    // =====================================================================
    // Rekonsiliasi generik
    // =====================================================================

    public function test_status_lamaran_menyusul_lewat_endpoint_umum_saat_webhook_hilang(): void
    {
        $user = $this->penggunaMitra('customer');
        $s    = $this->pengajuan($user, 'job_application', 'received');

        Http::fake([
            '*/submissions/*/status' => Http::response(['data' => [
                'status'        => 'rejected',
                'reason'        => 'Terima kasih atas ketertarikan Anda.',
                'erp_reference' => 'CAND-12',
            ]], 200),
        ]);

        $this->artisan('portal:sync-status')->assertSuccessful();

        $s->refresh();

        $this->assertSame('rejected', $s->status);
        $this->assertSame('CAND-12', $s->erp_reference);

        // Timeline tidak boleh bolong: perpindahan status selalu lewat
        // transitionTo(), bukan update('status') langsung.
        $this->assertDatabaseHas('submission_status_histories', [
            'submission_id' => $s->id,
            'from_status'   => 'received',
            'to_status'     => 'rejected',
        ]);

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_rfq_yang_dimenangkan_di_crm_menyusul_jadi_disetujui(): void
    {
        $user = $this->penggunaMitra('customer');
        $s    = $this->pengajuan($user, 'rfq', 'received');

        Http::fake([
            '*/submissions/*/status' => Http::response(['data' => [
                'status'        => 'approved',
                'reason'        => 'Permintaan penawaran Anda sudah disepakati.',
                'erp_reference' => 'DEAL-3',
            ]], 200),
        ]);

        $this->artisan('portal:sync-status')->assertSuccessful();

        $this->assertSame('approved', $s->fresh()->status);
    }

    public function test_404_dari_erp_berarti_kirim_ulang_bukan_diamkan(): void
    {
        Queue::fake();

        $user = $this->penggunaMitra('vendor');
        $s    = $this->pengajuan($user, 'vendor_bill', 'received');

        // Ditandai 'synced' di portal, tapi ERP tidak mengenalnya: responsnya
        // hilang di tengah jalan. Menunggu status yang tidak akan pernah ada
        // berarti pengajuannya mandek permanen.
        Http::fake(['*/submissions/*/status' => Http::response(['message' => 'tidak ditemukan'], 404)]);

        $this->artisan('portal:sync-status')->assertSuccessful();

        $this->assertSame('pending', $s->fresh()->sync_state);
        $this->assertSame('received', $s->fresh()->status, 'Status pengguna tidak boleh ikut turun.');

        Queue::assertPushed(SyncSubmissionToErp::class);
    }

    public function test_pengajuan_yang_sudah_final_tidak_ditarik_lagi(): void
    {
        $user = $this->penggunaMitra('customer');
        $this->pengajuan($user, 'sales_return', 'approved');
        $this->pengajuan($user, 'quotation_decision', 'rejected');

        Http::fake();

        $this->artisan('portal:sync-status')->assertSuccessful();

        // Keputusannya sudah sampai ke pengguna. Menariknya lagi tiap 15 menit
        // hanya membebani ERP tanpa hasil.
        Http::assertNothingSent();
    }

    public function test_status_asing_dari_erp_diabaikan_bukan_disimpan(): void
    {
        $user = $this->penggunaMitra('customer');
        $s    = $this->pengajuan($user, 'inquiry', 'received');

        Http::fake([
            '*/submissions/*/status' => Http::response(['data' => ['status' => 'entah_apa']], 200),
        ]);

        $this->artisan('portal:sync-status')->assertSuccessful();

        $this->assertSame('received', $s->fresh()->status);
        $this->assertSame(0, Notification::count());
    }
}
