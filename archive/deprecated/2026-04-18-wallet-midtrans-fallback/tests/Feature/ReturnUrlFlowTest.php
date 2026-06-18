<?php

namespace Tests\Feature;

use App\Models\AuctionRanking;
use App\Models\Ikan;
use App\Models\Transaksi;
use App\Models\User;
use App\Services\LelangService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReturnUrlFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_upload_validation_failure_redirects_to_create_with_return_url(): void
    {
        $seller = $this->makeUser('penjual');
        $returnUrl = route('penjual.ikans.index');

        $response = $this->actingAs($seller)->post(route('penjual.ikans.store'), [
            'return_url' => $returnUrl,
        ]);

        $response->assertSessionHasErrors('nama_ikan');
        $response->assertSessionHas('error', 'Data upload belum lengkap. Mohon lengkapi data wajib lalu coba lagi.');
        $response->assertRedirect(route('penjual.ikans.create', ['return_url' => $returnUrl]));
    }

    public function test_seller_upload_requires_photo(): void
    {
        $seller = $this->makeUser('penjual');
        $returnUrl = route('penjual.ikans.index');

        $response = $this->actingAs($seller)->post(route('penjual.ikans.store'), [
            'return_url' => $returnUrl,
            'nama_ikan' => 'Bandeng Segar Uji',
            'berat' => 12,
            'kondisi' => 'segar',
            'tipe_lelang' => 'naik',
            'harga_awal' => 100_000,
            'minimal_increment' => 5_000,
            'mulai_sekarang' => 1,
            'waktu_selesai' => now()->addHours(2)->toDateTimeString(),
        ]);

        $response->assertSessionHasErrors('foto');
        $response->assertSessionHas('error', 'Data upload belum lengkap. Mohon lengkapi data wajib lalu coba lagi.');
        $response->assertRedirect(route('penjual.ikans.create', ['return_url' => $returnUrl]));
    }

    public function test_seller_success_upload_redirects_to_dashboard(): void
    {
        Storage::fake('public');

        $seller = $this->makeUser('penjual');

        $response = $this->actingAs($seller)->post(route('penjual.ikans.store'), [
            'return_url' => route('ikans.index'),
            'nama_ikan' => 'Bandeng Segar Baru',
            'berat' => 10,
            'kondisi' => 'segar',
            'tipe_lelang' => 'naik',
            'harga_awal' => 120_000,
            'reserve_price' => 130_000,
            'minimal_increment' => 5_000,
            'payment_deadline_minutes' => 240,
            'payment_deadline_fallback_one_minutes' => 90,
            'payment_deadline_fallback_two_minutes' => 45,
            'payment_window_limit_minutes' => 360,
            'mulai_sekarang' => 1,
            'waktu_selesai' => now()->addHours(3)->toDateTimeString(),
            'foto' => UploadedFile::fake()->image('ikan.jpg'),
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('sukses');
        $response->assertRedirect(route('penjual.ikans.index'));

        $lot = Ikan::query()->where('user_id', $seller->id)->latest('id')->first();
        $this->assertNotNull($lot);
        $this->assertNotNull($lot->foto);
        $this->assertNull($lot->reserve_price);
        $this->assertSame(240, (int) $lot->payment_deadline_minutes);
        $this->assertSame(240, (int) $lot->payment_deadline_initial_minutes);
        $this->assertSame(90, (int) $lot->payment_deadline_fallback_one_minutes);
        $this->assertSame(45, (int) $lot->payment_deadline_fallback_two_minutes);
        $this->assertSame(360, (int) $lot->payment_window_limit_minutes);
        Storage::disk('public')->assertExists($lot->foto);
    }

    public function test_seller_upload_rejects_invalid_fallback_policy_order(): void
    {
        Storage::fake('public');

        $seller = $this->makeUser('penjual');

        $response = $this->actingAs($seller)->post(route('penjual.ikans.store'), [
            'return_url' => route('ikans.index'),
            'nama_ikan' => 'Bandeng Policy Invalid',
            'berat' => 10,
            'kondisi' => 'segar',
            'tipe_lelang' => 'naik',
            'harga_awal' => 120_000,
            'minimal_increment' => 5_000,
            'payment_deadline_minutes' => 45,
            'payment_deadline_fallback_one_minutes' => 60,
            'payment_deadline_fallback_two_minutes' => 30,
            'payment_window_limit_minutes' => 90,
            'mulai_sekarang' => 1,
            'waktu_selesai' => now()->addHours(3)->toDateTimeString(),
            'foto' => UploadedFile::fake()->image('ikan.jpg'),
        ]);

        $response->assertSessionHasErrors('payment_deadline_fallback_one_minutes');
        $response->assertRedirect(route('penjual.ikans.create', ['return_url' => route('ikans.index')]));
    }

    public function test_bid_validation_failure_redirects_to_given_return_url(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli');

        $ikan = $this->makeActiveLot($seller);
        $returnUrl = route('ikans.show', [
            'ikan' => $ikan,
            'return_url' => route('ikans.index'),
        ]);

        $response = $this->actingAs($buyer)->post(route('bid.store', $ikan), [
            'jumlah_bid' => 0,
            'return_url' => $returnUrl,
        ]);

        $response->assertSessionHasErrors('jumlah_bid');
        $response->assertRedirect($returnUrl);
    }

    public function test_bid_validation_failure_with_external_return_url_redirects_to_safe_default(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli');

        $ikan = $this->makeActiveLot($seller);

        $response = $this->actingAs($buyer)->post(route('bid.store', $ikan), [
            'jumlah_bid' => 0,
            'return_url' => 'https://evil.example/phish',
        ]);

        $response->assertSessionHasErrors('jumlah_bid');
        $response->assertRedirect(route('ikans.show', $ikan));
    }

    public function test_seller_packing_validation_failure_redirects_to_given_return_url(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli');

        $ikan = $this->makeActiveLot($seller);
        $this->makeEscrowTransaction($ikan, $buyer, [
            'delivery_status' => 'menunggu_pengiriman',
        ]);

        $returnUrl = route('penjual.ikans.show', [
            'ikan' => $ikan,
            'return_url' => route('penjual.ikans.index'),
        ]);

        $response = $this->actingAs($seller)->post(route('penjual.ikans.packing', $ikan), [
            'packing_proof' => UploadedFile::fake()->create('proof.pdf', 32, 'application/pdf'),
            'return_url' => $returnUrl,
        ]);

        $response->assertSessionHasErrors('packing_proof');
        $response->assertRedirect($returnUrl);
    }

    public function test_seller_packing_success_redirects_to_given_return_url(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli');

        $ikan = $this->makeActiveLot($seller);
        $transaksi = $this->makeEscrowTransaction($ikan, $buyer, [
            'delivery_status' => 'menunggu_pengiriman',
            'packed_at' => null,
        ]);

        $returnUrl = route('penjual.ikans.index', [
            'tipe_lelang' => 'naik',
        ]);

        $response = $this->actingAs($seller)->post(route('penjual.ikans.packing', $ikan), [
            'return_url' => $returnUrl,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('sukses');
        $response->assertRedirect($returnUrl);

        $this->assertNotNull($transaksi->fresh()->packed_at);
    }

    public function test_seller_shipping_validation_failure_redirects_to_given_return_url(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli');

        $ikan = $this->makeActiveLot($seller);
        $this->makeEscrowTransaction($ikan, $buyer, [
            'delivery_status' => 'diproses',
            'packed_at' => now()->subMinutes(20),
        ]);

        $returnUrl = route('penjual.ikans.show', [
            'ikan' => $ikan,
            'return_url' => route('penjual.ikans.index'),
        ]);

        $response = $this->actingAs($seller)->post(route('penjual.ikans.shipping', $ikan), [
            'courier_name' => '',
            'tracking_number' => '',
            'return_url' => $returnUrl,
        ]);

        $response->assertSessionHasErrors(['courier_name', 'tracking_number']);
        $response->assertRedirect($returnUrl);
    }

    public function test_seller_shipping_success_redirects_to_given_return_url(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli');

        $ikan = $this->makeActiveLot($seller);
        $this->makeEscrowTransaction($ikan, $buyer, [
            'delivery_status' => 'diproses',
            'packed_at' => now()->subMinutes(25),
            'shipped_at' => null,
            'tracking_number' => null,
            'courier_name' => null,
        ]);

        $returnUrl = route('penjual.ikans.index', [
            'tipe_lelang' => 'naik',
        ]);

        $response = $this->actingAs($seller)->post(route('penjual.ikans.shipping', $ikan), [
            'courier_name' => 'JNE Cargo',
            'tracking_number' => 'JNE-12345678',
            'estimated_arrival_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'return_url' => $returnUrl,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('sukses');
        $response->assertRedirect($returnUrl);
    }

    public function test_seller_shipping_requires_packing_before_update(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli');

        $ikan = $this->makeActiveLot($seller);
        $this->makeEscrowTransaction($ikan, $buyer, [
            'delivery_status' => 'diproses',
            'packed_at' => null,
        ]);

        $returnUrl = route('penjual.ikans.show', [
            'ikan' => $ikan,
            'return_url' => route('penjual.ikans.index'),
        ]);

        $response = $this->actingAs($seller)->post(route('penjual.ikans.shipping', $ikan), [
            'courier_name' => 'JNE Cargo',
            'tracking_number' => 'JNE-12345678',
            'return_url' => $returnUrl,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('error', 'Konfirmasi packing wajib dilakukan sebelum update pengiriman.');
        $response->assertRedirect($returnUrl);
    }

    public function test_seller_reverse_upload_rejects_reference_price_below_two_thousand(): void
    {
        Storage::fake('public');

        $seller = $this->makeUser('penjual');
        $returnUrl = route('penjual.ikans.index');

        $response = $this->actingAs($seller)->post(route('penjual.ikans.store'), [
            'return_url' => $returnUrl,
            'nama_ikan' => 'Nila Reverse Uji',
            'berat' => 8,
            'kondisi' => 'segar',
            'tipe_lelang' => 'turun',
            'harga_awal' => 1_000,
            'mulai_sekarang' => 1,
            'waktu_selesai' => now()->addHours(2)->toDateTimeString(),
            'foto' => UploadedFile::fake()->image('reverse.jpg'),
        ]);

        $response->assertSessionHasErrors('harga_awal');
        $response->assertRedirect(route('penjual.ikans.create', ['return_url' => $returnUrl]));
    }

    public function test_buyer_confirm_received_redirects_to_given_return_url_and_releases_escrow(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli');

        $ikan = $this->makeActiveLot($seller, [
            'status' => 'selesai',
        ]);

        $transaksi = $this->makeEscrowTransaction($ikan, $buyer, [
            'delivery_status' => 'dikirim',
        ]);

        $returnUrl = route('pembeli.aktivitas.detail', [
            'ikan' => $ikan,
            'return_url' => route('pembeli.aktivitas'),
        ]);

        $response = $this->actingAs($buyer)->post(route('pembeli.ikans.diterima', $ikan), [
            'return_url' => $returnUrl,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('sukses');
        $response->assertRedirect($returnUrl);

        $this->assertDatabaseHas('transaksis', [
            'id' => $transaksi->id,
            'escrow_status' => 'dilepas',
            'delivery_status' => 'diterima',
            'status' => 'lunas',
        ]);

        $this->assertDatabaseHas('ikans', [
            'id' => $ikan->id,
            'status' => 'terbayar',
        ]);
    }

    public function test_buy_now_redirects_to_payment_and_preserves_return_url_chain(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli');

        $ikan = $this->makeActiveLot($seller, [
            'buy_now_enabled' => true,
            'buy_now_price' => 150_000,
            'harga_tertinggi' => 120_000,
            'status' => 'aktif',
            'tipe_lelang' => 'naik',
        ]);

        $returnUrl = route('ikans.show', [
            'ikan' => $ikan,
            'return_url' => route('ikans.index'),
        ]);

        $response = $this->actingAs($buyer)->post(route('ikans.buy_now', $ikan), [
            'return_url' => $returnUrl,
        ]);

        $transaksi = Transaksi::query()->where('ikan_id', $ikan->id)->firstOrFail();
        $this->assertSame('lunas', $transaksi->status);
        $paymentUrl = route('pembayaran.show', [
            'transaksi' => $transaksi,
            'return_url' => $returnUrl,
        ]);

        $response->assertRedirect($paymentUrl);

        $paymentPage = $this->actingAs($buyer)->get($paymentUrl);
        $paymentPage->assertOk();
        $paymentPage->assertSee('Dana otomatis diamankan dari saldo Anda', false);
        $paymentPage->assertSee($returnUrl, false);

        $doneUrl = route('pembayaran.selesai', [
            'transaksi' => $transaksi,
            'return_url' => $returnUrl,
        ]);

        $donePage = $this->actingAs($buyer)->get($doneUrl);
        $donePage->assertOk();
        $donePage->assertSee($returnUrl, false);
    }

    public function test_buy_now_unavailable_redirects_to_given_return_url(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli');

        $ikan = $this->makeActiveLot($seller, [
            'buy_now_enabled' => false,
            'buy_now_price' => null,
        ]);

        $returnUrl = route('ikans.show', [
            'ikan' => $ikan,
            'return_url' => route('ikans.index'),
        ]);

        $response = $this->actingAs($buyer)->post(route('ikans.buy_now', $ikan), [
            'return_url' => $returnUrl,
        ]);

        $response->assertSessionHas('error');
        $response->assertRedirect($returnUrl);
        $this->assertDatabaseCount('transaksis', 0);
    }

    public function test_buy_now_unavailable_with_external_return_url_redirects_to_safe_default(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli');

        $ikan = $this->makeActiveLot($seller, [
            'buy_now_enabled' => false,
            'buy_now_price' => null,
        ]);

        $response = $this->actingAs($buyer)->post(route('ikans.buy_now', $ikan), [
            'return_url' => 'https://evil.example/phish',
        ]);

        $response->assertSessionHas('error');
        $response->assertRedirect(route('ikans.show', $ikan));
    }

    public function test_payment_show_ignores_external_return_url_in_back_navigation(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli');

        $ikan = $this->makeActiveLot($seller, [
            'status' => 'selesai',
        ]);

        $transaksi = $this->makeEscrowTransaction($ikan, $buyer, [
            'status' => 'menunggu_bayar',
            'escrow_status' => 'belum',
            'dibayar_pada' => null,
            'escrow_locked_at' => null,
            'delivery_status' => 'menunggu_pengiriman',
            'bayar_sebelum' => now()->addHours(6),
        ]);

        $paymentUrl = route('pembayaran.show', [
            'transaksi' => $transaksi,
            'return_url' => 'https://evil.example/phish',
        ]);

        $expectedFallback = route('ikans.show', [
            'ikan' => $ikan,
            'return_url' => route('ikans.index'),
        ]);

        $response = $this->actingAs($buyer)->get($paymentUrl);

        $response->assertOk();
        $response->assertDontSee('https://evil.example/phish', false);
        $response->assertSee($expectedFallback, false);
    }

    public function test_payment_token_rejects_expired_transaction_and_marks_kadaluarsa(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli');

        $ikan = $this->makeActiveLot($seller, [
            'status' => 'selesai',
        ]);

        $transaksi = $this->makeEscrowTransaction($ikan, $buyer, [
            'status' => 'menunggu_bayar',
            'escrow_status' => 'belum',
            'dibayar_pada' => null,
            'escrow_locked_at' => null,
            'delivery_status' => 'menunggu_pengiriman',
            'bayar_sebelum' => now()->subMinute(),
        ]);

        $response = $this->actingAs($buyer)
            ->post(route('pembayaran.token', $transaksi));

        $response->assertStatus(422);
        $response->assertJson([
            'error' => 'Waktu pembayaran sudah habis untuk transaksi ini.',
        ]);

        $this->assertDatabaseHas('transaksis', [
            'id' => $transaksi->id,
            'status' => 'kadaluarsa',
        ]);
    }

    public function test_previous_winner_payment_page_redirects_after_timeout_fallback_without_403(): void
    {
        ['transaksi' => $transaksi, 'previousWinner' => $previousWinner] = $this->prepareExpiredFallbackTransaction();

        $response = $this->actingAs($previousWinner)->get(route('pembayaran.show', $transaksi));

        $response->assertRedirect(route('pembeli.aktivitas'));
        $response->assertSessionHas('error', 'Akses pembayaran sudah tidak tersedia karena waktu bayar habis atau pemenang telah dialihkan.');
    }

    public function test_previous_winner_payment_token_returns_user_friendly_error_after_timeout_fallback(): void
    {
        ['transaksi' => $transaksi, 'previousWinner' => $previousWinner] = $this->prepareExpiredFallbackTransaction();

        $response = $this->actingAs($previousWinner)->post(route('pembayaran.token', $transaksi));

        $response->assertStatus(422);
        $response->assertJson([
            'error' => 'Akses pembayaran sudah tidak tersedia karena waktu bayar habis atau pemenang telah dialihkan.',
        ]);
    }

    public function test_payment_done_page_redirects_to_payment_show_when_not_lunas(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli');

        $ikan = $this->makeActiveLot($seller, [
            'status' => 'selesai',
        ]);

        $transaksi = $this->makeEscrowTransaction($ikan, $buyer, [
            'status' => 'menunggu_bayar',
            'escrow_status' => 'belum',
            'dibayar_pada' => null,
            'escrow_locked_at' => null,
            'delivery_status' => 'menunggu_pengiriman',
        ]);

        $returnUrl = route('ikans.show', [
            'ikan' => $ikan,
            'return_url' => route('ikans.index'),
        ]);

        $doneUrl = route('pembayaran.selesai', [
            'transaksi' => $transaksi,
            'return_url' => $returnUrl,
        ]);

        $paymentUrl = route('pembayaran.show', [
            'transaksi' => $transaksi,
            'return_url' => $returnUrl,
        ]);

        $response = $this->actingAs($buyer)->get($doneUrl);

        $response->assertSessionHas('error');
        $response->assertRedirect($paymentUrl);
    }

    private function makeUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_admin' => false,
        ]);
    }

    private function makeActiveLot(User $seller, array $overrides = []): Ikan
    {
        return Ikan::create(array_merge([
            'user_id' => $seller->id,
            'nama_ikan' => 'Bandeng Uji',
            'berat' => 12,
            'estimasi_jumlah_ekor' => 30,
            'jenis_kemasan' => 'keranjang',
            'kondisi' => 'segar',
            'deskripsi' => 'Lot untuk test return url',
            'tipe_lelang' => 'naik',
            'harga_awal' => 120_000,
            'harga_tertinggi' => 120_000,
            'minimal_increment' => 5_000,
            'buy_now_enabled' => false,
            'buy_now_price' => null,
            'anti_sniping_enabled' => true,
            'anti_sniping_window_seconds' => 120,
            'anti_sniping_extend_seconds' => 120,
            'anti_sniping_max_extensions' => 3,
            'anti_sniping_extensions_used' => 0,
            'waktu_mulai' => now()->subMinutes(15),
            'waktu_selesai' => now()->addMinutes(45),
            'status' => 'aktif',
            'state_version' => 1,
        ], $overrides));
    }

    private function makeEscrowTransaction(Ikan $ikan, User $buyer, array $overrides = []): Transaksi
    {
        return Transaksi::create(array_merge([
            'ikan_id' => $ikan->id,
            'pemenang_id' => $buyer->id,
            'harga_final' => 150_000,
            'status' => 'lunas',
            'bayar_sebelum' => now()->addHours(24),
            'dibayar_pada' => now()->subHour(),
            'escrow_status' => 'ditahan',
            'escrow_amount' => 150_000,
            'escrow_locked_at' => now()->subHour(),
            'delivery_status' => 'menunggu_pengiriman',
            'delivery_cost' => 0,
        ], $overrides));
    }

    private function prepareExpiredFallbackTransaction(): array
    {
        $seller = $this->makeUser('penjual');
        $previousWinner = $this->makeUser('pembeli');
        $nextWinner = $this->makeUser('pembeli');

        $ikan = $this->makeActiveLot($seller, [
            'status' => 'selesai',
            'auction_state' => 'MENUNGGU_PEMBAYARAN',
            'fallback_count' => 0,
            'current_winner_rank' => 1,
            'waktu_selesai' => now()->subMinute(),
        ]);

        AuctionRanking::create([
            'ikan_id' => $ikan->id,
            'rank' => 1,
            'bidder_id' => $previousWinner->id,
            'bid_id' => null,
            'bid_amount' => 180_000,
            'bid_created_at' => now()->subMinutes(2),
            'snapshot_hash' => hash('sha256', "{$ikan->id}|1|{$previousWinner->id}"),
        ]);

        AuctionRanking::create([
            'ikan_id' => $ikan->id,
            'rank' => 2,
            'bidder_id' => $nextWinner->id,
            'bid_id' => null,
            'bid_amount' => 175_000,
            'bid_created_at' => now()->subMinute(),
            'snapshot_hash' => hash('sha256', "{$ikan->id}|2|{$nextWinner->id}"),
        ]);

        $transaksi = $this->makeEscrowTransaction($ikan, $previousWinner, [
            'harga_final' => 180_000,
            'status' => 'menunggu_bayar',
            'escrow_status' => 'belum',
            'escrow_amount' => 180_000,
            'escrow_locked_at' => null,
            'dibayar_pada' => null,
            'delivery_status' => 'menunggu_pengiriman',
            'bayar_sebelum' => now()->subMinutes(2),
        ]);

        app(LelangService::class)->handleExpiredTransaction($transaksi, 'test');

        $transaksi = $transaksi->fresh();
        $this->assertNotNull($transaksi);
        $this->assertSame((int) $nextWinner->id, (int) $transaksi->pemenang_id);

        return [
            'seller' => $seller,
            'previousWinner' => $previousWinner,
            'nextWinner' => $nextWinner,
            'ikan' => $ikan->fresh(),
            'transaksi' => $transaksi,
        ];
    }
}
