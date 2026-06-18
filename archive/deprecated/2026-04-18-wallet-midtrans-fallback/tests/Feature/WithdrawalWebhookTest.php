<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\SellerWithdrawal;
use App\Models\Withdrawal;
use App\Models\Wallet;

class WithdrawalWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_payout_webhook_marks_withdrawal_and_seller_withdrawal_paid()
    {
        // ensure SIMULATION mode for deterministic behavior
        config(['wallet.mode' => 'SIMULATION']);

        // create a user and a seller withdrawal
        $user = User::factory()->create();

        $sellerWithdrawal = SellerWithdrawal::create([
            'user_id' => $user->id,
            'amount' => 100000,
            'status' => 'approved',
            'bank_name' => 'Bank Test',
            'account_number' => '1234567890',
            'account_holder_name' => 'Tester',
            'requested_at' => now(),
            'approved_at' => now(),
        ]);

        // mark seller's pending withdraw balance to simulate ledger reservation
        $user->seller_saldo_pending_withdrawal = 100000;
        $user->save();

        // create a wallet for the user because withdrawals reference wallets
        $wallet = Wallet::create(['user_id' => $user->id, 'currency' => 'IDR']);

        // create a generic external withdrawal that references the seller withdrawal
        $withdrawal = Withdrawal::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'amount' => 100000,
            'fee' => 0,
            'net_amount' => 100000,
            'status' => 'PAYOUT_INITIATED',
            'requested_at' => now(),
            'approved_at' => now(),
            'payout_provider' => config('wallet.gateway'),
            'payout_external_id' => 'sim-payout-'.uniqid(),
            'meta' => ['seller_withdrawal_id' => $sellerWithdrawal->id],
        ]);

        // craft a webhook payload consistent with gateway adapters (Midtrans/Xendit)
        $payload = [
            'status' => 'success',
            'id' => $withdrawal->payout_external_id,
        ];

        // Call controller handle() directly since route may not be registered in test
        $controller = app(\App\Http\Controllers\Webhook\PaymentWebhookController::class);
        $request = \Illuminate\Http\Request::create('/webhook/payment', 'POST', $payload);
        $response = $controller->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $withdrawal->refresh();
        $sellerWithdrawal->refresh();

        $this->assertEquals('PAID', $withdrawal->status);
        $this->assertTrue($sellerWithdrawal->isPaid());
    }
}
