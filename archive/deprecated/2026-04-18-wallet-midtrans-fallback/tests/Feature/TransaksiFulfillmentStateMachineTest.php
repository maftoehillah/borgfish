<?php

namespace Tests\Feature;

use App\Models\Ikan;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransaksiFulfillmentStateMachineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('midtrans.server_key', 'SB-Mid-server-valid-12345');
        config()->set('midtrans.client_key', 'SB-Mid-client-valid-12345');
        config()->set('midtrans.is_production', false);
    }

    public function test_webhook_settlement_sets_fulfillment_state_and_audit_log(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli');
        $ikan = $this->makeLot($seller, ['status' => 'selesai']);

        $transaksi = $this->makeTransaksi($ikan, $buyer, [
            'status' => 'menunggu_bayar',
            'escrow_status' => 'belum',
            'delivery_status' => 'menunggu_pengiriman',
            'midtrans_order_id' => 'BORGFISH-FULFILLMENT-001',
        ]);

        $grossAmount = number_format($transaksi->totalTagihan(), 2, '.', '');
        $payload = [
            'order_id' => $transaksi->midtrans_order_id,
            'transaction_status' => 'settlement',
            'status_code' => '200',
            'gross_amount' => $grossAmount,
            'payment_type' => 'bank_transfer',
            'signature_key' => $this->sign($transaksi->midtrans_order_id, '200', $grossAmount),
        ];

        $response = $this->postJson(route('midtrans.webhook'), $payload);

        $response->assertOk();

        $this->assertDatabaseHas('transaksis', [
            'id' => $transaksi->id,
            'status' => 'lunas',
            'fulfillment_state' => 'DIBAYAR',
        ]);

        $this->assertDatabaseHas('transaction_state_logs', [
            'transaksi_id' => $transaksi->id,
            'to_state' => 'DIBAYAR',
            'reason_code' => 'PAYMENT_SETTLED',
        ]);
    }

    public function test_seller_mark_shipped_transitions_to_dikirim_fulfillment(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli');
        $ikan = $this->makeLot($seller, ['status' => 'terbayar']);

        $transaksi = $this->makeTransaksi($ikan, $buyer, [
            'status' => 'lunas',
            'fulfillment_state' => 'DIPROSES_PENJUAL',
            'escrow_status' => 'ditahan',
            'delivery_status' => 'diproses',
            'dibayar_pada' => now()->subHours(1),
            'paid_at' => now()->subHours(1),
            'packed_at' => now()->subMinutes(30),
        ]);

        $response = $this->actingAs($seller)->post(route('penjual.ikans.shipping', $ikan), [
            'courier_name' => 'JNE Cargo',
            'tracking_number' => 'JNE-12345678',
            'estimated_arrival_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'return_url' => route('penjual.ikans.show', $ikan),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('sukses');

        $this->assertDatabaseHas('transaksis', [
            'id' => $transaksi->id,
            'fulfillment_state' => 'DIKIRIM',
            'delivery_status' => 'dikirim',
            'tracking_number' => 'JNE-12345678',
        ]);

        $this->assertDatabaseHas('transaction_state_logs', [
            'transaksi_id' => $transaksi->id,
            'to_state' => 'DIKIRIM',
            'actor_type' => 'seller',
        ]);
    }

    public function test_buyer_confirm_is_blocked_when_transaction_is_disputed(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli');
        $ikan = $this->makeLot($seller, ['status' => 'terbayar']);

        $transaksi = $this->makeTransaksi($ikan, $buyer, [
            'status' => 'proses',
            'fulfillment_state' => 'DISENGKETAKAN',
            'escrow_status' => 'ditahan',
            'delivery_status' => 'dikirim',
            'shipped_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($buyer)->post(route('pembeli.ikans.diterima', $ikan), [
            'return_url' => route('pembeli.aktivitas.detail', $ikan),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('transaksis', [
            'id' => $transaksi->id,
            'fulfillment_state' => 'DISENGKETAKAN',
            'status' => 'proses',
        ]);
    }

    private function sign(string $orderId, string $statusCode, string $grossAmount): string
    {
        return hash('sha512', $orderId . $statusCode . $grossAmount . (string) config('midtrans.server_key'));
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
            'nama_ikan' => 'Tuna Uji Fulfillment',
            'berat' => 12,
            'estimasi_jumlah_ekor' => 18,
            'jenis_kemasan' => 'keranjang',
            'kondisi' => 'segar',
            'deskripsi' => 'Lot untuk test fulfillment state machine',
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
            'waktu_selesai' => now()->addMinutes(20),
            'status' => 'aktif',
            'state_version' => 1,
        ], $overrides));
    }

    private function makeTransaksi(Ikan $ikan, User $buyer, array $overrides = []): Transaksi
    {
        return Transaksi::create(array_merge([
            'ikan_id' => $ikan->id,
            'pemenang_id' => $buyer->id,
            'harga_final' => 150_000,
            'status' => 'menunggu_bayar',
            'fulfillment_state' => null,
            'state_version' => 0,
            'escrow_status' => 'belum',
            'escrow_amount' => 0,
            'delivery_status' => 'menunggu_pengiriman',
            'delivery_cost' => 0,
            'bayar_sebelum' => now()->addHours(24),
            'midtrans_order_id' => 'BORGFISH-FULFILLMENT-TEMP',
        ], $overrides));
    }
}
