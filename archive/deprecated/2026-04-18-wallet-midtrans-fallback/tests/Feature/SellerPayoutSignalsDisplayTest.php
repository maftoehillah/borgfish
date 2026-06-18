<?php

namespace Tests\Feature;

use App\Models\Ikan;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerPayoutSignalsDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_dashboard_shows_escrow_and_ready_to_disburse_buckets(): void
    {
        $seller = $this->makeSeller();
        $buyer = $this->makeBuyer();

        $escrowLot = $this->makeLot($seller, 'Lot Escrow Ditahan');
        $releasedLot = $this->makeLot($seller, 'Lot Siap Cair');

        $this->makeTransaction($escrowLot, $buyer, [
            'harga_final' => 150_000,
            'escrow_amount' => 150_000,
            'escrow_status' => 'ditahan',
            'delivery_status' => 'diproses',
        ]);

        $this->makeTransaction($releasedLot, $buyer, [
            'harga_final' => 210_000,
            'escrow_amount' => 210_000,
            'escrow_status' => 'dilepas',
            'delivery_status' => 'diterima',
            'escrow_released_at' => now()->subHour(),
            'released_by_buyer_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($seller)->get(route('penjual.ikans.index'));

        $response->assertOk();
        // Funds moved to dedicated Dana Penjual page; dashboard should not show fund widgets
        $response->assertDontSee('Dana Ditahan di Escrow');
        $response->assertDontSee('Saldo Seller Siap Ditarik');
        $response->assertDontSee('Pending Withdraw');
        // Header should still expose the Dana Penjual link
        $response->assertSee('Dana Penjual');
    }

    public function test_seller_detail_shows_seller_fund_position_for_released_escrow(): void
    {
        $seller = $this->makeSeller();
        $buyer = $this->makeBuyer();
        $ikan = $this->makeLot($seller, 'Lot Detail Dana Seller');

        $this->makeTransaction($ikan, $buyer, [
            'harga_final' => 175_000,
            'escrow_amount' => 175_000,
            'escrow_status' => 'dilepas',
            'escrow_released_at' => now()->subMinutes(30),
            'delivery_status' => 'diterima',
            'released_by_buyer_at' => now()->subMinutes(30),
        ]);

        $response = $this->actingAs($seller)->get(route('penjual.ikans.show', $ikan));

        $response->assertOk();
        $response->assertSee('Posisi Dana Seller');
        $response->assertSee('Sudah Masuk Saldo Penjual');
        $response->assertSee('Escrow Dilepas');
    }

    private function makeSeller(): User
    {
        return User::factory()->create([
            'role' => 'penjual',
            'is_admin' => false,
        ]);
    }

    private function makeBuyer(): User
    {
        return User::factory()->create([
            'role' => 'pembeli',
            'is_admin' => false,
        ]);
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
            'deskripsi' => 'Lot untuk pengujian dana seller',
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
            'waktu_mulai' => now()->subMinutes(10),
            'waktu_selesai' => now()->addMinutes(30),
            'status' => 'terbayar',
            'auction_state' => 'DIBAYAR',
            'state_version' => 1,
        ]);
    }

    private function makeTransaction(Ikan $ikan, User $buyer, array $overrides = []): Transaksi
    {
        return Transaksi::create(array_merge([
            'ikan_id' => $ikan->id,
            'pemenang_id' => $buyer->id,
            'harga_final' => 150_000,
            'status' => 'lunas',
            'bayar_sebelum' => now()->addHours(24),
            'dibayar_pada' => now()->subHours(2),
            'escrow_status' => 'ditahan',
            'escrow_amount' => 150_000,
            'escrow_locked_at' => now()->subHours(2),
            'delivery_status' => 'diproses',
            'delivery_cost' => 0,
            'fulfillment_state' => 'DIBAYAR',
        ], $overrides));
    }
}
