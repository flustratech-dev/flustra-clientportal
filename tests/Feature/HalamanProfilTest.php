<?php

namespace Tests\Feature;

use App\Models\PartnerLink;
use App\Models\Submission;
use App\Models\SubmissionAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Halaman profil: dua janji yang dulu tidak ditepati.
 *
 *  - Tombol pengajuan perubahan data pernah mati berlabel "segera hadir",
 *    padahal layanannya lengkap sampai ke layar persetujuan staf di ERP. Mitra
 *    yang mencarinya di sini menyimpulkan fiturnya belum ada.
 *  - Tab "Dokumen Saya" pernah jadi kartu kosong permanen yang menjanjikan
 *    invoice, surat jalan, dan kontrak akan "terkumpul di sini". Tidak ada kode
 *    yang mengisinya, jadi kalimat itu selamanya bohong.
 */
class HalamanProfilTest extends TestCase
{
    use RefreshDatabase;

    protected function pengguna(string $email = 'mitra@uji.test'): User
    {
        return User::create([
            'name'              => 'Mitra Uji',
            'email'             => $email,
            'password'          => Hash::make('KataSandiUji#2026x'),
            'account_type'      => 'umum',
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);
    }

    protected function mitra(User $user, string $tipe = 'customer'): PartnerLink
    {
        $link = PartnerLink::create([
            'user_id'        => $user->id,
            'partner_type'   => $tipe,
            'company_name'   => 'PT Mitra Uji',
            'evidence_type'  => 'invite_code',
            'evidence_value' => 'UJI-1',
            'status'         => 'verified',
            'erp_partner_id' => 1,
            'verified_at'    => now(),
        ]);

        $user->forceFill([
            'account_type'   => $tipe === 'customer' ? 'pelanggan' : 'vendor',
            'active_link_id' => $link->id,
        ])->save();

        return $link;
    }

    public function test_tombol_ajukan_perubahan_menuju_layanannya_bukan_mati(): void
    {
        $user = $this->pengguna();
        $this->mitra($user);

        $this->actingAs($user)
            ->get(route('profil.edit'))
            ->assertStatus(200)
            ->assertSee(route('layanan.data.edit'))
            ->assertDontSee('segera hadir');
    }

    public function test_peran_yang_tidak_sedang_dipakai_diarahkan_untuk_berganti_dulu(): void
    {
        $user = $this->pengguna();
        $this->mitra($user, 'customer');

        // Peran kedua yang terverifikasi tapi tidak sedang aktif. Halaman
        // tujuannya dijaga middleware yang membaca peran aktif, jadi tautan
        // untuk peran ini akan berakhir di penolakan.
        PartnerLink::create([
            'user_id'        => $user->id,
            'partner_type'   => 'vendor',
            'company_name'   => 'PT Mitra Uji',
            'evidence_type'  => 'invite_code',
            'evidence_value' => 'UJI-2',
            'status'         => 'verified',
            'erp_partner_id' => 2,
            'verified_at'    => now(),
        ]);

        $this->actingAs($user)
            ->get(route('profil.edit'))
            ->assertStatus(200)
            ->assertSee('Pakai peran ini')
            ->assertDontSee(route('vendor.data.edit'));
    }

    public function test_tab_dokumen_menampilkan_berkas_yang_pernah_dikirim(): void
    {
        $user = $this->pengguna();

        $pengajuan = Submission::create([
            'user_id'          => $user->id,
            'type'             => 'payment_confirmation',
            'reference_number' => 'BYR-202608-0001',
            'title'            => 'Konfirmasi pembayaran',
            'status'           => 'submitted',
            'submitted_at'     => now(),
            'sync_state'       => 'synced',
        ]);

        SubmissionAttachment::create([
            'submission_id' => $pengajuan->id,
            'disk'          => 'private',
            'path'          => 'portal/bukti/uji.jpg',
            'original_name' => 'bukti-transfer.jpg',
            'mime'          => 'image/jpeg',
            'size'          => 204800,
        ]);

        $this->actingAs($user)
            ->get(route('profil.edit'))
            ->assertStatus(200)
            ->assertSee('bukti-transfer.jpg')
            ->assertSee('BYR-202608-0001');
    }

    public function test_tab_dokumen_tidak_menjanjikan_dokumen_yang_tidak_pernah_datang(): void
    {
        $user = $this->pengguna();

        $this->actingAs($user)
            ->get(route('profil.edit'))
            ->assertStatus(200)
            ->assertSee('Belum ada berkas terkirim')
            // Kalimat lama menjanjikan dokumen terbitan kantor akan terkumpul
            // di sini. Ketiganya milik ERP dan punya kartu layanannya sendiri.
            ->assertDontSee('Belum ada dokumen');
    }

    public function test_berkas_mitra_lain_tidak_ikut_terdaftar(): void
    {
        $saya       = $this->pengguna('saya@uji.test');
        $orangLain  = $this->pengguna('lain@uji.test');

        $pengajuanOrangLain = Submission::create([
            'user_id'          => $orangLain->id,
            'type'             => 'payment_confirmation',
            'reference_number' => 'BYR-202608-9999',
            'title'            => 'Konfirmasi pembayaran',
            'status'           => 'submitted',
            'submitted_at'     => now(),
            'sync_state'       => 'synced',
        ]);

        SubmissionAttachment::create([
            'submission_id' => $pengajuanOrangLain->id,
            'disk'          => 'private',
            'path'          => 'portal/bukti/rahasia.jpg',
            'original_name' => 'bukti-orang-lain.jpg',
            'mime'          => 'image/jpeg',
            'size'          => 1024,
        ]);

        $this->actingAs($saya)
            ->get(route('profil.edit'))
            ->assertStatus(200)
            ->assertDontSee('bukti-orang-lain.jpg')
            ->assertDontSee('BYR-202608-9999');
    }
}
