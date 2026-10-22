<?php

namespace Tests\Feature;

use App\Models\PartnerLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tombol bayar di detail tagihan mengikuti info `payment` dari ERP:
 * tautan bayar online, dan unggah bukti transfer hanya bila ada rekening.
 */
class TagihanBayarOnlineTest extends TestCase
{
    use RefreshDatabase;

    private function pelanggan(): User
    {
        $user = User::create([
            'name' => 'Uji', 'email' => 'bayar@uji.test', 'password' => Hash::make('KataSandiUji#2026x'),
            'account_type' => 'pelanggan', 'status' => 'active', 'email_verified_at' => now(),
        ]);
        $link = PartnerLink::create([
            'user_id' => $user->id, 'partner_type' => 'customer', 'company_name' => 'PT Uji',
            'evidence_type' => 'invite_code', 'evidence_value' => 'UJI-7', 'status' => 'verified',
            'erp_partner_id' => 7, 'verified_at' => now(),
        ]);
        $user->forceFill(['active_link_id' => $link->id])->save();

        return $user->fresh();
    }

    private function tampil(array $payment)
    {
        Cache::flush();
        Http::fake(['*' => Http::response(['data' => [
            'id' => 3, 'invoice_number' => 'INV-3', 'invoice_date' => '2026-10-01', 'due_date' => '2026-10-20',
            'currency' => 'IDR', 'subtotal' => 1000, 'tax' => 0, 'grand_total' => 1000, 'paid_amount' => 0,
            'remaining_amount' => 1000, 'status' => 'sent', 'status_label' => 'Terkirim', 'status_color' => 'blue',
            'is_overdue' => false, 'items' => [], 'payment_confirmations' => [], 'payment' => $payment,
        ]], 200)]);

        return $this->actingAs($this->pelanggan())->get(route('layanan.tagihan.show', 3));
    }

    public function test_tombol_bayar_online_memakai_tautan_dari_erp(): void
    {
        $this->tampil(['online_url' => 'https://erp.example.test/bayar/abc', 'allow_transfer' => true])
            ->assertOk()
            ->assertSee('https://erp.example.test/bayar/abc', false)
            ->assertSee('Bayar Online')
            ->assertSee('Konfirmasi Pembayaran');
    }

    public function test_tanpa_rekening_unggah_bukti_transfer_disembunyikan(): void
    {
        $this->tampil(['online_url' => 'https://erp.example.test/bayar/abc', 'allow_transfer' => false])
            ->assertOk()
            ->assertSee('Bayar Online')
            ->assertDontSee('Konfirmasi Pembayaran');
    }

    public function test_tanpa_info_payment_perilaku_lama_dipertahankan(): void
    {
        $this->tampil([])->assertOk()->assertDontSee('Bayar Online')->assertSee('Konfirmasi Pembayaran');
    }
}
