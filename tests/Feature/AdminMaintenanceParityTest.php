<?php

namespace Tests\Feature;

use App\Mail\NotifikasiPortalMail;
use App\Models\PortalSetting;
use App\Models\User;
use App\Services\Maintenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminMaintenanceParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_bisa_membuka_halaman_maintenance(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->get(route('admin.maintenance'));

        $response->assertOk()
            ->assertSee('Konfigurasi Jadwal Maintenance')
            ->assertSee('Kontrol Notifikasi')
            ->assertSee('Pop-up Banner')
            ->assertSee('Email Siaran')
            ->assertSee('Pesan WhatsApp')
            ->assertSee('Kunci Akses Portal');
    }

    public function test_admin_bisa_menyimpan_konfigurasi_jadwal(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->post(route('admin.maintenance.update'), [
                'title'              => 'Pemeliharaan Server Database Terjadwal',
                'scheduled_at'       => '2026-09-01T01:00',
                'estimated_duration' => '2 Jam',
                'severity'           => 'warning',
                'description'        => 'Peningkatan performa database portal.',
            ]);

        $response->assertRedirect(route('admin.maintenance'));
        $this->assertEquals('Pemeliharaan Server Database Terjadwal', PortalSetting::ambil(Maintenance::LOKAL_JUDUL));
        $this->assertEquals('2 Jam', PortalSetting::ambil(Maintenance::LOKAL_DURASI));
        $this->assertEquals('warning', PortalSetting::ambil(Maintenance::LOKAL_TINGKAT));
    }

    public function test_admin_bisa_toggle_banner_dan_lockdown(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Toggle Banner ON
        $response = $this->actingAs($admin)
            ->postJson(route('admin.maintenance.banner'), ['is_active' => true]);
        $response->assertOk()->assertJson(['success' => true]);
        $this->assertEquals('1', PortalSetting::ambil(Maintenance::LOKAL_AKTIF));

        // Toggle Lockdown ON
        $response = $this->actingAs($admin)
            ->postJson(route('admin.maintenance.lockdown'), ['is_active' => true]);
        $response->assertOk()->assertJson(['success' => true]);
        $this->assertEquals('1', PortalSetting::ambil(Maintenance::LOKAL_LOCKDOWN));
    }

    public function test_mode_lockdown_menolak_login_pengguna_biasa_tapi_mengizinkan_admin(): void
    {
        PortalSetting::simpan(Maintenance::LOKAL_LOCKDOWN, '1');

        $user = User::factory()->create([
            'email'    => 'mitra@example.com',
            'password' => bcrypt('password123'),
            'role'     => 'pelanggan',
            'status'   => 'active',
        ]);

        $admin = User::factory()->create([
            'email'    => 'admin@example.com',
            'password' => bcrypt('password123'),
            'role'     => 'admin',
            'status'   => 'active',
        ]);

        // Login user biasa ditolak
        $responseUser = $this->post(route('login'), [
            'email'    => 'mitra@example.com',
            'password' => 'password123',
        ]);
        $responseUser->assertSessionHasErrors('email');
        $this->assertGuest();

        // Login admin berhasil
        $responseAdmin = $this->post(route('login'), [
            'email'    => 'admin@example.com',
            'password' => 'password123',
        ]);
        $responseAdmin->assertRedirect(route('beranda'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_broadcast_email_maintenance_terkirim_ke_semua_pengguna_aktif(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $user1 = User::factory()->create(['status' => 'active', 'email' => 'user1@example.com']);
        $user2 = User::factory()->create(['status' => 'active', 'email' => 'user2@example.com']);

        PortalSetting::simpanBanyak([
            Maintenance::LOKAL_JUDUL   => 'Maintenance Rutin',
            Maintenance::LOKAL_PESAN   => 'Pembersihan cache',
            Maintenance::LOKAL_TINGKAT => 'info',
            Maintenance::LOKAL_JADWAL  => '2026-09-01T00:00',
            Maintenance::LOKAL_DURASI  => '1 Jam',
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.maintenance.email'));

        $response->assertOk()
            ->assertJson(['success' => true, 'count' => 3]); // admin + user1 + user2

        Mail::assertQueued(NotifikasiPortalMail::class, 3);
    }

    public function test_broadcast_wa_maintenance_terkirim_ke_pengguna_yang_punya_nomor(): void
    {
        Http::fake([
            'https://wa.flustra.id/*' => Http::response(['status' => 'ok', 'data' => ['id' => 'msg-123']], 200),
        ]);

        config([
            'whatsapp.enabled' => true,
            'whatsapp.url'     => 'https://wa.flustra.id',
            'whatsapp.key'     => 'test-key',
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081234567890']);
        $userWithPhone = User::factory()->create(['status' => 'active', 'phone' => '081987654321']);
        $userWithoutPhone = User::factory()->create(['status' => 'active', 'phone' => null]);

        PortalSetting::simpanBanyak([
            Maintenance::LOKAL_JUDUL   => 'Maintenance Rutin',
            Maintenance::LOKAL_PESAN   => 'Pembaruan server',
            Maintenance::LOKAL_JADWAL  => '2026-09-01T00:00',
            Maintenance::LOKAL_DURASI  => '1 Jam',
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.maintenance.wa'));

        $response->assertOk()
            ->assertJson(['success' => true, 'count' => 2]);
    }

    public function test_selesaikan_maintenance_mematikan_banner_dan_mengirim_notifikasi(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['status' => 'active', 'email' => 'partner@example.com']);

        PortalSetting::simpanBanyak([
            Maintenance::LOKAL_AKTIF      => '1',
            Maintenance::LOKAL_EMAIL_SENT => '1',
            Maintenance::LOKAL_WA_SENT    => '0',
            Maintenance::LOKAL_LOCKDOWN   => '1',
            Maintenance::LOKAL_JUDUL      => 'Maintenance Portal',
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.maintenance.complete'));

        $response->assertOk()
            ->assertJson(['success' => true, 'email_count' => 2, 'wa_count' => 0]);

        $this->assertEquals('0', PortalSetting::ambil(Maintenance::LOKAL_AKTIF));
        $this->assertEquals('0', PortalSetting::ambil(Maintenance::LOKAL_LOCKDOWN));
        $this->assertEquals('0', PortalSetting::ambil(Maintenance::LOKAL_EMAIL_SENT));
        Mail::assertQueued(NotifikasiPortalMail::class, 2);
    }
}
