<?php

namespace Tests\Feature;

use App\Models\Bid;
use App\Models\Ikan;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultAuctionTypeFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_activity_defaults_to_all_auction_types(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli');

        $naikLot = $this->makeLot($seller, [
            'nama_ikan' => 'Naik Lot Uji',
            'tipe_lelang' => 'naik',
        ]);

        $turunLot = $this->makeLot($seller, [
            'nama_ikan' => 'Turun Lot Uji',
            'tipe_lelang' => 'turun',
        ]);

        $this->makeWinningPendingTransaction($naikLot, $buyer);
        $this->makeWinningPendingTransaction($turunLot, $buyer);

        $response = $this->actingAs($buyer)
            ->withSession(['otp_verified_user_id' => $buyer->id])
            ->get(route('pembeli.aktivitas'));

        $response->assertOk();
        $response->assertSee('Naik Lot Uji');
        $response->assertSee('Turun Lot Uji');
    }

    public function test_seller_dashboard_defaults_to_all_auction_types(): void
    {
        $seller = $this->makeUser('penjual');

        $this->makeLot($seller, [
            'nama_ikan' => 'Dashboard Naik Uji',
            'tipe_lelang' => 'naik',
        ]);

        $this->makeLot($seller, [
            'nama_ikan' => 'Dashboard Turun Uji',
            'tipe_lelang' => 'turun',
        ]);

        $response = $this->actingAs($seller)
            ->withSession(['otp_verified_user_id' => $seller->id])
            ->get(route('penjual.ikans.index'));

        $response->assertOk();
        $response->assertSee('Dashboard Naik Uji');
        $response->assertSee('Dashboard Turun Uji');
    }

    private function makeUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_admin' => false,
        ]);
    }

    private function makeLot(User $seller, array $overrides = []): Ikan
    {
        return Ikan::create(array_merge([
            'user_id' => $seller->id,
            'nama_ikan' => 'Lot Uji',
            'berat' => 10,
            'estimasi_jumlah_ekor' => 20,
            'jenis_kemasan' => 'keranjang',
            'kondisi' => 'segar',
            'deskripsi' => 'Lot untuk test default filter',
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
            'status' => 'aktif',
            'state_version' => 1,
        ], $overrides));
    }

    private function makeWinningPendingTransaction(Ikan $ikan, User $buyer): void
    {
        Bid::create([
            'ikan_id' => $ikan->id,
            'user_id' => $buyer->id,
            'jumlah_bid' => 95_000,
            'bidder_ip' => '127.0.0.1',
            'bidder_user_agent' => 'phpunit',
        ]);

        Transaksi::create([
            'ikan_id' => $ikan->id,
            'pemenang_id' => $buyer->id,
            'harga_final' => 95_000,
            'status' => 'menunggu_bayar',
            'bayar_sebelum' => now()->addHours(24),
            'pickup_status' => 'waiting_payment',
        ]);
    }
}
