<?php

namespace Tests\Feature;

use App\Models\Bid;
use App\Models\Ikan;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSellerDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_sees_clickable_seller_store_chip_on_lot_detail(): void
    {
        $seller = User::factory()->create([
            'role' => 'penjual',
            'is_admin' => false,
        ]);
        $buyer = User::factory()->create([
            'role' => 'pembeli',
            'is_admin' => false,
        ]);
        $lot = $this->makeLot($seller, 'Lot Detail Dengan Toko');

        $response = $this->actingAs($buyer)
            ->withSession(['otp_verified_user_id' => $buyer->id])
            ->get(route('ikans.show', $lot));

        $response->assertOk();
        $response->assertSee('Toko Penjual');
        $response->assertSee($seller->sellerProfile->store_name);
        $response->assertSee(route('seller.public', $seller), false);
        $response->assertSee('seller-profiles/testing-store.jpg');
    }

    public function test_public_seller_dashboard_shows_store_identity_and_lots_without_internal_actions(): void
    {
        $seller = User::factory()->create([
            'role' => 'penjual',
            'is_admin' => false,
        ]);
        $buyer = User::factory()->create([
            'role' => 'pembeli',
            'is_admin' => false,
        ]);

        $activeLot = $this->makeLot($seller, 'Lot Aktif Etalase Toko');
        $completedLot = $this->makeLot($seller, 'Lot Selesai Etalase Toko', [
            'status' => 'terbayar',
            'waktu_mulai' => now()->subHours(4),
            'waktu_selesai' => now()->subHours(2),
        ]);
        Bid::create([
            'ikan_id' => $completedLot->id,
            'user_id' => $buyer->id,
            'jumlah_bid' => 185_000,
            'bidder_ip' => '127.0.0.1',
            'bidder_user_agent' => 'phpunit',
        ]);
        $this->makeCompletedTransaction($completedLot, $buyer);

        $response = $this->actingAs($buyer)
            ->withSession(['otp_verified_user_id' => $buyer->id])
            ->get(route('seller.public', $seller));

        $response->assertOk();
        $response->assertSee('Dashboard Toko Penjual');
        $response->assertSee('Identitas Toko');
        $response->assertSee('Isi Toko');
        $response->assertSee('Rating');
        $response->assertSee('5.0 / 5');
        $response->assertSee($seller->sellerProfile->store_name);
        $response->assertSee($seller->sellerProfile->full_address);
        $response->assertSee('Lot Aktif Etalase Toko');
        $response->assertSee('Lot Selesai Etalase Toko');
        $response->assertDontSee('Aktivitas Lot');
        $response->assertDontSee('Upload Ikan');
        $response->assertDontSee(route('penjual.ikans.index'), false);
        $response->assertDontSee(route('penjual.ikans.create'), false);
    }

    private function makeLot(User $seller, string $name, array $overrides = []): Ikan
    {
        return Ikan::create([
            'user_id' => $seller->id,
            'nama_ikan' => $name,
            'berat' => 10,
            'estimasi_jumlah_ekor' => 20,
            'jenis_kemasan' => 'keranjang',
            'kondisi' => 'segar',
            'deskripsi' => 'Lot untuk test etalase toko publik',
            'tipe_lelang' => 'naik',
            'harga_awal' => 100_000,
            'harga_tertinggi' => 150_000,
            'minimal_increment' => 5_000,
            'buy_now_enabled' => false,
            'buy_now_price' => null,
            'anti_sniping_enabled' => true,
            'anti_sniping_window_seconds' => 120,
            'anti_sniping_extend_seconds' => 120,
            'anti_sniping_max_extensions' => 3,
            'anti_sniping_extensions_used' => 0,
            'waktu_mulai' => now()->subHour(),
            'waktu_selesai' => now()->addHours(2),
            'status' => 'aktif',
            'state_version' => 1,
            'foto' => 'fish-lots/testing-fish.jpg',
            ...$overrides,
        ]);
    }

    private function makeCompletedTransaction(Ikan $ikan, User $buyer): Transaksi
    {
        return Transaksi::create([
            'ikan_id' => $ikan->id,
            'pemenang_id' => $buyer->id,
            'harga_final' => 185_000,
            'status' => 'lunas',
            'payment_status' => 'paid',
            'buyer_rating' => 5,
            'buyer_review' => 'Ikan sesuai dan proses cepat.',
            'buyer_reviewed_at' => now()->subMinutes(25),
            'fulfillment_state' => 'SELESAI',
            'pickup_status' => 'completed',
            'dibayar_pada' => now()->subHours(3),
            'paid_at' => now()->subHours(3),
            'packed_at' => now()->subHours(2),
            'buyer_pickup_submitted_at' => now()->subHours(2),
            'pickup_verified_at' => now()->subHour(),
            'completed_by_buyer_at' => now()->subMinutes(30),
        ]);
    }
}
