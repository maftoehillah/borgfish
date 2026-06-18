<?php

namespace App\Filament\Widgets;

use App\Models\Transaksi;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FulfillmentPipelineOverviewWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '15s';

    protected function getStats(): array
    {
        $baseLunas = Transaksi::query()
            ->where('status', 'lunas')
            ->where('payment_status', 'paid');

        $packingTotal = (clone $baseLunas)->count();
        $packingDone = (clone $baseLunas)->whereNotNull('packed_at')->count();
        $packingPending = max(0, $packingTotal - $packingDone);

        $pickupTotal = (clone $baseLunas)
            ->whereIn('pickup_status', ['awaiting_pickup', 'pickup_arrived'])
            ->count();

        $pickupVerified = (clone $baseLunas)
            ->where('pickup_status', 'pickup_arrived')
            ->whereNotNull('pickup_verified_at')
            ->count();

        $completedTotal = (clone $baseLunas)
            ->where('pickup_status', 'completed')
            ->count();

        return [
            Stat::make('Siapkan Packing', (string) $packingPending)
                ->description('Packing: ' . $packingDone . ' dari ' . $packingTotal)
                ->descriptionIcon($packingDone > 0 ? 'heroicon-m-check-badge' : 'heroicon-m-clock')
                ->color($packingPending > 0 ? 'warning' : 'success'),

            Stat::make('Penjemputan', (string) $pickupTotal)
                ->description('Terverifikasi: ' . $pickupVerified)
                ->descriptionIcon($pickupVerified > 0 ? 'heroicon-m-truck' : 'heroicon-m-archive-box')
                ->color($pickupTotal > 0 ? 'info' : 'gray'),

            Stat::make('Selesai', (string) $completedTotal)
                ->description('Konfirmasi buyer selesai')
                ->descriptionIcon($completedTotal > 0 ? 'heroicon-m-check-circle' : 'heroicon-m-map-pin')
                ->color($completedTotal > 0 ? 'success' : 'gray'),
        ];
    }
}
