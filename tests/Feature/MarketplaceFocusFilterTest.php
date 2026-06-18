<?php

namespace Tests\Feature;

use App\Models\Bid;
use App\Models\Ikan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceFocusFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_focus_aktif_only_shows_active_lots(): void
    {
        $seller = $this->makeUser('penjual');

        $aktif = $this->makeLot($seller, 'Lot Aktif A', [
            'status' => 'aktif',
            'waktu_mulai' => now()->subHour(),
            'waktu_selesai' => now()->addHours(2),
        ]);

        $this->makeLot($seller, 'Lot Menunggu B', [
            'status' => 'menunggu',
            'waktu_mulai' => now()->addHour(),
            'waktu_selesai' => now()->addHours(3),
        ]);

        $this->makeLot($seller, 'Lot Selesai C', [
            'status' => 'selesai',
            'waktu_mulai' => now()->subDays(2),
            'waktu_selesai' => now()->subDay(),
        ]);

        $response = $this->get(route('ikans.index', ['fokus' => 'aktif']));

        $response->assertOk();
        $response->assertSee($aktif->nama_ikan);
        $response->assertDontSee('Lot Menunggu B');
        $response->assertDontSee('Lot Selesai C');
    }

    public function test_focus_hampir_selesai_only_shows_lots_ending_within_30_minutes(): void
    {
        $seller = $this->makeUser('penjual');

        $nearEnd = $this->makeLot($seller, 'Lot Hampir Tutup', [
            'status' => 'aktif',
            'waktu_mulai' => now()->subHours(2),
            'waktu_selesai' => now()->addMinutes(20),
        ]);

        $this->makeLot($seller, 'Lot Masih Lama', [
            'status' => 'aktif',
            'waktu_mulai' => now()->subHours(2),
            'waktu_selesai' => now()->addHours(3),
        ]);

        $response = $this->get(route('ikans.index', ['fokus' => 'hampir_selesai']));

        $response->assertOk();
        $response->assertSee($nearEnd->nama_ikan);
        $response->assertDontSee('Lot Masih Lama');
    }

    public function test_focus_selesai_only_shows_completed_lots(): void
    {
        $seller = $this->makeUser('penjual');

        $selesai = $this->makeLot($seller, 'Lot Riwayat Selesai', [
            'status' => 'selesai',
            'waktu_mulai' => now()->subDays(3),
            'waktu_selesai' => now()->subDays(2),
        ]);

        $this->makeLot($seller, 'Lot Aktif D', [
            'status' => 'aktif',
            'waktu_mulai' => now()->subHour(),
            'waktu_selesai' => now()->addHours(2),
        ]);

        $response = $this->get(route('ikans.index', ['fokus' => 'selesai']));

        $response->assertOk();
        $response->assertSee($selesai->nama_ikan);
        $response->assertDontSee('Lot Aktif D');
    }

    public function test_focus_terpopuler_orders_active_lots_by_unique_bidders_then_total_bids(): void
    {
        $seller = $this->makeUser('penjual');
        $buyerA = $this->makeUser('pembeli');
        $buyerB = $this->makeUser('pembeli');
        $buyerC = $this->makeUser('pembeli');

        $mostPopular = $this->makeLot($seller, 'Lot Terpopuler', [
            'status' => 'aktif',
            'harga_awal' => 150_000,
            'harga_tertinggi' => 150_000,
            'waktu_mulai' => now()->subHour(),
            'waktu_selesai' => now()->addHour(),
        ]);

        $lessPopular = $this->makeLot($seller, 'Lot Kurang Populer', [
            'status' => 'aktif',
            'harga_awal' => 250_000,
            'harga_tertinggi' => 250_000,
            'waktu_mulai' => now()->subHour(),
            'waktu_selesai' => now()->addHours(2),
        ]);

        Bid::query()->create([
            'ikan_id' => $mostPopular->id,
            'user_id' => $buyerA->id,
            'jumlah_bid' => 155_000,
        ]);

        Bid::query()->create([
            'ikan_id' => $mostPopular->id,
            'user_id' => $buyerB->id,
            'jumlah_bid' => 160_000,
        ]);

        Bid::query()->create([
            'ikan_id' => $mostPopular->id,
            'user_id' => $buyerC->id,
            'jumlah_bid' => 165_000,
        ]);

        Bid::query()->create([
            'ikan_id' => $lessPopular->id,
            'user_id' => $buyerA->id,
            'jumlah_bid' => 255_000,
        ]);

        Bid::query()->create([
            'ikan_id' => $lessPopular->id,
            'user_id' => $buyerA->id,
            'jumlah_bid' => 260_000,
        ]);

        $response = $this->get(route('ikans.index', ['fokus' => 'terpopuler']));

        $response->assertOk();
        $response->assertSeeInOrder([$mostPopular->nama_ikan, $lessPopular->nama_ikan]);
    }

    public function test_legacy_highest_bid_focus_alias_maps_to_popular_focus(): void
    {
        $seller = $this->makeUser('penjual');
        $lot = $this->makeLot($seller, 'Lot Alias Populer', [
            'status' => 'aktif',
            'waktu_mulai' => now()->subHour(),
            'waktu_selesai' => now()->addHour(),
        ]);

        $response = $this->get(route('ikans.index', ['fokus' => 'nilai_bid_tertinggi']));

        $response->assertOk();
        $response->assertSee('Lot Terpopuler');
        $response->assertSee($lot->nama_ikan);
    }

    private function makeUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_admin' => false,
        ]);
    }

    private function makeLot(User $seller, string $name, array $overrides = []): Ikan
    {
        return Ikan::create(array_merge([
            'user_id' => $seller->id,
            'nama_ikan' => $name,
            'berat' => 10,
            'estimasi_jumlah_ekor' => 20,
            'jenis_kemasan' => 'keranjang',
            'kondisi' => 'segar',
            'deskripsi' => 'Lot uji filter marketplace',
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
            'waktu_mulai' => now()->subMinutes(30),
            'waktu_selesai' => now()->addHours(2),
            'status' => 'aktif',
            'state_version' => 1,
        ], $overrides));
    }
}
