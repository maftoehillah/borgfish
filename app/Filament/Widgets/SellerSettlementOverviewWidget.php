<?php

namespace App\Filament\Widgets;

use App\Models\SellerSettlement;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SellerSettlementOverviewWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 5;

    protected ?string $pollingInterval = '15s';

    protected function getStats(): array
    {
        $pendingCount = SellerSettlement::where('status', 'pending')->count();
        $readyQuery = SellerSettlement::where('status', 'ready_to_pay');
        $heldCount = SellerSettlement::where('status', 'held')->count();
        $paidQuery = SellerSettlement::where('status', 'paid');

        return [
            Stat::make('Settlement Pending', $pendingCount)
                ->description('Menunggu review admin')
                ->color('warning'),

            Stat::make(
                'Siap Dibayar',
                'Rp ' . number_format((float) $readyQuery->sum('amount'), 0, ',', '.')
            )
                ->description($readyQuery->count() . ' settlement siap transfer')
                ->color('info'),

            Stat::make('Settlement Ditahan', $heldCount)
                ->description('Perlu follow-up dispute/data rekening')
                ->color('danger'),

            Stat::make(
                'Settlement Dibayar',
                'Rp ' . number_format((float) $paidQuery->sum('amount'), 0, ',', '.')
            )
                ->description($paidQuery->count() . ' settlement selesai')
                ->color('success'),
        ];
    }
}
