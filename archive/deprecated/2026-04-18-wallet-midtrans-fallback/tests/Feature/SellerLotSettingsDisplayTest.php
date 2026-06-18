<?php

namespace Tests\Feature;

use App\Models\Ikan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerLotSettingsDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_detail_displays_reserve_price_and_auto_payment_policy_for_reverse_auction(): void
    {
        $seller = User::factory()->create([
            'role' => 'penjual',
            'is_admin' => false,
        ]);

        $ikan = Ikan::create([
            'user_id' => $seller->id,
            'nama_ikan' => 'Lot Setting Display',
            'berat' => 15,
            'estimasi_jumlah_ekor' => 30,
            'jenis_kemasan' => 'keranjang',
            'kondisi' => 'segar',
            'deskripsi' => 'Test detail setting reserve dan tenggat',
            'tipe_lelang' => 'turun',
            'harga_awal' => 150_000,
            'harga_tertinggi' => 150_000,
            'reserve_price' => 145_000,
            'minimal_increment' => 5_000,
            'buy_now_enabled' => false,
            'buy_now_price' => null,
            'payment_deadline_minutes' => 2160,
            'anti_sniping_enabled' => true,
            'anti_sniping_window_seconds' => 120,
            'anti_sniping_extend_seconds' => 120,
            'anti_sniping_max_extensions' => 3,
            'anti_sniping_extensions_used' => 0,
            'waktu_mulai' => now()->subMinutes(10),
            'waktu_selesai' => now()->addHours(2),
            'status' => 'aktif',
            'state_version' => 1,
        ]);

        $response = $this->actingAs($seller)->get(route('penjual.ikans.show', $ikan));

        $response->assertOk();
        $response->assertSee('Reserve Price');
        $response->assertSee('Rp 145.000');
        $response->assertSee('Kebijakan Pembayaran');
        $response->assertSee('Otomatis dari saldo buyer saat lelang selesai');
    }

    public function test_seller_detail_hides_reserve_price_for_ascending_auction(): void
    {
        $seller = User::factory()->create([
            'role' => 'penjual',
            'is_admin' => false,
        ]);

        $ikan = Ikan::create([
            'user_id' => $seller->id,
            'nama_ikan' => 'Lot Naik Tanpa Reserve Display',
            'berat' => 15,
            'estimasi_jumlah_ekor' => 30,
            'jenis_kemasan' => 'keranjang',
            'kondisi' => 'segar',
            'deskripsi' => 'Test detail setting lelang naik',
            'tipe_lelang' => 'naik',
            'harga_awal' => 150_000,
            'harga_tertinggi' => 150_000,
            'reserve_price' => 145_000,
            'minimal_increment' => 5_000,
            'buy_now_enabled' => false,
            'buy_now_price' => null,
            'payment_deadline_minutes' => 2160,
            'anti_sniping_enabled' => true,
            'anti_sniping_window_seconds' => 120,
            'anti_sniping_extend_seconds' => 120,
            'anti_sniping_max_extensions' => 3,
            'anti_sniping_extensions_used' => 0,
            'waktu_mulai' => now()->subMinutes(10),
            'waktu_selesai' => now()->addHours(2),
            'status' => 'aktif',
            'state_version' => 1,
        ]);

        $response = $this->actingAs($seller)->get(route('penjual.ikans.show', $ikan));

        $response->assertOk();
        $response->assertSee('Kebijakan Pembayaran');
        $response->assertSee('Otomatis dari saldo buyer saat lelang selesai');
        $response->assertDontSee('Reserve Price');
    }
}
