<?php

namespace Tests\Feature;

use App\Models\PartnerLink;
use App\Models\User;
use App\Services\Erp\ErpCustomerApi;
use App\Services\Erp\ErpVendorApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Cache pembacaan ERP.
 *
 * Cache adalah pintu belakang yang paling mudah membocorkan isolasi data:
 * satu kunci yang kurang spesifik, dan mitra A membaca tagihan mitra B tanpa
 * satu pun pagar rute yang dilanggar. Karena itu uji terpenting di berkas ini
 * bukan "cache-nya bekerja", melainkan **"cache-nya tidak pernah tercampur"**.
 *
 * Yang kedua: cache tidak boleh terasa oleh penggunanya. Orang yang baru
 * menekan "Kirim" harus melihat keadaan sesudahnya, bukan jawaban dari sebelum
 * ia mengirim.
 */
class CacheBacaErpTest extends TestCase
{
    use RefreshDatabase;

    protected function mitra(string $type, int $erpPartnerId, string $email): User
    {
        $user = User::create([
            'name'         => 'Mitra '.$erpPartnerId,
            'email'        => $email,
            'password'     => Hash::make('KataSandiUji#2026x'),
            'account_type' => $type === 'vendor' ? 'vendor' : 'pelanggan',
            'status'       => 'active',
        ]);

        $link = PartnerLink::create([
            'user_id'        => $user->id,
            'partner_type'   => $type,
            'company_name'   => 'PT Mitra '.$erpPartnerId,
            'evidence_type'  => 'invite_code',
            'evidence_value' => 'UJI-'.$erpPartnerId,
            'status'         => 'verified',
            'erp_partner_id' => $erpPartnerId,
            'verified_at'    => now(),
        ]);

        $user->forceFill(['active_link_id' => $link->id])->save();

        return $user->fresh();
    }

    // =====================================================================

    public function test_pembacaan_berulang_hanya_sekali_menembak_erp(): void
    {
        config(['portal.erp.read_cache_seconds' => 30]);

        $user = $this->mitra('customer', 101, 'cache-a@uji.test');

        Http::fake(['*' => Http::response(['data' => [], 'meta' => []], 200)]);

        $api = app(ErpCustomerApi::class);
        $api->invoices($user);
        $api->invoices($user);
        $api->invoices($user);

        // Tiga kali membuka halaman Tagihan dalam setengah menit, satu panggilan
        // ke ERP. Inilah seluruh gunanya.
        Http::assertSentCount(1);
    }

    public function test_cache_tidak_pernah_tercampur_antar_mitra(): void
    {
        config(['portal.erp.read_cache_seconds' => 30]);

        $a = $this->mitra('customer', 201, 'cache-b@uji.test');
        $b = $this->mitra('customer', 202, 'cache-c@uji.test');

        Http::fake([
            '*/customers/201/*' => Http::response(['data' => [['id' => 'MILIK-A']], 'meta' => []], 200),
            '*/customers/202/*' => Http::response(['data' => [['id' => 'MILIK-B']], 'meta' => []], 200),
        ]);

        $api = app(ErpCustomerApi::class);

        $hasilA = $api->invoices($a);
        $hasilB = $api->invoices($b);

        // Kalau kuncinya kurang spesifik, $hasilB akan berisi MILIK-A — dan
        // isolasi data portal bobol lewat pintu yang tidak dijaga rute mana pun.
        $this->assertSame('MILIK-A', $hasilA['data'][0]['id']);
        $this->assertSame('MILIK-B', $hasilB['data'][0]['id']);
    }

    public function test_pelanggan_dan_vendor_bernomor_sama_tidak_berbagi_cache(): void
    {
        config(['portal.erp.read_cache_seconds' => 30]);

        $pelanggan = $this->mitra('customer', 7, 'cache-d@uji.test');
        $vendor    = $this->mitra('vendor', 7, 'cache-e@uji.test');

        Http::fake([
            '*/customers/7/*' => Http::response(['data' => [['id' => 'PELANGGAN-7']], 'meta' => []], 200),
            '*/vendors/7/*'   => Http::response(['data' => [['id' => 'VENDOR-7']], 'meta' => []], 200),
        ]);

        // customers.id dan vendors.id di ERP berjalan sendiri-sendiri, jadi
        // angka 7 bisa menunjuk dua perusahaan yang sama sekali berbeda.
        $this->assertSame('PELANGGAN-7', app(ErpCustomerApi::class)->invoices($pelanggan)['data'][0]['id']);
        $this->assertSame('VENDOR-7', app(ErpVendorApi::class)->purchaseOrders($vendor)['data'][0]['id']);
    }

    public function test_pengiriman_membuang_cache_mitra_itu(): void
    {
        config(['portal.erp.read_cache_seconds' => 30]);

        $user = $this->mitra('customer', 301, 'cache-f@uji.test');

        Http::fake(['*' => Http::response(['data' => [], 'meta' => []], 200)]);

        $api = app(ErpCustomerApi::class);

        $api->invoices($user);
        $api->decideQuotation($user, 1, ['decision' => 'accepted']);
        $api->invoices($user);

        // baca + tulis + baca ulang. Tanpa pembuangan cache, pembacaan ketiga
        // akan menjawab dari sebelum keputusan dikirim — persis pada momen
        // pengguna paling ingin memastikan kirimannya masuk.
        Http::assertSentCount(3);
    }

    public function test_cache_bisa_dimatikan_lewat_config(): void
    {
        config(['portal.erp.read_cache_seconds' => 0]);

        $user = $this->mitra('customer', 401, 'cache-g@uji.test');

        Http::fake(['*' => Http::response(['data' => [], 'meta' => []], 200)]);

        app(ErpCustomerApi::class)->invoices($user);
        app(ErpCustomerApi::class)->invoices($user);

        Http::assertSentCount(2);
    }
}
