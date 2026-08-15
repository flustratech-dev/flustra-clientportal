<?php

namespace Tests\Feature;

use App\Models\PartnerLink;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Uji isolasi data antar-mitra.
 *
 * Ini uji yang paling penting di seluruh berkas uji portal. PRD §13.1
 * menyebutnya syarat mutlak, dan alasannya sederhana: portal ini membuka data
 * keuangan perusahaan kepada pihak luar. Satu kebocoran di sini bukan bug
 * biasa — ia menghancurkan alasan portalnya dipisah dari ERP.
 *
 * Yang diuji bukan tampilannya, melainkan jawaban servernya. Menyembunyikan
 * tombol tidak menghentikan siapa pun yang mengetik URL sendiri.
 */
class IsolasiDataTest extends TestCase
{
    use RefreshDatabase;

    protected function pengguna(string $email, string $tipe = 'umum'): User
    {
        return User::create([
            'name'              => 'Uji '.$email,
            'email'             => $email,
            'password'          => Hash::make('KataSandiUji#2026x'),
            'account_type'      => $tipe,
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);
    }

    protected function mitra(User $user, string $tipe, int $erpId): PartnerLink
    {
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

        $user->forceFill([
            'account_type'   => $tipe === 'customer' ? 'pelanggan' : 'vendor',
            'active_link_id' => $link->id,
        ])->save();

        return $link;
    }

    protected function pengajuan(User $user, array $extra = []): Submission
    {
        return Submission::create([
            'user_id'          => $user->id,
            'type'             => 'partner_claim',
            'reference_number' => 'PRT-TEST-'.$user->id.'-'.rand(1000, 9999),
            'title'            => 'Pengajuan milik '.$user->email,
            'status'           => 'submitted',
            'submitted_at'     => now(),
            'sync_state'       => 'pending',
        ] + $extra);
    }

    // =====================================================================

    public function test_pengguna_tidak_bisa_membuka_pengajuan_milik_orang_lain(): void
    {
        $a = $this->pengguna('a@uji.test');
        $b = $this->pengguna('b@uji.test');

        $milikB = $this->pengajuan($b);

        // 404, bukan 403: membedakan "tidak boleh" dari "tidak ada" memberi
        // tahu penebak bahwa nomor yang dicobanya benar.
        $this->actingAs($a)
            ->get(route('riwayat.show', $milikB->id))
            ->assertNotFound();
    }

    public function test_pengajuan_orang_lain_tidak_muncul_di_daftar_riwayat(): void
    {
        $a = $this->pengguna('a@uji.test');
        $b = $this->pengguna('b@uji.test');

        $this->pengajuan($a);
        $milikB = $this->pengajuan($b);

        $this->actingAs($a)
            ->get(route('riwayat.index'))
            ->assertOk()
            ->assertDontSee($milikB->reference_number);
    }

    public function test_pengajuan_orang_lain_tidak_muncul_di_pencarian(): void
    {
        $a = $this->pengguna('a@uji.test');
        $b = $this->pengguna('b@uji.test');

        $milikB = $this->pengajuan($b);

        $this->actingAs($a)
            ->getJson(route('cari', ['q' => $milikB->reference_number]))
            ->assertOk()
            ->assertJsonPath('hasil', []);
    }

    public function test_akun_umum_ditolak_dari_seluruh_layanan_pelanggan(): void
    {
        $umum = $this->pengguna('umum@uji.test');

        foreach (['layanan.tagihan.index', 'layanan.penawaran.index', 'layanan.kredit.index', 'layanan.data.edit'] as $rute) {
            $this->actingAs($umum)
                ->get(route($rute))
                ->assertRedirect(route('beranda'));
        }
    }

    public function test_vendor_ditolak_dari_layanan_pelanggan_dan_sebaliknya(): void
    {
        $vendor = $this->pengguna('vendor@uji.test');
        $this->mitra($vendor, 'vendor', 10);

        $pelanggan = $this->pengguna('pelanggan@uji.test');
        $this->mitra($pelanggan, 'customer', 20);

        $this->actingAs($vendor)->get(route('layanan.tagihan.index'))->assertRedirect(route('beranda'));
        $this->actingAs($pelanggan)->get(route('vendor.po.index'))->assertRedirect(route('beranda'));
    }

    public function test_link_yang_dicabut_langsung_menutup_akses(): void
    {
        $user = $this->pengguna('dicabut@uji.test');
        $link = $this->mitra($user, 'customer', 30);

        // account_type sengaja dibiarkan 'pelanggan' — inilah kasusnya:
        // cerminan bisa tertinggal, ikatannya tidak. Yang menentukan akses
        // adalah partner_links, bukan kolom di users.
        $link->forceFill(['status' => 'revoked'])->save();

        $this->actingAs($user)
            ->get(route('layanan.tagihan.index'))
            ->assertRedirect(route('beranda'));
    }

    public function test_id_mitra_diambil_dari_link_bukan_dari_request(): void
    {
        $user = $this->pengguna('mitra@uji.test');
        $this->mitra($user, 'customer', 77);

        Http::fake(['*' => Http::response(['data' => [], 'meta' => []], 200)]);

        // Query string yang mencoba menyuntikkan id lain harus diabaikan.
        $this->actingAs($user)
            ->get(route('layanan.tagihan.index').'?customer=999&portal_user_id=999&erp_partner_id=999')
            ->assertOk();

        Http::assertSent(function ($request) {
            // Id yang dipakai wajib 77 (dari partner_links), bukan 999.
            return str_contains($request->url(), '/customers/77/')
                && ! str_contains($request->url(), '/customers/999/');
        });
    }
}
