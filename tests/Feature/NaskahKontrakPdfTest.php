<?php

namespace Tests\Feature;

use App\Models\PartnerLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Naskah kontrak di portal adalah PDF dari ERP, bukan salinan HTML.
 *
 * Sebelumnya portal menulis ulang naskah perjanjian sendiri di dalam modal —
 * dua versi yang bisa berbeda, padahal yang dibaca mitra harus persis dokumen
 * yang dipegang tim internal. Uji ini menjaga tiga hal: portal meneruskan PDF
 * ERP apa adanya, kegagalan ERP tidak menghasilkan berkas kosong, dan naskah
 * HTML buatan portal tidak muncul lagi.
 */
class NaskahKontrakPdfTest extends TestCase
{
    use RefreshDatabase;

    private const PDF = "%PDF-1.7\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF";

    protected function mitra(string $tipe, int $erpId): User
    {
        $user = User::create([
            'name'              => 'Uji '.$tipe,
            'email'             => $tipe.'@uji.test',
            'password'          => Hash::make('KataSandiUji#2026x'),
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

        return $user->fresh();
    }

    public function test_pelanggan_menerima_pdf_naskah_dari_erp_apa_adanya(): void
    {
        Http::fake(['*' => Http::response(self::PDF, 200, ['Content-Type' => 'application/pdf'])]);

        $response = $this->actingAs($this->mitra('customer', 501))
            ->get(route('layanan.kontrak.pdf', 9));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(self::PDF, $response->getContent());

        // Id mitra yang dikirim ke ERP digali dari ikatan akun, bukan dari request.
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/customers/501/contracts/9/pdf'));
    }

    public function test_vendor_menerima_pdf_naskah_dari_erp_apa_adanya(): void
    {
        Http::fake(['*' => Http::response(self::PDF, 200, ['Content-Type' => 'application/pdf'])]);

        $response = $this->actingAs($this->mitra('vendor', 702))
            ->get(route('vendor.kontrak.pdf', 4));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(self::PDF, $response->getContent());

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/vendors/702/contracts/4/pdf'));
    }

    public function test_kontrak_milik_mitra_lain_dijawab_404(): void
    {
        // ERP yang menegakkan kepemilikan dan menjawab 404; portal meneruskannya
        // sebagai 404 juga — 403 memberi tahu penebak bahwa nomornya benar.
        Http::fake(['*' => Http::response(['message' => 'Tidak ditemukan.'], 404)]);

        $this->actingAs($this->mitra('customer', 501))
            ->get(route('layanan.kontrak.pdf', 12345))
            ->assertNotFound();
    }

    public function test_erp_mati_mengembalikan_pesan_jujur_bukan_berkas_kosong(): void
    {
        Http::fake(fn () => throw new ConnectionException('ERP tidak menjawab'));

        $response = $this->actingAs($this->mitra('customer', 501))
            ->get(route('layanan.kontrak.pdf', 9));

        $response->assertRedirect(route('layanan.kontrak.index'));
        $response->assertSessionHas('error');
    }

    public function test_pelanggan_tidak_bisa_membuka_naskah_lewat_rute_vendor(): void
    {
        Http::fake(['*' => Http::response(self::PDF, 200)]);

        // Middleware `mitra:vendor` mengalihkan mitra bertipe lain ke beranda.
        $this->actingAs($this->mitra('customer', 501))
            ->get(route('vendor.kontrak.pdf', 9))
            ->assertRedirect(route('beranda'));

        Http::assertNothingSent();
    }

    public function test_daftar_kontrak_tidak_lagi_memuat_naskah_html_buatan_portal(): void
    {
        Http::fake(['*' => Http::response(['data' => [[
            'id' => 9, 'title' => 'Layanan ERP Tahunan', 'start_date' => '2026-10-01', 'end_date' => '2027-09-30',
            'value' => 150000000, 'status' => 'active', 'customer_ack_at' => null, 'needs_acknowledge' => true,
        ]]], 200)]);

        $response = $this->actingAs($this->mitra('customer', 501))->get(route('layanan.kontrak.index'));

        $response->assertOk();
        $response->assertSee('Layanan ERP Tahunan');
        $response->assertSee(route('layanan.kontrak.pdf', 9), false);

        // Salinan naskah lama: kop perusahaan lama dan pasal yang ditulis ulang di portal.
        $response->assertDontSee('PT FLUSTRA TEKNOLOGI NUSANTARA');
        $response->assertDontSee('Draf Naskah');
        $response->assertDontSee('PASAL 1:');
    }
}
