<?php

namespace Tests\Feature;

use App\Models\Ikan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerDashboardSettingsDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_dashboard_shows_reserve_and_auto_payment_copy_for_reverse_auction_lot(): void
    {
        $seller = User::factory()->create([
            'role' => 'penjual',
            'is_admin' => false,
        ]);

        Ikan::create([
            'user_id' => $seller->id,
            'nama_ikan' => 'Lot Dashboard Turun Reserve',
            'berat' => 11,
            'estimasi_jumlah_ekor' => 22,
            'jenis_kemasan' => 'keranjang',
            'kondisi' => 'segar',
            'deskripsi' => 'Test tampilan setting di dashboard',
            'tipe_lelang' => 'turun',
            'harga_awal' => 100_000,
            'harga_tertinggi' => 120_000,
            'reserve_price' => 115_000,
            'minimal_increment' => 5_000,
            'buy_now_enabled' => false,
            'buy_now_price' => null,
            'payment_deadline_minutes' => 1080,
            'anti_sniping_enabled' => true,
            'anti_sniping_window_seconds' => 120,
            'anti_sniping_extend_seconds' => 120,
            'anti_sniping_max_extensions' => 3,
            'anti_sniping_extensions_used' => 0,
            'waktu_mulai' => now()->subMinutes(5),
            'waktu_selesai' => now()->addHours(2),
            'status' => 'aktif',
            'state_version' => 1,
        ]);

        $response = $this->actingAs($seller)->get(route('penjual.ikans.index'));

        $response->assertOk();
        $response->assertSee('Lot Dashboard Turun Reserve');
        $response->assertSee('Reserve: Rp 115.000');
        $response->assertSee('Pembayaran buyer: otomatis dari saldo saat lot selesai');
        $response->assertSee('Reserve Aktif');
    }

    public function test_seller_dashboard_hides_reserve_information_for_ascending_auction_lot(): void
    {
        $seller = User::factory()->create([
            'role' => 'penjual',
            'is_admin' => false,
        ]);

        Ikan::create([
            'user_id' => $seller->id,
            'nama_ikan' => 'Lot Dashboard Naik No Reserve',
            'berat' => 9,
            'estimasi_jumlah_ekor' => 18,
            'jenis_kemasan' => 'keranjang',
            'kondisi' => 'segar',
            'deskripsi' => 'Test lelang naik tanpa tampilan reserve',
            'tipe_lelang' => 'naik',
            'harga_awal' => 90_000,
            'harga_tertinggi' => 95_000,
            'reserve_price' => 92_000,
            'minimal_increment' => 5_000,
            'buy_now_enabled' => false,
            'buy_now_price' => null,
            'payment_deadline_minutes' => 1440,
            'anti_sniping_enabled' => true,
            'anti_sniping_window_seconds' => 120,
            'anti_sniping_extend_seconds' => 120,
            'anti_sniping_max_extensions' => 3,
            'anti_sniping_extensions_used' => 0,
            'waktu_mulai' => now()->subMinutes(5),
            'waktu_selesai' => now()->addHours(3),
            'status' => 'aktif',
            'state_version' => 1,
        ]);

        $response = $this->actingAs($seller)->get(route('penjual.ikans.index'));

        $response->assertOk();
        $response->assertSee('Lot Dashboard Naik No Reserve');
        $response->assertSee('Pembayaran buyer: otomatis dari saldo saat lot selesai');
        $response->assertDontSee('Reserve:');
        $response->assertDontSee('Reserve Aktif');
        $response->assertDontSee('Tanpa Reserve');
    }
}
