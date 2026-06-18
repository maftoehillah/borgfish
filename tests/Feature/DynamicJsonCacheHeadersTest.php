<?php

namespace Tests\Feature;

use App\Models\Ikan;
use App\Models\Transaksi;
use App\Models\User;
use App\Services\PembayaranService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DynamicJsonCacheHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_lot_state_endpoint_is_marked_as_no_store(): void
    {
        $seller = User::factory()->create([
            'role' => 'penjual',
        ]);

        $lot = Ikan::create([
            'user_id' => $seller->id,
            'nama_ikan' => 'Lot Header Dinamis',
            'berat' => 12,
            'estimasi_jumlah_ekor' => 14,
            'jenis_kemasan' => 'keranjang',
            'kondisi' => 'segar',
            'deskripsi' => 'Lot untuk test cache header JSON.',
            'tipe_lelang' => 'naik',
            'harga_awal' => 100000,
            'harga_tertinggi' => 100000,
            'minimal_increment' => 5000,
            'buy_now_enabled' => false,
            'buy_now_price' => null,
            'anti_sniping_enabled' => true,
            'anti_sniping_window_seconds' => 120,
            'anti_sniping_extend_seconds' => 120,
            'anti_sniping_max_extensions' => 3,
            'anti_sniping_extensions_used' => 0,
            'waktu_mulai' => now()->subMinutes(15),
            'waktu_selesai' => now()->addHour(),
            'status' => 'aktif',
            'auction_state' => 'AKTIF',
            'state_version' => 1,
        ]);

        $response = $this->getJson(route('ikans.state', $lot));

        $response->assertOk();
        $response->assertHeader('Pragma', 'no-cache');
        $cacheControl = strtolower((string) $response->headers->get('Cache-Control', ''));
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
    }

    public function test_payment_refresh_endpoint_is_marked_as_no_store(): void
    {
        $buyer = User::factory()->create([
            'role' => 'pembeli',
        ]);
        $seller = User::factory()->create([
            'role' => 'penjual',
        ]);

        $lot = Ikan::create([
            'user_id' => $seller->id,
            'nama_ikan' => 'Lot Pembayaran Dinamis',
            'berat' => 8,
            'estimasi_jumlah_ekor' => 10,
            'jenis_kemasan' => 'keranjang',
            'kondisi' => 'segar',
            'deskripsi' => 'Lot untuk test refresh pembayaran.',
            'tipe_lelang' => 'naik',
            'harga_awal' => 100000,
            'harga_tertinggi' => 125000,
            'minimal_increment' => 5000,
            'buy_now_enabled' => false,
            'buy_now_price' => null,
            'anti_sniping_enabled' => true,
            'anti_sniping_window_seconds' => 120,
            'anti_sniping_extend_seconds' => 120,
            'anti_sniping_max_extensions' => 3,
            'anti_sniping_extensions_used' => 0,
            'waktu_mulai' => now()->subHours(2),
            'waktu_selesai' => now()->subMinutes(15),
            'status' => 'selesai',
            'auction_state' => 'MENUNGGU_PEMBAYARAN',
            'state_version' => 1,
        ]);

        $transaksi = Transaksi::create([
            'ikan_id' => $lot->id,
            'pemenang_id' => $buyer->id,
            'winner_rank' => 1,
            'harga_final' => 125000,
            'status' => 'menunggu_bayar',
            'payment_status' => 'pending',
            'bayar_sebelum' => now()->addMinutes(20),
            'pickup_status' => 'waiting_payment',
        ]);

        $service = Mockery::mock(PembayaranService::class);
        $service->shouldReceive('refreshPendingAttempt')
            ->once()
            ->with(Mockery::on(fn ($argument) => $argument instanceof Transaksi && $argument->is($transaksi)))
            ->andReturn([
                'status' => 'ok',
                'payment_status' => 'pending',
            ]);

        $this->app->instance(PembayaranService::class, $service);

        $response = $this->actingAs($buyer)
            ->withSession(['otp_verified_user_id' => $buyer->id])
            ->postJson(route('pembayaran.refresh', $transaksi));

        $response->assertOk();
        $response->assertHeader('Pragma', 'no-cache');
        $cacheControl = strtolower((string) $response->headers->get('Cache-Control', ''));
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
        $response->assertJson([
            'status' => 'ok',
            'payment_status' => 'pending',
        ]);
    }
}
