<?php

namespace Tests\Feature;

use App\Models\PartnerLink;
use App\Models\Submission;
use App\Models\User;
use App\Services\KonteksMitra;
use App\Services\ServiceCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Admin portal punya akses penuh — tapi batasnya tetap ada.
 *
 * Dua sisi yang diuji bersama, karena keduanya mudah rusak sendirian:
 * membuka kunci untuk admin gampang membuat pagar mitra ikut longgar, dan
 * memperketat pagar gampang mengunci admin kembali.
 */
class AdminAksesPenuhTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::create([
            'name'              => 'Admin Portal',
            'email'             => 'admin@uji.test',
            'password'          => Hash::make('KataSandiUji#2026x'),
            'role'              => 'admin',
            'account_type'      => 'umum',
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);
    }

    protected function mitra(string $email, string $tipe, int $erpId): PartnerLink
    {
        $user = User::create([
            'name'              => 'Mitra '.$erpId,
            'email'             => $email,
            'password'          => Hash::make('KataSandiUji#2026x'),
            'role'              => 'mitra',
            'account_type'      => $tipe === 'customer' ? 'pelanggan' : 'vendor',
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);

        $link = PartnerLink::create([
            'user_id'        => $user->id,
            'partner_type'   => $tipe,
            'company_name'   => 'PT Uji '.$erpId,
            'evidence_type'  => 'invite_code',
            'evidence_value' => 'UJI-'.$erpId,
            'status'         => 'verified',
            'erp_partner_id' => $erpId,
            'verified_at'    => now(),
        ]);

        $user->forceFill(['active_link_id' => $link->id])->save();

        return $link;
    }

    // =====================================================================
    // Akses penuh
    // =====================================================================

    public function test_admin_melihat_seluruh_kartu_terbuka(): void
    {
        $admin = $this->admin();

        $terkunci = collect(ServiceCatalog::forUser($admin))->where('locked', true);

        $this->assertCount(0, $terkunci, 'Admin tidak boleh punya kartu yang terkunci.');
    }

    public function test_admin_tanpa_konteks_bisa_membuka_halaman_langsung(): void
    {
        $admin = $this->admin();

        $routes = [
            'layanan.data.edit',
            'layanan.tagihan.index',
            'layanan.pembayaran.create',
            'layanan.penawaran.index',
            'layanan.pesanan.index',
            'layanan.pengiriman.index',
            'layanan.retur.create',
            'layanan.kontrak.index',
            'layanan.kredit.index',
            'vendor.data.edit',
            'vendor.po.index',
            'vendor.tagihan.create',
            'vendor.pembayaran.index',
            'vendor.surat-jalan.index',
            'vendor.retur.index',
            'vendor.kontrak.index',
        ];

        foreach ($routes as $route) {
            $this->actingAs($admin)
                ->get(route($route))
                ->assertOk();
        }
    }

    public function test_admin_bisa_membuka_layanan_setelah_memilih_mitra(): void
    {
        $admin = $this->admin();
        $link  = $this->mitra('pelanggan@uji.test', 'customer', 42);

        Http::fake(['*' => Http::response(['data' => [], 'meta' => []], 200)]);

        $this->actingAs($admin)
            ->post(route('admin.lihat-sebagai.pilih', $link))
            ->assertRedirect(route('beranda'));

        $this->actingAs($admin)
            ->get(route('layanan.tagihan.index'))
            ->assertOk();
    }

    public function test_admin_bisa_memilih_mitra_secara_inline(): void
    {
        $admin = $this->admin();
        $link  = $this->mitra('pelanggan@uji.test', 'customer', 42);

        Http::fake(['*' => Http::response(['data' => [], 'meta' => []], 200)]);

        $this->actingAs($admin)
            ->from(route('layanan.tagihan.index'))
            ->post(route('admin.lihat-sebagai.pilih-inline'), ['partner_link_id' => $link->id])
            ->assertRedirect(route('layanan.tagihan.index'));

        $this->actingAs($admin)->get(route('layanan.tagihan.index'))->assertOk();

        Http::assertSent(function ($request) use ($link) {
            return str_contains($request->url(), '/customers/42/')
                && str_contains($request->url(), 'portal_user_id='.$link->user_id);
        });
    }

    public function test_admin_memakai_id_pemilik_link_saat_memanggil_erp(): void
    {
        $admin = $this->admin();
        $link  = $this->mitra('pelanggan@uji.test', 'customer', 42);

        Http::fake(['*' => Http::response(['data' => [], 'meta' => []], 200)]);

        $this->actingAs($admin)->post(route('admin.lihat-sebagai.pilih', $link));
        $this->actingAs($admin)->get(route('layanan.tagihan.index'));

        // ERP memvalidasi portal_user_id terhadap klaim terverifikasi, dan
        // admin tidak punya klaim apa pun — jadi yang dikirim wajib id PEMILIK.
        Http::assertSent(function ($request) use ($link, $admin) {
            return str_contains($request->url(), '/customers/42/')
                && str_contains($request->url(), 'portal_user_id='.$link->user_id)
                && ! str_contains($request->url(), 'portal_user_id='.$admin->id);
        });
    }

    public function test_admin_bisa_berpindah_antar_mitra_tanpa_terkunci(): void
    {
        $admin    = $this->admin();
        $customer = $this->mitra('c@uji.test', 'customer', 10);
        $vendor   = $this->mitra('v@uji.test', 'vendor', 20);

        Http::fake(['*' => Http::response(['data' => [], 'meta' => []], 200)]);

        $this->actingAs($admin)->post(route('admin.lihat-sebagai.pilih', $customer));

        // Berpindah juga POST — tanpa pengecualian di TolakTulisSaatLihatSebagai,
        // admin akan terkunci di mitra pertama sampai logout.
        $this->actingAs($admin)
            ->post(route('admin.lihat-sebagai.pilih', $vendor))
            ->assertRedirect(route('beranda'));

        $this->actingAs($admin)->get(route('vendor.po.index'))->assertOk();
    }

    // =====================================================================
    // Batasnya
    // =====================================================================

    public function test_admin_tidak_bisa_mengirim_pengajuan_atas_nama_mitra(): void
    {
        $admin = $this->admin();
        $link  = $this->mitra('pelanggan@uji.test', 'customer', 42);

        Http::fake();

        $this->actingAs($admin)->post(route('admin.lihat-sebagai.pilih', $link));

        $this->actingAs($admin)->post(route('layanan.penawaran.decide', 1), [
            'decision' => 'accepted',
            'number'   => 'QTN/2026/08/0001',
        ]);

        // Riwayat mitra tidak boleh memuat hal yang tidak pernah ia lakukan.
        $this->assertSame(0, Submission::withoutGlobalScope('milik_sendiri')->count());
    }

    public function test_konteks_hilang_bila_akses_mitra_dicabut(): void
    {
        $admin = $this->admin();
        $link  = $this->mitra('pelanggan@uji.test', 'customer', 42);

        $this->actingAs($admin)->post(route('admin.lihat-sebagai.pilih', $link));

        $link->forceFill(['status' => 'revoked'])->save();

        // Link yang dicabut tidak boleh tetap bisa dilihat hanya karena masih
        // tertinggal di sesi admin. Konteks reset dan halaman tetap terbuka 200 OK.
        $this->actingAs($admin)
            ->get(route('layanan.tagihan.index'))
            ->assertOk();

        $this->assertNull(KonteksMitra::pilihanAdmin());
    }

    // =====================================================================
    // Pagar mitra biasa tidak boleh ikut longgar
    // =====================================================================

    public function test_mitra_biasa_tidak_bisa_membuka_ruang_admin(): void
    {
        $link = $this->mitra('pelanggan@uji.test', 'customer', 42);

        foreach (['admin.dashboard', 'admin.maintenance', 'admin.lihat-sebagai'] as $rute) {
            $this->actingAs($link->user)->get(route($rute))->assertNotFound();
        }
    }

    public function test_mitra_biasa_tidak_bisa_memilih_lihat_sebagai(): void
    {
        $customer = $this->mitra('c@uji.test', 'customer', 10);
        $vendor   = $this->mitra('v@uji.test', 'vendor', 20);

        $this->actingAs($customer->user)
            ->post(route('admin.lihat-sebagai.pilih', $vendor))
            ->assertNotFound();

        $this->assertNull(session(KonteksMitra::KUNCI_SESI));
    }

    public function test_mitra_biasa_tetap_terkunci_dari_kartu_peran_lain(): void
    {
        $customer = $this->mitra('c@uji.test', 'customer', 10);

        $terkunci = collect(ServiceCatalog::forUser($customer->user))->where('locked', true);

        $this->assertGreaterThan(0, $terkunci->count(), 'Mitra biasa harus tetap punya kartu terkunci.');
    }
}
