<?php

namespace Tests\Feature;

use App\Models\Bid;
use App\Models\Ikan;
use App\Models\Transaksi;
use App\Models\User;
use App\Services\LelangService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservePriceAndPaymentDeadlineTest extends TestCase
{
    use RefreshDatabase;

    public function test_lot_does_not_create_transaction_when_best_bid_is_below_reserve_price(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli');

        $ikan = $this->makeEndedLot($seller, [
            'tipe_lelang' => 'turun',
            'harga_awal' => 150_000,
            'harga_tertinggi' => 110_000,
            'reserve_price' => 120_000,
            'payment_deadline_minutes' => 1440,
        ]);

        Bid::create([
            'ikan_id' => $ikan->id,
            'user_id' => $buyer->id,
            'jumlah_bid' => 110_000,
            'bidder_ip' => '127.0.0.2',
            'bidder_user_agent' => 'phpunit',
        ]);

        app(LelangService::class)->tutupLelang($ikan);

        $ikan->refresh();

        $this->assertSame('selesai', $ikan->status);
        $this->assertDatabaseMissing('transaksis', [
            'ikan_id' => $ikan->id,
        ]);
    }

    public function test_lot_with_valid_winner_is_paid_automatically_from_balance(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli');

        $ikan = $this->makeEndedLot($seller, [
            'tipe_lelang' => 'turun',
            'harga_awal' => 150_000,
            'harga_tertinggi' => 125_000,
            'reserve_price' => 120_000,
            'payment_deadline_minutes' => 360,
        ]);

        Bid::create([
            'ikan_id' => $ikan->id,
            'user_id' => $buyer->id,
            'jumlah_bid' => 125_000,
            'bidder_ip' => '127.0.0.3',
            'bidder_user_agent' => 'phpunit',
        ]);

        app(LelangService::class)->tutupLelang($ikan);

        $transaksi = Transaksi::query()->where('ikan_id', $ikan->id)->first();

        $this->assertNotNull($transaksi);
        $this->assertSame('lunas', $transaksi->status);
        $this->assertNull($transaksi->bayar_sebelum);
        $this->assertSame('saldo', $transaksi->metode_pembayaran);
    }

    private function makeUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_admin' => false,
        ]);
    }

    private function makeEndedLot(User $seller, array $overrides = []): Ikan
    {
        return Ikan::create(array_merge([
            'user_id' => $seller->id,
            'nama_ikan' => 'Lot Uji Reserve',
            'berat' => 10,
            'estimasi_jumlah_ekor' => 20,
            'jenis_kemasan' => 'keranjang',
            'kondisi' => 'segar',
            'deskripsi' => 'Lot untuk test reserve & deadline',
            'tipe_lelang' => 'naik',
            'harga_awal' => 150_000,
            'harga_tertinggi' => 125_000,
            'reserve_price' => null,
            'minimal_increment' => 5_000,
            'buy_now_enabled' => false,
            'buy_now_price' => null,
            'payment_deadline_minutes' => 1440,
            'anti_sniping_enabled' => true,
            'anti_sniping_window_seconds' => 120,
            'anti_sniping_extend_seconds' => 120,
            'anti_sniping_max_extensions' => 3,
            'anti_sniping_extensions_used' => 0,
            'waktu_mulai' => now()->subHours(3),
            'waktu_selesai' => now()->subMinute(),
            'status' => 'aktif',
            'state_version' => 1,
        ], $overrides));
    }
}
