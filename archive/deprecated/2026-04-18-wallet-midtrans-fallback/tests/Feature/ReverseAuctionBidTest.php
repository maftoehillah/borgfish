<?php

namespace Tests\Feature;

use App\Models\Bid;
use App\Models\Ikan;
use App\Models\User;
use App\Services\LelangService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReverseAuctionBidTest extends TestCase
{
    use RefreshDatabase;

    public function test_reverse_auction_rejects_bid_that_is_not_lower_than_reference_price(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli');

        $ikan = $this->makeActiveReverseLot($seller, [
            'harga_awal' => 100_000,
            'harga_tertinggi' => 100_000,
            'minimal_increment' => 5_000,
        ]);

        $response = $this->actingAs($buyer)->post(route('bid.store', $ikan), [
            'jumlah_bid' => 100_000,
        ]);

        $response->assertSessionHasErrors('jumlah_bid');

        $this->assertDatabaseCount('bids', 0);
        $this->assertSame(100000.0, (float) $ikan->fresh()->harga_tertinggi);
    }

    public function test_reverse_auction_accepts_lower_integer_bid_below_reference_price(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli');

        $ikan = $this->makeActiveReverseLot($seller, [
            'harga_awal' => 100_000,
            'harga_tertinggi' => 100_000,
            'minimal_increment' => 5_000,
        ]);

        $response = $this->actingAs($buyer)->post(route('bid.store', $ikan), [
            'jumlah_bid' => 95_000,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('sukses');

        $this->assertDatabaseHas('bids', [
            'ikan_id' => $ikan->id,
            'user_id' => $buyer->id,
            'jumlah_bid' => 95000,
        ]);

        $ikan->refresh();
        $this->assertSame(95000.0, (float) $ikan->harga_tertinggi);
        $this->assertSame('aktif', $ikan->status);
    }

    public function test_reverse_auction_allows_non_monotonic_bids_below_reference_price(): void
    {
        $seller = $this->makeUser('penjual');
        $buyerA = $this->makeUser('pembeli');
        $buyerB = $this->makeUser('pembeli');
        $buyerC = $this->makeUser('pembeli');

        $ikan = $this->makeActiveReverseLot($seller, [
            'harga_awal' => 200_000,
            'harga_tertinggi' => 200_000,
            'minimal_increment' => 10_000,
        ]);

        $this->actingAs($buyerA)
            ->post(route('bid.store', $ikan), ['jumlah_bid' => 190_000])
            ->assertSessionHasNoErrors();

        $this->actingAs($buyerB)
            ->post(route('bid.store', $ikan), ['jumlah_bid' => 170_000])
            ->assertSessionHasNoErrors();

        $this->actingAs($buyerC)
            ->post(route('bid.store', $ikan), ['jumlah_bid' => 180_000])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('bids', [
            'ikan_id' => $ikan->id,
            'user_id' => $buyerA->id,
            'jumlah_bid' => 190000,
        ]);
        $this->assertDatabaseHas('bids', [
            'ikan_id' => $ikan->id,
            'user_id' => $buyerB->id,
            'jumlah_bid' => 170000,
        ]);
        $this->assertDatabaseHas('bids', [
            'ikan_id' => $ikan->id,
            'user_id' => $buyerC->id,
            'jumlah_bid' => 180000,
        ]);

        $ikan->refresh();
        $this->assertSame(190000.0, (float) $ikan->harga_tertinggi);
    }

    public function test_reverse_auction_rejects_bid_below_minimum_amount(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli');

        $ikan = $this->makeActiveReverseLot($seller, [
            'harga_awal' => 100_000,
            'harga_tertinggi' => 100_000,
            'minimal_increment' => 5_000,
        ]);

        $response = $this->actingAs($buyer)->post(route('bid.store', $ikan), [
            'jumlah_bid' => 999,
        ]);

        $response->assertSessionHasErrors('jumlah_bid');

        $this->assertDatabaseCount('bids', 0);
        $this->assertSame(100000.0, (float) $ikan->fresh()->harga_tertinggi);
    }

    public function test_reverse_auction_rejects_non_thousand_integer_bid(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli');

        $ikan = $this->makeActiveReverseLot($seller, [
            'harga_awal' => 100_000,
            'harga_tertinggi' => 100_000,
            'minimal_increment' => 5_000,
        ]);

        $response = $this->actingAs($buyer)->post(route('bid.store', $ikan), [
            'jumlah_bid' => 99_999,
        ]);

        $response->assertSessionHasErrors('jumlah_bid');

        $this->assertDatabaseCount('bids', 0);
        $this->assertSame(100000.0, (float) $ikan->fresh()->harga_tertinggi);
    }

    public function test_reverse_auction_rejects_decimal_bid_nominal(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli');

        $ikan = $this->makeActiveReverseLot($seller, [
            'harga_awal' => 100_000,
            'harga_tertinggi' => 100_000,
            'minimal_increment' => 5_000,
        ]);

        $response = $this->actingAs($buyer)->post(route('bid.store', $ikan), [
            'jumlah_bid' => 99_999.5,
        ]);

        $response->assertSessionHasErrors('jumlah_bid');

        $this->assertDatabaseCount('bids', 0);
        $this->assertSame(100000.0, (float) $ikan->fresh()->harga_tertinggi);
    }

    public function test_reverse_auction_closes_with_highest_valid_bid_as_winner(): void
    {
        $seller = $this->makeUser('penjual');
        $buyerA = $this->makeUser('pembeli');
        $buyerB = $this->makeUser('pembeli');

        $ikan = $this->makeActiveReverseLot($seller, [
            'harga_awal' => 100_000,
            'harga_tertinggi' => 85_000,
            'waktu_selesai' => now()->subMinute(),
        ]);

        Bid::create([
            'ikan_id' => $ikan->id,
            'user_id' => $buyerA->id,
            'jumlah_bid' => 90_000,
            'bidder_ip' => '127.0.0.1',
            'bidder_user_agent' => 'phpunit',
        ]);

        Bid::create([
            'ikan_id' => $ikan->id,
            'user_id' => $buyerB->id,
            'jumlah_bid' => 85_000,
            'bidder_ip' => '127.0.0.2',
            'bidder_user_agent' => 'phpunit',
        ]);

        app(LelangService::class)->tutupLelang($ikan);

        $ikan->refresh();

        $this->assertSame('terbayar', $ikan->status);

        $this->assertDatabaseHas('transaksis', [
            'ikan_id' => $ikan->id,
            'pemenang_id' => $buyerA->id,
            'harga_final' => 90000,
            'status' => 'lunas',
            'metode_pembayaran' => 'saldo',
        ]);
    }

    public function test_reverse_auction_allows_buy_now_at_reference_price(): void
    {
        $seller = $this->makeUser('penjual');

        $ikan = $this->makeActiveReverseLot($seller, [
            'harga_awal' => 120_000,
            'harga_tertinggi' => 110_000,
            'buy_now_enabled' => false,
            'buy_now_price' => null,
        ]);

        $ikan->refresh();

        $this->assertTrue($ikan->canBuyNow());
        $this->assertSame(120000.0, (float) $ikan->buyNowTarget());
    }

    public function test_reverse_auction_buy_now_creates_transaction_at_reference_price(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli');

        $ikan = $this->makeActiveReverseLot($seller, [
            'harga_awal' => 130_000,
            'harga_tertinggi' => 120_000,
        ]);

        $response = $this->actingAs($buyer)->post(route('ikans.buy_now', $ikan));

        $response->assertStatus(302);
        $response->assertSessionHas('sukses');

        $ikan->refresh();

        $this->assertSame('terbayar', $ikan->status);
        $this->assertSame(130000.0, (float) $ikan->harga_tertinggi);

        $this->assertDatabaseHas('bids', [
            'ikan_id' => $ikan->id,
            'user_id' => $buyer->id,
            'jumlah_bid' => 130000,
        ]);

        $this->assertDatabaseHas('transaksis', [
            'ikan_id' => $ikan->id,
            'pemenang_id' => $buyer->id,
            'harga_final' => 130000,
            'status' => 'lunas',
            'metode_pembayaran' => 'saldo',
        ]);
    }

    private function makeUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_admin' => false,
        ]);
    }

    private function makeActiveReverseLot(User $seller, array $overrides = []): Ikan
    {
        return Ikan::create(array_merge([
            'user_id' => $seller->id,
            'nama_ikan' => 'Nila Tambak',
            'berat' => 10,
            'estimasi_jumlah_ekor' => 20,
            'jenis_kemasan' => 'keranjang',
            'kondisi' => 'segar',
            'deskripsi' => 'Lot uji reverse auction',
            'tipe_lelang' => 'turun',
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
}
