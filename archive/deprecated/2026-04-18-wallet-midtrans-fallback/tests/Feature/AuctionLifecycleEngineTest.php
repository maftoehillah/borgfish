<?php

namespace Tests\Feature;

use App\Models\Bid;
use App\Models\Ikan;
use App\Models\NotificationOutbox;
use App\Models\Transaksi;
use App\Models\User;
use App\Services\LelangService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AuctionLifecycleEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_ranking_freeze_and_state_log_are_created_when_auction_closes(): void
    {
        $seller = $this->makeUser('penjual');
        $buyerA = $this->makeUser('pembeli');
        $buyerB = $this->makeUser('pembeli');

        $ikan = $this->makeEndedLot($seller, [
            'reserve_price' => 110_000,
            'payment_deadline_minutes' => 720,
        ]);

        Bid::create([
            'ikan_id' => $ikan->id,
            'user_id' => $buyerA->id,
            'jumlah_bid' => 130_000,
            'bidder_ip' => '127.0.0.2',
            'bidder_user_agent' => 'phpunit',
            'created_at' => now()->subSeconds(10),
            'updated_at' => now()->subSeconds(10),
        ]);

        Bid::create([
            'ikan_id' => $ikan->id,
            'user_id' => $buyerB->id,
            'jumlah_bid' => 120_000,
            'bidder_ip' => '127.0.0.3',
            'bidder_user_agent' => 'phpunit',
            'created_at' => now()->subSeconds(5),
            'updated_at' => now()->subSeconds(5),
        ]);

        app(LelangService::class)->tutupLelang($ikan);

        $ikan->refresh();

        $this->assertSame('DIBAYAR', $ikan->auction_state);
        $this->assertSame(1, (int) $ikan->current_winner_rank);

        $this->assertDatabaseHas('auction_rankings', [
            'ikan_id' => $ikan->id,
            'rank' => 1,
            'bidder_id' => $buyerA->id,
        ]);

        $this->assertDatabaseHas('auction_rankings', [
            'ikan_id' => $ikan->id,
            'rank' => 2,
            'bidder_id' => $buyerB->id,
        ]);

        $this->assertDatabaseHas('auction_state_logs', [
            'ikan_id' => $ikan->id,
            'event_name' => 'auction_closed',
            'to_state' => 'SELESAI',
        ]);

        $this->assertDatabaseHas('auction_state_logs', [
            'ikan_id' => $ikan->id,
            'event_name' => 'winner_selected_after_close',
            'to_state' => 'DIBAYAR',
        ]);

        $this->assertDatabaseHas('transaksis', [
            'ikan_id' => $ikan->id,
            'pemenang_id' => $buyerA->id,
            'status' => 'lunas',
            'metode_pembayaran' => 'saldo',
        ]);

        $this->assertDatabaseHas('notification_outbox', [
            'recipient_user_id' => $buyerA->id,
            'category' => 'lelang',
            'title' => 'Selamat, Anda menang lelang',
            'status' => 'sent',
        ]);

        $this->assertDatabaseHas('notification_outbox', [
            'recipient_user_id' => $buyerB->id,
            'category' => 'lelang',
            'title' => 'Lelang selesai, Anda belum menang',
            'status' => 'sent',
        ]);

        $this->assertDatabaseHas('notification_outbox', [
            'recipient_user_id' => $seller->id,
            'category' => 'lelang',
            'title' => 'Lelang selesai, pemenang ditemukan',
            'status' => 'sent',
        ]);
    }

    public function test_auction_close_without_winner_notifies_seller_and_participants(): void
    {
        $seller = $this->makeUser('penjual');
        $buyerA = $this->makeUser('pembeli');
        $buyerB = $this->makeUser('pembeli');

        $ikan = $this->makeEndedLot($seller, [
            'tipe_lelang' => 'turun',
            'reserve_price' => 180_000,
        ]);

        Bid::create([
            'ikan_id' => $ikan->id,
            'user_id' => $buyerA->id,
            'jumlah_bid' => 140_000,
            'bidder_ip' => '127.0.0.2',
            'bidder_user_agent' => 'phpunit',
        ]);

        Bid::create([
            'ikan_id' => $ikan->id,
            'user_id' => $buyerB->id,
            'jumlah_bid' => 130_000,
            'bidder_ip' => '127.0.0.3',
            'bidder_user_agent' => 'phpunit',
        ]);

        app(LelangService::class)->tutupLelang($ikan);

        $ikan->refresh();

        $this->assertSame('GAGAL_TOTAL', $ikan->auction_state);
        $this->assertSame('reserve_not_met', $ikan->hard_stop_reason);

        $this->assertDatabaseHas('notification_outbox', [
            'recipient_user_id' => $seller->id,
            'category' => 'lelang',
            'title' => 'Lelang selesai tanpa pemenang',
            'status' => 'sent',
        ]);

        $this->assertDatabaseHas('notification_outbox', [
            'recipient_user_id' => $buyerA->id,
            'category' => 'lelang',
            'title' => 'Lelang selesai tanpa pemenang',
            'status' => 'sent',
        ]);

        $this->assertDatabaseHas('notification_outbox', [
            'recipient_user_id' => $buyerB->id,
            'category' => 'lelang',
            'title' => 'Lelang selesai tanpa pemenang',
            'status' => 'sent',
        ]);
    }

    public function test_expired_winner_triggers_fallback_and_penalty(): void
    {
        $seller = $this->makeUser('penjual');
        $buyerA = $this->makeUser('pembeli');
        $buyerB = $this->makeUser('pembeli');

        $ikan = $this->makeEndedLot($seller, [
            'reserve_price' => 105_000,
        ]);

        Bid::create([
            'ikan_id' => $ikan->id,
            'user_id' => $buyerA->id,
            'jumlah_bid' => 130_000,
            'bidder_ip' => '127.0.0.2',
            'bidder_user_agent' => 'phpunit',
        ]);

        Bid::create([
            'ikan_id' => $ikan->id,
            'user_id' => $buyerB->id,
            'jumlah_bid' => 120_000,
            'bidder_ip' => '127.0.0.3',
            'bidder_user_agent' => 'phpunit',
        ]);

        $service = app(LelangService::class);
        $service->tutupLelang($ikan);

        $transaksi = Transaksi::query()->where('ikan_id', $ikan->id)->firstOrFail();
        $transaksi->update([
            'bayar_sebelum' => now()->subMinute(),
            'status' => 'menunggu_bayar',
        ]);

        $service->handleExpiredTransaction($transaksi, 'test');

        $ikan->refresh();
        $transaksi->refresh();

        $this->assertSame('DIBAYAR', $ikan->auction_state);
        $this->assertSame(1, (int) $ikan->fallback_count);
        $this->assertSame(2, (int) $ikan->current_winner_rank);
        $this->assertSame($buyerB->id, (int) $transaksi->pemenang_id);
        $this->assertSame('lunas', $transaksi->status);

        $this->assertDatabaseHas('bidder_penalties', [
            'user_id' => $buyerA->id,
            'ikan_id' => $ikan->id,
            'reason' => 'payment_default',
        ]);

        $this->assertDatabaseHas('payment_attempts', [
            'ikan_id' => $ikan->id,
            'rank' => 1,
            'bidder_id' => $buyerA->id,
            'status' => 'kadaluarsa',
        ]);

        $this->assertDatabaseHas('payment_attempts', [
            'ikan_id' => $ikan->id,
            'rank' => 2,
            'bidder_id' => $buyerB->id,
            'status' => 'dibayar',
        ]);

        $this->assertDatabaseHas('notification_outbox', [
            'recipient_user_id' => $buyerA->id,
            'category' => 'pembayaran',
            'title' => 'Waktu pembayaran habis',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('notification_outbox', [
            'recipient_user_id' => $buyerB->id,
            'category' => 'pembayaran',
            'title' => 'Anda jadi pemenang berikutnya',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('notification_outbox', [
            'recipient_user_id' => $seller->id,
            'category' => 'operasional',
            'title' => 'Pemenang lelang dialihkan',
            'status' => 'pending',
        ]);
    }

    public function test_fallback_skips_candidate_with_insufficient_balance_and_picks_next_rank(): void
    {
        $seller = $this->makeUser('penjual');
        $buyerA = $this->makeUser('pembeli');
        $buyerB = $this->makeUser('pembeli', ['saldo' => 50_000]);
        $buyerC = $this->makeUser('pembeli');

        $ikan = $this->makeEndedLot($seller, [
            'reserve_price' => 100_000,
        ]);

        Bid::create([
            'ikan_id' => $ikan->id,
            'user_id' => $buyerA->id,
            'jumlah_bid' => 140_000,
            'bidder_ip' => '127.0.0.2',
            'bidder_user_agent' => 'phpunit',
        ]);

        Bid::create([
            'ikan_id' => $ikan->id,
            'user_id' => $buyerB->id,
            'jumlah_bid' => 130_000,
            'bidder_ip' => '127.0.0.3',
            'bidder_user_agent' => 'phpunit',
        ]);

        Bid::create([
            'ikan_id' => $ikan->id,
            'user_id' => $buyerC->id,
            'jumlah_bid' => 120_000,
            'bidder_ip' => '127.0.0.4',
            'bidder_user_agent' => 'phpunit',
        ]);

        $service = app(LelangService::class);
        $service->tutupLelang($ikan);

        $transaksi = Transaksi::query()->where('ikan_id', $ikan->id)->firstOrFail();
        $transaksi->update([
            'status' => 'menunggu_bayar',
            'bayar_sebelum' => now()->subMinute(),
        ]);

        $service->handleExpiredTransaction($transaksi, 'test');
        $transaksi->refresh();
        $ikan->refresh();

        $this->assertSame((int) $buyerC->id, (int) $transaksi->pemenang_id);
        $this->assertSame('lunas', $transaksi->status);
        $this->assertSame('DIBAYAR', $ikan->auction_state);
        $this->assertSame(1, (int) $ikan->fallback_count);
    }

    public function test_hard_stop_when_fallback_limit_already_reached(): void
    {
        $seller = $this->makeUser('penjual');
        $buyerA = $this->makeUser('pembeli');
        $buyerB = $this->makeUser('pembeli');

        $ikan = $this->makeEndedLot($seller, [
            'fallback_count' => 2,
            'current_winner_rank' => 1,
            'auction_state' => 'MENUNGGU_PEMBAYARAN',
        ]);

        Bid::create([
            'ikan_id' => $ikan->id,
            'user_id' => $buyerA->id,
            'jumlah_bid' => 130_000,
            'bidder_ip' => '127.0.0.2',
            'bidder_user_agent' => 'phpunit',
        ]);

        Bid::create([
            'ikan_id' => $ikan->id,
            'user_id' => $buyerB->id,
            'jumlah_bid' => 120_000,
            'bidder_ip' => '127.0.0.3',
            'bidder_user_agent' => 'phpunit',
        ]);

        $service = app(LelangService::class);
        $service->tutupLelang($ikan);

        $transaksi = Transaksi::query()->where('ikan_id', $ikan->id)->firstOrFail();
        $transaksi->update([
            'bayar_sebelum' => now()->subMinute(),
            'status' => 'menunggu_bayar',
        ]);

        $service->handleExpiredTransaction($transaksi, 'test');

        $ikan->refresh();

        $this->assertSame('GAGAL_TOTAL', $ikan->auction_state);
        $this->assertSame('max_fallback_reached', $ikan->hard_stop_reason);

        $this->assertDatabaseHas('notification_outbox', [
            'recipient_user_id' => $buyerA->id,
            'category' => 'pembayaran',
            'title' => 'Waktu pembayaran habis',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('notification_outbox', [
            'recipient_user_id' => $seller->id,
            'category' => 'operasional',
            'title' => 'Lelang berakhir tanpa pemenang',
            'status' => 'pending',
        ]);

        $this->assertSame(0, NotificationOutbox::query()
            ->where('recipient_user_id', $buyerB->id)
            ->where('title', 'Anda jadi pemenang berikutnya')
            ->count());
    }

    public function test_expired_winner_hard_stops_when_total_payment_window_has_ended(): void
    {
        $seller = $this->makeUser('penjual');
        $buyerA = $this->makeUser('pembeli');
        $buyerB = $this->makeUser('pembeli');

        $ikan = $this->makeEndedLot($seller, [
            'reserve_price' => 100_000,
        ]);

        Bid::create([
            'ikan_id' => $ikan->id,
            'user_id' => $buyerA->id,
            'jumlah_bid' => 140_000,
            'bidder_ip' => '127.0.0.2',
            'bidder_user_agent' => 'phpunit',
        ]);

        Bid::create([
            'ikan_id' => $ikan->id,
            'user_id' => $buyerB->id,
            'jumlah_bid' => 130_000,
            'bidder_ip' => '127.0.0.3',
            'bidder_user_agent' => 'phpunit',
        ]);

        $service = app(LelangService::class);
        $service->tutupLelang($ikan);

        $ikan->update([
            'ranking_frozen_at' => now()->subHours(6)->subMinutes(5),
        ]);

        $transaksi = Transaksi::query()->where('ikan_id', $ikan->id)->firstOrFail();
        $transaksi->update([
            'bayar_sebelum' => now()->subMinute(),
            'status' => 'menunggu_bayar',
        ]);

        $service->handleExpiredTransaction($transaksi, 'test');

        $ikan->refresh();
        $transaksi->refresh();

        $this->assertSame('GAGAL_TOTAL', $ikan->auction_state);
        $this->assertSame('payment_window_expired', $ikan->hard_stop_reason);
        $this->assertSame('kadaluarsa', $transaksi->status);
        $this->assertSame($buyerA->id, (int) $transaksi->pemenang_id);

        $this->assertSame(0, NotificationOutbox::query()
            ->where('recipient_user_id', $buyerB->id)
            ->where('title', 'Anda jadi pemenang berikutnya')
            ->count());
    }

    public function test_expiry_handler_is_idempotent_and_does_not_double_fallback(): void
    {
        $seller = $this->makeUser('penjual');
        $buyerA = $this->makeUser('pembeli');
        $buyerB = $this->makeUser('pembeli');
        $buyerC = $this->makeUser('pembeli');

        $ikan = $this->makeEndedLot($seller, [
            'reserve_price' => 100_000,
        ]);

        Bid::create([
            'ikan_id' => $ikan->id,
            'user_id' => $buyerA->id,
            'jumlah_bid' => 140_000,
            'bidder_ip' => '127.0.0.2',
            'bidder_user_agent' => 'phpunit',
        ]);

        Bid::create([
            'ikan_id' => $ikan->id,
            'user_id' => $buyerB->id,
            'jumlah_bid' => 130_000,
            'bidder_ip' => '127.0.0.3',
            'bidder_user_agent' => 'phpunit',
        ]);

        Bid::create([
            'ikan_id' => $ikan->id,
            'user_id' => $buyerC->id,
            'jumlah_bid' => 120_000,
            'bidder_ip' => '127.0.0.4',
            'bidder_user_agent' => 'phpunit',
        ]);

        $service = app(LelangService::class);
        $service->tutupLelang($ikan);

        $transaksi = Transaksi::query()->where('ikan_id', $ikan->id)->firstOrFail();
        $transaksi->update([
            'bayar_sebelum' => now()->subMinute(),
            'status' => 'menunggu_bayar',
        ]);

        $service->handleExpiredTransaction($transaksi, 'test');
        $service->handleExpiredTransaction($transaksi, 'test');

        $ikan->refresh();
        $transaksi->refresh();

        $this->assertSame(1, (int) $ikan->fallback_count);
        $this->assertSame(2, (int) $ikan->current_winner_rank);
        $this->assertSame($buyerB->id, (int) $transaksi->pemenang_id);

        $this->assertDatabaseCount('auction_fallback_histories', 1);
    }

    public function test_bidder_in_cooldown_cannot_place_bid(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli', [
            'auction_cooldown_until' => now()->addHours(8),
        ]);

        $ikan = $this->makeActiveLot($seller);

        $response = $this->actingAs($buyer)->post(route('bid.store', $ikan), [
            'jumlah_bid' => 120_000,
        ]);

        $response->assertSessionHas('error', function ($message): bool {
            return is_string($message) && str_contains(strtolower($message), 'cooldown');
        });
    }

    private function makeUser(string $role, array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'is_admin' => false,
            'is_blacklisted' => false,
            'auction_cooldown_until' => null,
            'reputation_score' => 100,
        ], $overrides));
    }

    private function makeEndedLot(User $seller, array $overrides = []): Ikan
    {
        return Ikan::create(array_merge([
            'user_id' => $seller->id,
            'nama_ikan' => 'Lot Lifecycle Test',
            'berat' => 12,
            'estimasi_jumlah_ekor' => 20,
            'jenis_kemasan' => 'keranjang',
            'kondisi' => 'segar',
            'deskripsi' => 'Testing lifecycle and fallback engine',
            'tipe_lelang' => 'naik',
            'harga_awal' => 100_000,
            'harga_tertinggi' => 100_000,
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
            'waktu_mulai' => now()->subHours(2),
            'waktu_selesai' => now()->subMinute(),
            'status' => 'aktif',
            'auction_state' => 'AKTIF',
            'fallback_count' => 0,
            'current_winner_rank' => null,
            'hard_stop_reason' => null,
            'ranking_frozen_at' => null,
            'state_version' => 1,
        ], $overrides));
    }

    private function makeActiveLot(User $seller): Ikan
    {
        return Ikan::create([
            'user_id' => $seller->id,
            'nama_ikan' => 'Lot Aktif Cooldown Test',
            'berat' => 10,
            'estimasi_jumlah_ekor' => 20,
            'jenis_kemasan' => 'keranjang',
            'kondisi' => 'segar',
            'deskripsi' => 'Testing bidder cooldown',
            'tipe_lelang' => 'naik',
            'harga_awal' => 100_000,
            'harga_tertinggi' => 100_000,
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
            'waktu_mulai' => now()->subMinutes(10),
            'waktu_selesai' => now()->addMinutes(50),
            'status' => 'aktif',
            'auction_state' => 'AKTIF',
            'fallback_count' => 0,
            'current_winner_rank' => null,
            'hard_stop_reason' => null,
            'ranking_frozen_at' => null,
            'state_version' => 1,
        ]);
    }
}
