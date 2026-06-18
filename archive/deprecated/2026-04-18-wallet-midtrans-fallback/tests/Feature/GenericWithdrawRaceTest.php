<?php

namespace Tests\Feature;

use App\Jobs\ProcessPayoutJob;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class GenericWithdrawRaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_process_payout_job_called_only_once_when_run_twice()
    {
        config(['wallet.mode' => 'REAL']);

        $user = User::factory()->create();

        $wallet = Wallet::create([
            'user_id' => $user->id,
            'balance_available' => 200000,
            'balance_pending' => 0,
            'currency' => 'IDR',
        ]);

        // reserve funds
        $ws = app(WalletService::class);
        $idemp = (string) Str::uuid();
        $ws->reserveForWithdraw($user->id, 100000, $idemp, ['note' => 'test reserve']);

        $withdrawal = Withdrawal::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'amount' => 100000,
            'fee' => 0,
            'net_amount' => 100000,
            'status' => 'APPROVED',
            'requested_at' => now(),
            'approved_at' => now(),
            'idempotency_key' => $idemp,
            'meta' => [],
        ]);

        // Mock the gateway and ensure payout() is only called once
        $mock = Mockery::mock(\App\Services\PaymentGateway\PaymentGatewayInterface::class);
        $mock->shouldReceive('payout')->once()->andReturn(['id' => 'sim-payout-1', 'status' => 'initiated']);
        $this->app->instance(\App\Services\PaymentGateway\PaymentGatewayInterface::class, $mock);

        // Run the job twice (simulate duplicate job execution)
        $job1 = new ProcessPayoutJob($withdrawal->id);
        $job1->handle();

        $job2 = new ProcessPayoutJob($withdrawal->id);
        $job2->handle();

        $withdrawal->refresh();

        $this->assertEquals('PAYOUT_INITIATED', $withdrawal->status);
        $this->assertDatabaseHas('withdrawals', [
            'id' => $withdrawal->id,
            'payout_external_id' => 'sim-payout-1',
            'status' => 'PAYOUT_INITIATED',
        ]);
    }
}
