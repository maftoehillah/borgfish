<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\SaldoTopupResource;
use App\Models\SaldoLedger;
use App\Models\SaldoTopup;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SaldoTopupOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '20s';

    protected function getStats(): array
    {
        $today = today();

        $pendingCount = SaldoTopup::query()
            ->where('status', 'pending')
            ->count();

        $pendingAmount = (float) SaldoTopup::query()
            ->where('status', 'pending')
            ->sum('amount');

        $needsReconciliationCount = SaldoTopup::query()
            ->where('status', 'success')
            ->whereDoesntHave('ledgerEntries')
            ->count();

        $successTodayCount = SaldoTopup::query()
            ->where('status', 'success')
            ->whereDate('paid_at', $today)
            ->count();

        $successTodayAmount = (float) SaldoTopup::query()
            ->where('status', 'success')
            ->whereDate('paid_at', $today)
            ->sum('amount');

        $ledgerTodayAmount = (float) SaldoLedger::query()
            ->where('entry_type', 'topup')
            ->whereDate('created_at', $today)
            ->sum('available_delta');

        $ledgerTodayCount = SaldoLedger::query()
            ->where('entry_type', 'topup')
            ->whereDate('created_at', $today)
            ->count();

        return [
            Stat::make('Top Up Pending', (string) $pendingCount)
                ->description('Nominal pending ' . formatRupiah($pendingAmount))
                ->color('warning')
                ->url(SaldoTopupResource::getUrl()),

            Stat::make('Butuh Rekonsiliasi', (string) $needsReconciliationCount)
                ->description('Status sukses tapi ledger belum masuk')
                ->color($needsReconciliationCount > 0 ? 'danger' : 'success')
                ->url(SaldoTopupResource::getUrl()),

            Stat::make('Top Up Berhasil Hari Ini', (string) $successTodayCount)
                ->description('Nominal ' . formatRupiah($successTodayAmount))
                ->color('success')
                ->url(SaldoTopupResource::getUrl()),

            Stat::make('Ledger Top Up Hari Ini', (string) $ledgerTodayCount)
                ->description('Mutasi masuk ' . formatRupiah($ledgerTodayAmount))
                ->color('info')
                ->url(SaldoTopupResource::getUrl()),
        ];
    }
}
