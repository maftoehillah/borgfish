<?php

namespace Tests\Feature;

use App\Models\Ikan;
use App\Models\PaymentAttempt;
use App\Models\Transaksi;
use App\Models\User;
use App\Services\PaymentGateway\PaymentGatewayInterface;
use App\Services\PembayaranService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_payment_can_be_reconciled_to_paid(): void
    {
        config(['tripay.reconcile_pending_after_seconds' => 0]);

        $gateway = new class implements PaymentGatewayInterface {
            public function name(): string
            {
                return 'tripay';
            }

            public function availableMethods(): array
            {
                return ['QRIS' => 'QRIS'];
            }

            public function createPayment(array $payload): array
            {
                return [];
            }

            public function verifyCallback(string $rawBody, array $headers): bool
            {
                return true;
            }

            public function parseCallback(string $rawBody, array $headers): array
            {
                return [];
            }

            public function fetchPayment(string $providerTransactionId): array
            {
                return [
                    'provider_transaction_id' => $providerTransactionId,
                    'merchant_ref' => 'PAY-TEST-001',
                    'provider_status' => 'PAID',
                    'status_code' => 'paid',
                    'payment_method_code' => 'QRIS',
                    'payment_method_name' => 'QRIS',
                    'checkout_url' => 'https://tripay.test/checkout/paid',
                    'paid_at' => now(),
                    'payload' => ['reference' => $providerTransactionId, 'status' => 'PAID'],
                ];
            }
        };

        $this->app->instance(PaymentGatewayInterface::class, $gateway);

        [$transaksi, $payment] = $this->makePendingPaymentAttempt();

        $result = app(PembayaranService::class)->refreshPaymentAttempt($payment);

        $this->assertSame('ok', $result['status']);
        $this->assertFalse($result['idempotent']);
        $this->assertSame('paid', $payment->fresh()->status_code);
        $this->assertSame('paid', $transaksi->fresh()->payment_status);
        $this->assertSame('lunas', $transaksi->fresh()->status);
    }

    private function makePendingPaymentAttempt(): array
    {
        $buyer = User::factory()->create(['role' => 'pembeli']);
        $seller = User::factory()->create(['role' => 'penjual']);

        $ikan = Ikan::create([
            'user_id' => $seller->id,
            'nama_ikan' => 'Lot Reconcile',
            'berat' => 10,
            'estimasi_jumlah_ekor' => 20,
            'jenis_kemasan' => 'keranjang',
            'kondisi' => 'segar',
            'deskripsi' => 'Lot untuk test rekonsiliasi payment',
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
            'waktu_selesai' => now()->subMinutes(5),
            'status' => 'selesai',
            'auction_state' => 'MENUNGGU_PEMBAYARAN',
            'state_version' => 1,
        ]);

        $transaksi = Transaksi::create([
            'ikan_id' => $ikan->id,
            'pemenang_id' => $buyer->id,
            'winner_rank' => 1,
            'harga_final' => 125000,
            'status' => 'menunggu_bayar',
            'payment_status' => 'pending',
            'bayar_sebelum' => now()->addMinutes(30),
            'pickup_status' => 'waiting_payment',
        ]);

        $payment = PaymentAttempt::create([
            'payment_code' => 'PAY-TEST-001',
            'provider' => 'tripay',
            'status_code' => 'pending',
            'ikan_id' => $ikan->id,
            'transaksi_id' => $transaksi->id,
            'bidder_id' => $buyer->id,
            'rank' => 1,
            'amount_due' => 125000,
            'status' => 'menunggu_pembayaran',
            'provider_transaction_id' => 'TRIPAY-REF-001',
            'provider_status' => 'UNPAID',
            'payment_method_code' => 'QRIS',
            'payment_method_name' => 'QRIS',
            'checkout_url' => 'https://tripay.test/checkout/TRIPAY-REF-001',
            'checkout_expires_at' => now()->addMinutes(30),
            'bayar_sebelum' => now()->addMinutes(30),
            'assigned_at' => now()->subMinutes(2),
            'idempotency_key' => 'payment-idempotency-test',
        ]);

        return [$transaksi, $payment];
    }
}
