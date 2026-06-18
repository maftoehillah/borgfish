<?php

namespace Tests\Feature;

use App\Models\Ikan;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('midtrans.server_key', 'SB-Mid-server-valid-12345');
        config()->set('midtrans.client_key', 'SB-Mid-client-valid-12345');
        config()->set('midtrans.is_production', false);
    }

    public function test_webhook_settlement_marks_transaction_as_lunas_and_locks_escrow(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli');

        $ikan = $this->makeLot($seller, [
            'status' => 'selesai',
        ]);

        $transaksi = $this->makeTransaksi($ikan, $buyer, [
            'status' => 'menunggu_bayar',
            'escrow_status' => 'belum',
            'delivery_status' => 'menunggu_pengiriman',
            'midtrans_order_id' => 'BORGFISH-WEBHOOK-001',
        ]);

        $grossAmount = number_format($transaksi->totalTagihan(), 2, '.', '');
        $payload = $this->webhookPayload(
            $transaksi->midtrans_order_id,
            'settlement',
            '200',
            $grossAmount,
            'bank_transfer'
        );

        $response = $this->postJson(route('midtrans.webhook'), $payload);

        $response->assertOk()->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('transaksis', [
            'id' => $transaksi->id,
            'status' => 'lunas',
            'escrow_status' => 'ditahan',
            'delivery_status' => 'diproses',
            'metode_pembayaran' => 'bank_transfer',
        ]);

        $this->assertDatabaseHas('ikans', [
            'id' => $ikan->id,
            'status' => 'terbayar',
        ]);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli');

        $ikan = $this->makeLot($seller);
        $transaksi = $this->makeTransaksi($ikan, $buyer, [
            'status' => 'menunggu_bayar',
            'escrow_status' => 'belum',
            'midtrans_order_id' => 'BORGFISH-WEBHOOK-002',
        ]);

        $grossAmount = number_format($transaksi->totalTagihan(), 2, '.', '');
        $payload = $this->webhookPayload(
            $transaksi->midtrans_order_id,
            'settlement',
            '200',
            $grossAmount,
            'bank_transfer'
        );
        $payload['signature_key'] = 'invalid-signature';

        $response = $this->postJson(route('midtrans.webhook'), $payload);

        $response->assertStatus(422);
        $response->assertJsonStructure(['error']);

        $this->assertDatabaseHas('transaksis', [
            'id' => $transaksi->id,
            'status' => 'menunggu_bayar',
            'escrow_status' => 'belum',
        ]);
    }

    public function test_pending_webhook_does_not_downgrade_lunas_status(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli');

        $ikan = $this->makeLot($seller, [
            'status' => 'terbayar',
        ]);
        $transaksi = $this->makeTransaksi($ikan, $buyer, [
            'status' => 'lunas',
            'escrow_status' => 'ditahan',
            'delivery_status' => 'diproses',
            'midtrans_order_id' => 'BORGFISH-WEBHOOK-003',
        ]);

        $grossAmount = number_format($transaksi->totalTagihan(), 2, '.', '');
        $payload = $this->webhookPayload(
            $transaksi->midtrans_order_id,
            'pending',
            '201',
            $grossAmount,
            'bank_transfer'
        );

        $response = $this->postJson(route('midtrans.webhook'), $payload);

        $response->assertOk()->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('transaksis', [
            'id' => $transaksi->id,
            'status' => 'lunas',
            'escrow_status' => 'ditahan',
        ]);
    }

    private function webhookPayload(
        string $orderId,
        string $transactionStatus,
        string $statusCode,
        string $grossAmount,
        ?string $paymentType = null
    ): array {
        $payload = [
            'order_id' => $orderId,
            'transaction_status' => $transactionStatus,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $this->sign($orderId, $statusCode, $grossAmount),
        ];

        if ($paymentType !== null) {
            $payload['payment_type'] = $paymentType;
        }

        return $payload;
    }

    private function sign(string $orderId, string $statusCode, string $grossAmount): string
    {
        return hash('sha512', $orderId.$statusCode.$grossAmount.(string) config('midtrans.server_key'));
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
            'nama_ikan' => 'Patin Uji',
            'berat' => 10,
            'estimasi_jumlah_ekor' => 22,
            'jenis_kemasan' => 'keranjang',
            'kondisi' => 'segar',
            'deskripsi' => 'Lot untuk test webhook Midtrans',
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
            'escrow_status' => 'belum',
            'escrow_amount' => 0,
            'delivery_status' => 'menunggu_pengiriman',
            'delivery_cost' => 0,
            'bayar_sebelum' => now()->addHours(24),
            'midtrans_order_id' => 'BORGFISH-TEMP-ORDER',
        ], $overrides));
    }
}
