<?php

namespace App\Console\Commands;

use App\Jobs\ReconcileWithdrawalsJob;
use Illuminate\Console\Command;

class ReconcileWithdrawals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'withdrawals:reconcile {--sync : Run reconciliation synchronously instead of dispatching to queue}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile pending provider payouts with local withdrawals';

    public function handle()
    {
        if ($this->option('sync')) {
            $this->info('Running reconciliation synchronously...');
            try {
                (new ReconcileWithdrawalsJob())->handle();
                $this->info('Reconciliation completed.');
                return 0;
            } catch (\Throwable $e) {
                $this->error('Reconciliation failed: ' . $e->getMessage());
                return 1;
            }
        }

        $this->info('Dispatching reconciliation job to queue...');
        ReconcileWithdrawalsJob::dispatch();
        $this->info('Job dispatched.');

        return 0;
    }
}
