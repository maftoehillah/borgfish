<?php

namespace App\Console\Commands;

use App\Models\Transaksi;
use App\Services\SellerSettlementService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class BackfillCompletedSellerSettlementsCommand extends Command
{
    protected $signature = 'settlements:backfill-completed
        {--limit=100 : Maksimum transaksi selesai yang diperiksa}
        {--dry-run : Tampilkan hasil tanpa membuat settlement}';

    protected $description = 'Buat settlement seller untuk transaksi lama yang sudah selesai tetapi belum punya settlement.';

    public function handle(SellerSettlementService $service): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $transaksis = Transaksi::query()
            ->with(['ikan.user.sellerProfile', 'sellerSettlement'])
            ->whereDoesntHave('sellerSettlement')
            ->where(function (Builder $query): void {
                $query->whereNotNull('completed_by_buyer_at')
                    ->orWhere('pickup_status', 'completed')
                    ->orWhere('fulfillment_state', 'SELESAI');
            })
            ->orderByRaw('COALESCE(completed_at, completed_by_buyer_at, updated_at) ASC')
            ->limit($limit)
            ->get();

        $processed = 0;
        $created = 0;
        $skipped = 0;

        foreach ($transaksis as $transaksi) {
            $processed++;

            if ($dryRun) {
                if (! $service->canAutoCreateForCompletedTransaction($transaksi)) {
                    $skipped++;
                    continue;
                }

                $created++;
                continue;
            }

            $settlement = $service->ensureAutoCreatedForCompletedTransaction($transaksi, null);

            if ($settlement) {
                $created++;
            } else {
                $skipped++;
            }
        }

        $modeLabel = $dryRun ? 'Dry-run selesai' : 'Backfill selesai';
        $this->info("{$modeLabel}. Diproses: {$processed}, dibuat: {$created}, dilewati: {$skipped}.");

        return self::SUCCESS;
    }
}
