<?php

namespace Tests\Feature;

use App\Jobs\ProcessPayoutJob;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Services\WalletService;
use App\Services\NotificationOutboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class OutboxWithdrawalProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_outbox_processed_after_payout_and_inapp_notification_created()
    {
        config(['wallet.mode' => 'REAL']);

        $user = \App\Models\User::factory()->create();

        $wallet = Wallet::create([
            'user_id' => $user->id,
            'balance_available' => 200000,
            'balance_pending' => 0,
            'currency' => 'IDR',
        ]);

        $ws = app(WalletService::class);
        $idemp = (string) \Illuminate\Support\Str::uuid();
        $ws->reserveForWithdraw($user->id, 60000, $idemp, ['note' => 'test reserve']);

        $withdrawal = Withdrawal::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'amount' => 60000,
            'fee' => 0,
            'net_amount' => 60000,
            'status' => 'APPROVED',
            'requested_at' => now(),
            'approved_at' => now(),
            'idempotency_key' => $idemp,
            'meta' => [],
        ]);

        $mock = Mockery::mock(\App\Services\PaymentGateway\PaymentGatewayInterface::class);
        $mock->shouldReceive('payout')->once()->andReturn(['id' => 'payout-ext-1', 'status' => 'PAID']);
        $this->app->instance(\App\Services\PaymentGateway\PaymentGatewayInterface::class, $mock);

        (new ProcessPayoutJob($withdrawal->id))->handle();

        $this->assertDatabaseHas('notification_outbox', [
            'idempotency_key' => 'withdrawal:paid:' . $withdrawal->id,
            'status' => 'pending',
        ]);

        $processed = app(NotificationOutboxService::class)->processPending(50);
        $this->assertGreaterThanOrEqual(1, $processed);

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $user->id,
            'category' => 'saldo',
            'title' => 'Withdraw sudah dibayar',
        ]);

        $this->assertDatabaseHas('notification_outbox', [
            'idempotency_key' => 'withdrawal:paid:' . $withdrawal->id,
            'status' => 'sent',
        ]);
    }
}
