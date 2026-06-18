<?php

namespace App\Console\Commands;

use App\Services\SellerSettlementService;
use Illuminate\Console\Command;

class PromoteReadySellerSettlementsCommand extends Command
{
    protected $signature = 'settlements:promote-ready {--limit=100 : Maksimum settlement pending yang dicek}';

    protected $description = 'Ubah settlement pending yang delay pencairannya sudah selesai menjadi siap dibayar.';

    public function handle(SellerSettlementService $service): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $promoted = $service->promoteReadySettlements($limit);

        $this->info("Promosi settlement selesai. Siap dibayar: {$promoted}.");

        return self::SUCCESS;
    }
}
