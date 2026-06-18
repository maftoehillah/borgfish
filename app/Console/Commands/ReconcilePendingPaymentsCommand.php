<?php

namespace App\Console\Commands;

use App\Models\PaymentAttempt;
use App\Services\PembayaranService;
use Illuminate\Console\Command;

class ReconcilePendingPaymentsCommand extends Command
{
    protected $signature = 'payments:reconcile-pending {--limit=50 : Maksimum invoice pending yang diperiksa}';

    protected $description = 'Sinkronkan invoice pending Borgfish dengan status terbaru dari TriPay.';

    public function handle(PembayaranService $service): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $payments = PaymentAttempt::query()
            ->where('provider', 'tripay')
            ->where('status_code', 'pending')
            ->whereNotNull('provider_transaction_id')
            ->orderBy('assigned_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $processed = 0;
        $changed = 0;
        $skipped = 0;

        foreach ($payments as $payment) {
            try {
                $result = $service->refreshPaymentAttempt($payment);
                $processed++;

                if (($result['status'] ?? null) === 'skipped') {
                    $skipped++;
                    continue;
                }

                if (($result['idempotent'] ?? true) === false) {
                    $changed++;
                }
            } catch (\Throwable $e) {
                report($e);
                $this->warn("Gagal sinkron payment {$payment->payment_code}: {$e->getMessage()}");
            }
        }

        $this->info("Rekonsiliasi selesai. Diproses: {$processed}, berubah: {$changed}, dilewati: {$skipped}.");

        return self::SUCCESS;
    }
}
