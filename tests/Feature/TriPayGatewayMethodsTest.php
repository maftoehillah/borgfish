<?php

namespace Tests\Feature;

use App\Services\PaymentGateway\TriPayGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TriPayGatewayMethodsTest extends TestCase
{
    use RefreshDatabase;

    public function test_available_methods_use_active_channels_from_tripay_when_sync_enabled(): void
    {
        Cache::flush();

        config([
            'tripay.sync_methods' => true,
            'tripay.api_key' => 'tripay-api-key',
            'tripay.environment' => 'sandbox',
            'tripay.sandbox_base_url' => 'https://tripay.test/api-sandbox',
            'tripay.methods' => [
                'QRIS' => 'QRIS',
                'BRIVA' => 'BRI Virtual Account',
            ],
        ]);

        Http::fake([
            'tripay.test/api-sandbox/merchant/payment-channel' => Http::response([
                'success' => true,
                'data' => [
                    ['code' => 'QRIS', 'name' => 'QRIS', 'active' => true],
                    ['code' => 'BCAVA', 'name' => 'BCA Virtual Account', 'active' => true],
                    ['code' => 'OVO', 'name' => 'OVO', 'active' => false],
                ],
            ], 200),
        ]);

        $gateway = app(TriPayGateway::class);

        $this->assertSame([
            'QRIS' => 'QRIS',
            'BCAVA' => 'BCA Virtual Account',
        ], $gateway->availableMethods());
    }
}
