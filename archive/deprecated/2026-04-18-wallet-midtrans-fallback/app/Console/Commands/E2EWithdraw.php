<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\WalletService;
use App\Services\WithdrawService;
use App\Jobs\ProcessPayoutJob;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class E2EWithdraw extends Command
{
    protected $signature = 'e2e:withdrawal {scenario=success} {--force : Override environment guard}';

    protected $description = 'Run E2E withdrawal flow on staging (uses configured sandbox gateway).';

    public function handle(): int
    {
        if (env('APP_ENV') !== 'staging' && ! $this->option('force')) {
            $this->error('This command should be run in a staging environment (APP_ENV=staging). Use --force to override.');
            return 2;
        }

        $scenario = $this->argument('scenario') ?: 'success';
        $this->info("E2E withdrawal run (scenario={$scenario})");

        // create or fetch test user
        $email = 'e2e-withdrawal@staging.local';
        $user = User::where('email', $email)->first();
        if (! $user) {
            $user = User::factory()->create([
                'email' => $email,
                'name' => 'E2E Withdraw User',
                'password' => Hash::make('password'),
                'role' => 'pembeli',
            ]);
            $this->info('Created test user: ' . $user->email);
        } else {
            $this->info('Using existing test user: ' . $user->email);
        }

        // ensure admin exists for approval
        $adminEmail = 'e2e-admin@staging.local';
        $admin = User::where('email', $adminEmail)->first();
        if (! $admin) {
            $admin = User::factory()->create([
                'email' => $adminEmail,
                'name' => 'E2E Admin',
                'password' => Hash::make('password'),
                'role' => 'superadmin',
                'is_admin' => true,
            ]);
            $this->info('Created test admin: ' . $admin->email);
        } else {
            $this->info('Using existing test admin: ' . $admin->email);
        }

        $ws = app(WalletService::class);
        $creditKey = 'e2e-credit:' . (string) Str::uuid();
        $creditAmount = 1000000;
        $this->info('Crediting wallet: ' . number_format($creditAmount));
        $ws->creditAvailable($user->id, $creditAmount, ['note' => 'E2E credit'], $creditKey);

        $withdrawSvc = app(WithdrawService::class);
        $withdrawKey = 'e2e-withdraw:' . (string) Str::uuid();
        $withdrawAmount = 500000;

        $this->info('Requesting withdrawal: ' . number_format($withdrawAmount));
        $withdrawal = $withdrawSvc->requestWithdraw($user->id, $withdrawAmount, $withdrawKey, []);

        $this->info('Approving withdrawal (will dispatch payout job if WALLET_MODE=REAL)');
        $withdrawSvc->approveWithdraw($withdrawal->id, $admin->id);

        // For deterministic run, attempt to process payout job synchronously here.
        try {
            $this->info('Running payout job handler synchronously (one-off).');
            (new ProcessPayoutJob($withdrawal->id))->handle();
        } catch (\Throwable $e) {
            $this->warn('Payout job execution raised: ' . $e->getMessage());
            $this->warn('If using a queue worker, ensure it is running and check logs.');
        }

        $this->info('Waiting for final status (PAID / FAILED)...');
        $timeout = 120; // seconds
        $start = time();
        $final = null;
        while (time() - $start < $timeout) {
            $withdrawal->refresh();
            if (in_array($withdrawal->status, ['PAID', 'FAILED'], true)) {
                $final = $withdrawal->status;
                break;
            }
            sleep(2);
        }

        if (! $final) {
            $this->warn('Withdrawal did not reach final state within timeout. Current status: ' . $withdrawal->status);
        } else {
            $this->info('Withdrawal finished with status: ' . $final);
            $this->info('Payout external id: ' . ($withdrawal->payout_external_id ?? 'n/a'));
        }

        $this->info('Wallet balances:');
        $wallet = $withdrawal->wallet()->first();
        if ($wallet) {
            $this->info(' available: ' . (string) $wallet->balance_available . ' pending: ' . (string) $wallet->balance_pending);
        }

        $outboxKey = 'withdrawal:paid:' . $withdrawal->id;
        $out = \App\Models\NotificationOutbox::where('idempotency_key', $outboxKey)->first();
        if ($out) {
            $this->info('Outbox entry: ' . $out->status . ' (id=' . $out->id . ')');
        } else {
            $this->info('No outbox entry found for idempotency_key=' . $outboxKey);
        }

        $this->info('E2E run complete. Check application logs, provider dashboard, and webhooks if needed.');

        return 0;
    }
}
