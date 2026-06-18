<?php

namespace Tests\Feature;

use App\Models\Ikan;
use App\Models\SellerSettlement;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerSettlementBackfillCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_command_creates_settlement_for_completed_transaction(): void
    {
        $seller = User::factory()->create(['role' => 'penjual']);
        $buyer = User::factory()->create(['role' => 'pembeli']);
        $ikan = $this->makeLot($seller);

        $transaksi = $this->makeCompletedTransaksi($ikan, $buyer);

        $this->artisan('settlements:backfill-completed --limit=10')
            ->expectsOutputToContain('dibuat: 1')
            ->assertSuccessful();

        $this->assertDatabaseHas('seller_settlements', [
            'transaksi_id' => $transaksi->id,
            'seller_id' => $seller->id,
            'status' => 'pending',
        ]);
    }

    public function test_backfill_command_skips_transaction_that_already_has_settlement(): void
    {
        $seller = User::factory()->create(['role' => 'penjual']);
        $buyer = User::factory()->create(['role' => 'pembeli']);
        $ikan = $this->makeLot($seller);

        $transaksi = $this->makeCompletedTransaksi($ikan, $buyer);

        SellerSettlement::create([
            'transaksi_id' => $transaksi->id,
            'seller_id' => $seller->id,
            'amount' => 160000,
            'status' => 'pending',
            'bank_name' => 'BCA',
            'bank_account_number' => '1234567890',
            'bank_account_name' => $seller->name,
        ]);

        $this->artisan('settlements:backfill-completed --limit=10')
            ->expectsOutputToContain('dibuat: 0')
            ->assertSuccessful();

        $this->assertSame(
            1,
            SellerSettlement::query()->where('transaksi_id', $transaksi->id)->count()
        );
    }

    private function makeLot(User $seller): Ikan
    {
        return Ikan::create([
            'user_id' => $seller->id,
            'nama_ikan' => 'Cakalang Uji Backfill',
            'berat' => 15,
            'estimasi_jumlah_ekor' => 25,
            'jenis_kemasan' => 'keranjang',
            'kondisi' => 'segar',
            'deskripsi' => 'Lot test settlement backfill',
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
            'waktu_mulai' => now()->subMinutes(20),
            'waktu_selesai' => now()->subMinutes(5),
            'status' => 'terbayar',
            'state_version' => 1,
        ]);
    }

    private function makeCompletedTransaksi(Ikan $ikan, User $buyer): Transaksi
    {
        return Transaksi::create([
            'ikan_id' => $ikan->id,
            'pemenang_id' => $buyer->id,
            'harga_final' => 160_000,
            'status' => 'lunas',
            'payment_status' => 'paid',
            'fulfillment_state' => 'SELESAI',
            'state_version' => 1,
            'pickup_status' => 'completed',
            'dibayar_pada' => now()->subHours(4),
            'paid_at' => now()->subHours(4),
            'completed_by_buyer_at' => now()->subHour(),
            'completed_at' => now()->subHour(),
        ]);
    }
}
