<?php

namespace Tests\Feature;

use App\Models\Ikan;
use App\Models\User;
use App\Services\LelangService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BidSaldoHoldTest extends TestCase
{
    use RefreshDatabase;

    public function test_bid_is_rejected_when_available_balance_is_insufficient(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli', ['saldo' => 100_000]);
        $ikan = $this->makeActiveLot($seller);

        $response = $this->actingAs($buyer)->post(route('bid.store', $ikan), [
            'jumlah_bid' => 120_000,
        ]);

        $response->assertSessionHasErrors('jumlah_bid');
        $this->assertDatabaseCount('bids', 0);
        $this->assertSame(100000.0, $buyer->fresh()->saldoTersedia());
        $this->assertSame(0.0, $buyer->fresh()->saldoDitahan());
    }

    public function test_active_hold_moves_to_latest_leader_and_releases_previous_one(): void
    {
        $seller = $this->makeUser('penjual');
        $buyerA = $this->makeUser('pembeli', ['saldo' => 200_000]);
        $buyerB = $this->makeUser('pembeli', ['saldo' => 200_000]);
        $ikan = $this->makeActiveLot($seller);

        $this->actingAs($buyerA)->post(route('bid.store', $ikan), [
            'jumlah_bid' => 120_000,
        ])->assertSessionHasNoErrors();

        $buyerA->refresh();
        $this->assertSame(80000.0, $buyerA->saldoTersedia());
        $this->assertSame(120000.0, $buyerA->saldoDitahan());
        $this->assertDatabaseHas('auction_bid_holds', [
            'ikan_id' => $ikan->id,
            'user_id' => $buyerA->id,
            'amount' => 120000,
            'status' => 'active',
        ]);

        $this->actingAs($buyerB)->post(route('bid.store', $ikan), [
            'jumlah_bid' => 130_000,
        ])->assertSessionHasNoErrors();

        $buyerA->refresh();
        $buyerB->refresh();

        $this->assertSame(200000.0, $buyerA->saldoTersedia());
        $this->assertSame(0.0, $buyerA->saldoDitahan());
        $this->assertSame(70000.0, $buyerB->saldoTersedia());
        $this->assertSame(130000.0, $buyerB->saldoDitahan());

        $this->assertDatabaseHas('auction_bid_holds', [
            'ikan_id' => $ikan->id,
            'user_id' => $buyerA->id,
            'status' => 'released',
        ]);

        $this->assertDatabaseHas('auction_bid_holds', [
            'ikan_id' => $ikan->id,
            'user_id' => $buyerB->id,
            'amount' => 130000,
            'status' => 'active',
        ]);
    }

    public function test_auction_close_captures_held_balance_into_paid_transaction(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli', ['saldo' => 250_000]);
        $ikan = $this->makeActiveLot($seller);

        $this->actingAs($buyer)->post(route('bid.store', $ikan), [
            'jumlah_bid' => 150_000,
        ])->assertSessionHasNoErrors();

        $ikan->update([
            'waktu_selesai' => now()->subSecond(),
        ]);

        app(LelangService::class)->tutupLelang($ikan);

        $buyer->refresh();
        $ikan->refresh();

        $this->assertSame(100000.0, $buyer->saldoTersedia());
        $this->assertSame(0.0, $buyer->saldoDitahan());
        $this->assertSame('terbayar', $ikan->status);

        $this->assertDatabaseHas('transaksis', [
            'ikan_id' => $ikan->id,
            'pemenang_id' => $buyer->id,
            'harga_final' => 150000,
            'status' => 'lunas',
            'metode_pembayaran' => 'saldo',
        ]);

        $this->assertDatabaseHas('auction_bid_holds', [
            'ikan_id' => $ikan->id,
            'user_id' => $buyer->id,
            'amount' => 150000,
            'status' => 'captured',
        ]);
    }

    private function makeUser(string $role, array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'is_admin' => false,
        ], $overrides));
    }

    private function makeActiveLot(User $seller): Ikan
    {
        return Ikan::create([
            'user_id' => $seller->id,
            'nama_ikan' => 'Lot Saldo Hold',
            'berat' => 12,
            'estimasi_jumlah_ekor' => 20,
            'jenis_kemasan' => 'keranjang',
            'kondisi' => 'segar',
            'deskripsi' => 'Lot untuk test saldo hold',
            'tipe_lelang' => 'naik',
            'harga_awal' => 100_000,
            'harga_tertinggi' => 100_000,
            'minimal_increment' => 5_000,
            'buy_now_enabled' => false,
            'buy_now_price' => null,
            'anti_sniping_enabled' => false,
            'anti_sniping_window_seconds' => 120,
            'anti_sniping_extend_seconds' => 120,
            'anti_sniping_max_extensions' => 3,
            'anti_sniping_extensions_used' => 0,
            'waktu_mulai' => now()->subMinutes(5),
            'waktu_selesai' => now()->addMinutes(30),
            'status' => 'aktif',
            'auction_state' => 'AKTIF',
            'state_version' => 1,
        ]);
    }
}
