<?php

namespace Tests\Feature;

use App\Models\Bid;
use App\Models\Ikan;
use App\Models\SellerSettlement;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompletedHistorySeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_purchase_moves_from_buyer_activity_to_purchase_history(): void
    {
        $seller = User::factory()->create([
            'role' => 'penjual',
            'is_admin' => false,
        ]);
        $buyer = User::factory()->create([
            'role' => 'pembeli',
            'is_admin' => false,
        ]);

        $lot = $this->makeLot($seller, 'Lot Pembelian Selesai');
        $this->makeBid($lot, $buyer, 180_000);
        $this->makeCompletedTransaction($lot, $buyer);

        $activityResponse = $this->actingAs($buyer)
            ->withSession(['otp_verified_user_id' => $buyer->id])
            ->get(route('pembeli.aktivitas'));

        $activityResponse->assertOk();
        $activityResponse->assertDontSee('Lot Pembelian Selesai');

        $historyResponse = $this->actingAs($buyer)
            ->withSession(['otp_verified_user_id' => $buyer->id])
            ->get(route('pembeli.riwayat'));

        $historyResponse->assertOk();
        $historyResponse->assertSee('Riwayat Pembelian');
        $historyResponse->assertSee('Lot Pembelian Selesai');
        $historyResponse->assertSee('4 bintang');
        $historyResponse->assertSee('Bukti Packing');
        $historyResponse->assertSee('Foto Kendaraan');
    }

    public function test_completed_sale_moves_from_seller_lot_activity_to_seller_dashboard(): void
    {
        $seller = User::factory()->create([
            'role' => 'penjual',
            'is_admin' => false,
        ]);
        $buyer = User::factory()->create([
            'role' => 'pembeli',
            'is_admin' => false,
        ]);

        $lot = $this->makeLot($seller, 'Lot Penjualan Selesai');
        $this->makeBid($lot, $buyer, 190_000);
        $transaksi = $this->makeCompletedTransaction($lot, $buyer);
        $this->makeSettlement($seller, $transaksi);

        $lotActivityResponse = $this->actingAs($seller)
            ->withSession(['otp_verified_user_id' => $seller->id])
            ->get(route('penjual.ikans.index'));

        $lotActivityResponse->assertOk();
        $lotActivityResponse->assertSee('Aktivitas Lot');
        $lotActivityResponse->assertDontSee('Lot Penjualan Selesai');

        $dashboardResponse = $this->actingAs($seller)
            ->withSession(['otp_verified_user_id' => $seller->id])
            ->get(route('penjual.dashboard'));

        $dashboardResponse->assertOk();
        $dashboardResponse->assertSee('Dashboard Penjual');
        $dashboardResponse->assertSee($seller->sellerProfile->store_name);
        $dashboardResponse->assertSee('Lot Penjualan Selesai');
        $dashboardResponse->assertSee('History Penjualan Selesai');
        $dashboardResponse->assertSee('Settlement Dana');
        $dashboardResponse->assertSee('Ditahan');
        $dashboardResponse->assertSee('Nominal settlement di bawah minimum payout.');
        $dashboardResponse->assertSee('Foto Sopir');
    }

    private function makeLot(User $seller, string $name): Ikan
    {
        return Ikan::create([
            'user_id' => $seller->id,
            'nama_ikan' => $name,
            'berat' => 10,
            'estimasi_jumlah_ekor' => 20,
            'jenis_kemasan' => 'keranjang',
            'kondisi' => 'segar',
            'deskripsi' => 'Lot untuk test riwayat selesai',
            'tipe_lelang' => 'naik',
            'harga_awal' => 100_000,
            'harga_tertinggi' => 100_000,
            'minimal_increment' => 5_000,
            'buy_now_enabled' => false,
            'buy_now_price' => null,
            'anti_sniping_enabled' => true,
            'anti_sniping_window_seconds' => 120,
            'anti_sniping_extend_seconds' => 120,
            'anti_sniping_max_extensions' => 3,
            'anti_sniping_extensions_used' => 0,
            'waktu_mulai' => now()->subHours(4),
            'waktu_selesai' => now()->subHours(2),
            'status' => 'terbayar',
            'state_version' => 1,
        ]);
    }

    private function makeBid(Ikan $ikan, User $buyer, int $amount): void
    {
        Bid::create([
            'ikan_id' => $ikan->id,
            'user_id' => $buyer->id,
            'jumlah_bid' => $amount,
            'bidder_ip' => '127.0.0.1',
            'bidder_user_agent' => 'phpunit',
        ]);
    }

    private function makeCompletedTransaction(Ikan $ikan, User $buyer): Transaksi
    {
        return Transaksi::create([
            'ikan_id' => $ikan->id,
            'pemenang_id' => $buyer->id,
            'harga_final' => 180_000,
            'status' => 'lunas',
            'payment_status' => 'paid',
            'fulfillment_state' => 'SELESAI',
            'pickup_status' => 'completed',
            'dibayar_pada' => now()->subHours(3),
            'paid_at' => now()->subHours(3),
            'packed_at' => now()->subHours(2),
            'packing_proof' => 'delivery-proof/packing.jpg',
            'buyer_pickup_name' => 'Pak Rudi',
            'buyer_pickup_plate_number' => 'B 1234 TEST',
            'buyer_pickup_photo' => 'pickup-proof/buyer.jpg',
            'buyer_pickup_vehicle_photo' => 'pickup-proof/buyer-vehicle.jpg',
            'buyer_pickup_submitted_at' => now()->subHours(2),
            'seller_pickup_driver_name' => 'Pak Rudi',
            'seller_pickup_driver_photo' => 'pickup-proof/driver.jpg',
            'seller_pickup_vehicle_photo' => 'pickup-proof/vehicle.jpg',
            'seller_pickup_plate_number' => 'B 1234 TEST',
            'seller_pickup_recorded_at' => now()->subHour(),
            'pickup_verified_at' => now()->subHour(),
            'buyer_rating' => 4,
            'buyer_review' => 'Barang sesuai.',
            'buyer_reviewed_at' => now()->subMinutes(30),
            'completed_by_buyer_at' => now()->subMinutes(30),
        ]);
    }

    private function makeSettlement(User $seller, Transaksi $transaksi): SellerSettlement
    {
        return SellerSettlement::create([
            'transaksi_id' => $transaksi->id,
            'seller_id' => $seller->id,
            'amount' => 180_000,
            'status' => 'held',
            'bank_name' => 'BCA',
            'bank_account_number' => '1234567890',
            'bank_account_name' => $seller->name,
            'hold_reason' => 'Nominal settlement di bawah minimum payout.',
            'held_at' => now()->subMinutes(10),
        ]);
    }
}
