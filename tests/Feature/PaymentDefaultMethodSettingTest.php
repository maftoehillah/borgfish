<?php

namespace Tests\Feature;

use App\Models\Ikan;
use App\Models\SystemSetting;
use App\Models\Transaksi;
use App\Models\User;
use App\Services\PaymentGateway\PaymentGatewayInterface;
use App\Services\PembayaranService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentDefaultMethodSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_service_uses_default_method_from_system_setting(): void
    {
        config([
            'tripay.default_method' => 'QRIS',
            'tripay.methods' => [
                'QRIS' => 'QRIS',
                'BRIVA' => 'BRI Virtual Account',
                'BCAVA' => 'BCA Virtual Account',
            ],
        ]);

        SystemSetting::query()->updateOrCreate(
            ['key' => 'default_payment_method'],
            [
                'group' => 'payment',
                'value' => 'BCAVA',
                'type' => 'string',
            ]
        );

        $service = app(PembayaranService::class);

        $this->assertSame('BCAVA', $service->defaultMethod());
        $this->assertSame('BCAVA', $service->resolvePaymentMethod(null));
        $this->assertSame('BRIVA', $service->resolvePaymentMethod('briva'));
    }

    public function test_payment_page_checks_configured_default_method(): void
    {
        config([
            'tripay.default_method' => 'QRIS',
            'tripay.methods' => [
                'QRIS' => 'QRIS',
                'BRIVA' => 'BRI Virtual Account',
                'BCAVA' => 'BCA Virtual Account',
            ],
        ]);

        SystemSetting::query()->updateOrCreate(
            ['key' => 'default_payment_method'],
            [
                'group' => 'payment',
                'value' => 'BCAVA',
                'type' => 'string',
            ]
        );

        [$buyer, , $transaksi] = $this->makePendingTransaction();

        $response = $this
            ->actingAs($buyer)
            ->withSession(['otp_verified_user_id' => $buyer->id])
            ->get(route('pembayaran.show', $transaksi));

        $response->assertOk();
        $response->assertSeeInOrder([
            'name="payment_method"',
            'value="BCAVA"',
            'checked',
        ], false);
    }

    public function test_payment_attempt_falls_back_to_configured_default_method(): void
    {
        config([
            'tripay.default_method' => 'QRIS',
            'tripay.methods' => [
                'QRIS' => 'QRIS',
                'BRIVA' => 'BRI Virtual Account',
                'BCAVA' => 'BCA Virtual Account',
            ],
        ]);

        SystemSetting::query()->updateOrCreate(
            ['key' => 'default_payment_method'],
            [
                'group' => 'payment',
                'value' => 'BCAVA',
                'type' => 'string',
            ]
        );

        $gateway = new class implements PaymentGatewayInterface {
            public ?array $lastPayload = null;

            public function name(): string
            {
                return 'tripay';
            }

            public function availableMethods(): array
            {
                return config('tripay.methods', []);
            }

            public function createPayment(array $payload): array
            {
                $this->lastPayload = $payload;

                return [
                    'provider_transaction_id' => 'TRIPAY-TEST-001',
                    'merchant_ref' => $payload['merchant_ref'],
                    'checkout_url' => 'https://tripay.test/checkout/TRIPAY-TEST-001',
                    'payment_method_code' => $payload['method'],
                    'payment_method_name' => $this->availableMethods()[$payload['method']] ?? $payload['method'],
                    'provider_status' => 'UNPAID',
                    'expires_at' => now()->addMinutes(30),
                    'request_payload' => $payload,
                ];
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
                return [];
            }
        };

        $this->app->instance(PaymentGatewayInterface::class, $gateway);

        [$buyer, , $transaksi] = $this->makePendingTransaction();

        $response = $this
            ->actingAs($buyer)
            ->withSession(['otp_verified_user_id' => $buyer->id])
            ->postJson(route('pembayaran.attempt', $transaksi));

        $response
            ->assertOk()
            ->assertJsonPath('payment_method_code', 'BCAVA');

        $this->assertSame('BCAVA', $gateway->lastPayload['method'] ?? null);
    }

    private function makePendingTransaction(): array
    {
        $buyer = User::factory()->create(['role' => 'pembeli']);
        $seller = User::factory()->create(['role' => 'penjual']);

        $ikan = Ikan::create([
            'user_id' => $seller->id,
            'nama_ikan' => 'Lot Payment Default',
            'berat' => 10,
            'estimasi_jumlah_ekor' => 20,
            'jenis_kemasan' => 'keranjang',
            'kondisi' => 'segar',
            'deskripsi' => 'Lot untuk test default pembayaran',
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

        return [$buyer, $seller, $transaksi];
    }
}
