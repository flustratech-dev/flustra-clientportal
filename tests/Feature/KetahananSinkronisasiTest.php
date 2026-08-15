<?php

namespace Tests\Feature;

use App\Jobs\SyncSubmissionToErp;
use App\Models\PartnerLink;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Uji janji terpenting portal: **kegagalan ERP tidak boleh jadi kegagalan
 * pengguna.**
 *
 * Yang diuji bukan bahwa pengirimannya berhasil — itu mudah. Yang diuji adalah
 * apa yang terjadi ketika ERP mati, menolak, atau menjawab dua kali.
 */
class KetahananSinkronisasiTest extends TestCase
{
    use RefreshDatabase;

    protected function penggunaMitra(): User
    {
        $user = User::create([
            'name'              => 'Uji Ketahanan',
            'email'             => 'ketahanan@uji.test',
            'password'          => Hash::make('KataSandiUji#2026x'),
            'account_type'      => 'pelanggan',
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);

        $link = PartnerLink::create([
            'user_id'        => $user->id,
            'partner_type'   => 'customer',
            'company_name'   => 'PT Uji Ketahanan',
            'evidence_type'  => 'invite_code',
            'evidence_value' => 'UJI-K',
            'status'         => 'verified',
            'erp_partner_id' => 55,
            'verified_at'    => now(),
        ]);

        $user->forceFill(['active_link_id' => $link->id])->save();

        return $user->fresh();
    }

    protected function pengajuan(User $user, string $type = 'partner_claim', array $payload = []): Submission
    {
        return Submission::create([
            'user_id'          => $user->id,
            'partner_link_id'  => $user->active_link_id,
            'type'             => $type,
            'reference_number' => 'PRT-TEST-'.rand(10000, 99999),
            'title'            => 'Pengajuan uji',
            'payload'          => $payload,
            'status'           => 'submitted',
            'submitted_at'     => now(),
            'sync_state'       => 'pending',
        ]);
    }

    // =====================================================================

    public function test_aksi_pengguna_tetap_berhasil_walau_erp_mati(): void
    {
        Queue::fake();

        $user = $this->penggunaMitra();

        // ERP tidak dipanggil sama sekali di dalam request — pengiriman
        // dititipkan ke antrean. Inilah yang membuat halaman tetap cepat dan
        // tidak ikut mati bersama ERP.
        $respons = $this->actingAs($user)->post(route('layanan.penawaran.decide', 1), [
            'decision' => 'accepted',
            'number'   => 'QTN/2026/08/0001',
            'amount'   => 1000000,
        ]);

        $respons->assertRedirect(route('layanan.penawaran.index'));

        $this->assertDatabaseHas('submissions', [
            'user_id'    => $user->id,
            'type'       => 'quotation_decision',
            'status'     => 'submitted',
            'sync_state' => 'pending',
        ]);

        Queue::assertPushed(SyncSubmissionToErp::class);
    }

    public function test_erp_mati_menandai_gagal_tanpa_menghapus_pengajuan(): void
    {
        $user = $this->penggunaMitra();
        $s    = $this->pengajuan($user, 'quotation_decision', ['quotation_id' => 1, 'decision' => 'accepted']);

        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('ERP tidak menjawab'));

        $job = new SyncSubmissionToErp($s->id);

        try {
            $job->handle(app(\App\Services\Erp\ErpClient::class), app(\App\Services\Erp\ErpEventApplier::class));
            $this->fail('Job seharusnya melempar agar antrean mengulangnya.');
        } catch (\App\Services\Erp\ErpException $e) {
            // Kegagalan koneksi HARUS boleh diulang; kalau tidak, gangguan
            // sesaat akan permanen menggagalkan pengajuan orang.
            $this->assertTrue($e->retryable);
        }

        $s->refresh();

        $this->assertSame('pending', $s->sync_state);
        $this->assertSame('submitted', $s->status, 'Status pengguna tidak boleh ikut turun.');
        $this->assertNotNull($s->sync_error);
    }

    public function test_penolakan_4xx_tidak_diulang_berkali_kali(): void
    {
        $user = $this->penggunaMitra();
        $s    = $this->pengajuan($user, 'quotation_decision', ['quotation_id' => 1, 'decision' => 'accepted']);

        Http::fake(['*' => Http::response(['message' => 'Penawaran sudah diputuskan.'], 422)]);

        $job = new SyncSubmissionToErp($s->id);

        // Sengaja TIDAK melempar: job memanggil $this->fail() sendiri supaya
        // antrean berhenti di percobaan pertama alih-alih menghabiskan lima
        // percobaan untuk muatan yang jawabannya tidak akan berubah.
        $job->handle(
            app(\App\Services\Erp\ErpClient::class),
            app(\App\Services\Erp\ErpEventApplier::class),
        );

        $s->refresh();

        $this->assertStringContainsString('422', (string) $s->sync_error);
        $this->assertSame('submitted', $s->status, 'Status pengguna tidak boleh ikut turun.');

        // failed() adalah yang dipanggil antrean setelah job menyerah.
        $job->failed(new \App\Services\Erp\ErpException('ditolak', retryable: false, statusCode: 422));

        $this->assertSame('failed', $s->fresh()->sync_state);
    }

    public function test_pengajuan_yang_sudah_tersinkron_tidak_dikirim_dua_kali(): void
    {
        $user = $this->penggunaMitra();
        $s    = $this->pengajuan($user);
        $s->forceFill(['sync_state' => 'synced'])->save();

        Http::fake();

        (new SyncSubmissionToErp($s->id))->handle(
            app(\App\Services\Erp\ErpClient::class),
            app(\App\Services\Erp\ErpEventApplier::class),
        );

        Http::assertNothingSent();
    }

    public function test_setiap_panggilan_erp_tercatat_di_api_sync_logs(): void
    {
        $user = $this->penggunaMitra();
        $s    = $this->pengajuan($user, 'quotation_decision', ['quotation_id' => 1, 'decision' => 'accepted']);

        Http::fake(['*' => Http::response(['data' => ['status' => 'accepted']], 200)]);

        (new SyncSubmissionToErp($s->id))->handle(
            app(\App\Services\Erp\ErpClient::class),
            app(\App\Services\Erp\ErpEventApplier::class),
        );

        // Tanpa jejak ini, menelusuri "pengajuan saya tidak muncul di ERP"
        // berarti menebak-nebak.
        $this->assertDatabaseHas('api_sync_logs', [
            'direction'     => 'outbound',
            'submission_id' => $s->id,
            'status_code'   => 200,
        ]);
    }

    public function test_jenis_pengajuan_tak_dikenal_gagal_cepat(): void
    {
        $user = $this->penggunaMitra();
        $s    = $this->pengajuan($user, 'entah_apa');

        Http::fake();

        (new SyncSubmissionToErp($s->id))->handle(
            app(\App\Services\Erp\ErpClient::class),
            app(\App\Services\Erp\ErpEventApplier::class),
        );

        // Gagal SEBELUM menyentuh jaringan: jenis yang tidak dikenal tidak
        // punya endpoint tujuan, jadi tidak ada yang perlu dicoba.
        Http::assertNothingSent();
        $this->assertStringContainsString('belum punya jalur sinkronisasi', (string) $s->fresh()->sync_error);
    }
}
