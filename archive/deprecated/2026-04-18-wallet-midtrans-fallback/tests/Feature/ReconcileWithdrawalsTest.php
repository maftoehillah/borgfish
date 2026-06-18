<?php

namespace Tests\Feature;

use App\Jobs\ReconcileWithdrawalsJob;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ReconcileWithdrawalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconcile_finalizes_paid_withdrawal_and_creates_outbox()
    {
        config(['wallet.mode' => 'REAL']);

        $user = $this->makeUser('pembeli');

        $wallet = Wallet::create([
            'user_id' => $user->id,
            'balance_available' => 200000,
            'balance_pending' => 0,
            'currency' => 'IDR',
        ]);

        $ws = app(WalletService::class);
        $idemp = 'test-reserve-' . uniqid();
        $ws->reserveForWithdraw($user->id, 50000, $idemp, ['note' => 'test reserve']);

        $withdrawal = Withdrawal::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'amount' => 50000,
            'fee' => 0,
            'net_amount' => 50000,
            'status' => 'PAYOUT_INITIATED',
            'requested_at' => now(),
            'approved_at' => now(),
            'payout_external_id' => 'ext-123',
            'idempotency_key' => $idemp,
            'meta' => [],
        ]);

        $mock = Mockery::mock(\App\Services\PaymentGateway\PaymentGatewayInterface::class);
        $mock->shouldReceive('fetchPayoutStatus')->once()->with('ext-123')->andReturn(['id' => 'ext-123', 'status' => 'PAID']);
        $this->app->instance(\App\Services\PaymentGateway\PaymentGatewayInterface::class, $mock);

        $job = new ReconcileWithdrawalsJob();
        $job->handle();

        $withdrawal->refresh();
        $this->assertEquals('PAID', $withdrawal->status);

        $wallet->refresh();
        $this->assertEquals(0, (int) $wallet->balance_pending);

        $this->assertDatabaseHas('notification_outbox', [
            'idempotency_key' => 'withdrawal:paid:' . $withdrawal->id,
        ]);
    }

    private function makeUser(string $role)
    {
        return \App\Models\User::factory()->create(['role' => $role]);
    }
}
