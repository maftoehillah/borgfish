<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PaymentAttemptResource;
use App\Filament\Resources\SellerSettlementResource;
use App\Filament\Resources\TransactionDisputeResource;
use App\Filament\Resources\TransaksiResource;
use App\Models\NotificationOutbox;
use App\Models\PaymentAttempt;
use App\Models\SellerSettlement;
use App\Models\TransactionDispute;
use App\Models\Transaksi;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminHealthCheckWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $pendingPayments = PaymentAttempt::query()
            ->where('provider', 'tripay')
            ->where('status_code', 'pending')
            ->whereNotNull('provider_transaction_id')
            ->where('assigned_at', '<=', now()->subMinutes(15))
            ->count();

        $failedJobs = Schema::hasTable('failed_jobs')
            ? (int) DB::table('failed_jobs')->count()
            : 0;

        $failedOutbox = NotificationOutbox::query()
            ->where('status', 'failed')
            ->count();

        $oldDisputes = TransactionDispute::query()
            ->where('status', 'open')
            ->where('opened_at', '<=', now()->subDay())
            ->count();

        $heldSettlements = SellerSettlement::query()
            ->where('status', 'held')
            ->where('held_at', '<=', now()->subDays(3))
            ->count();

        $overdueTransactions = Transaksi::query()
            ->where(function (Builder $query): void {
                $query
                    ->where(function (Builder $sub): void {
                        $sub->where('status', 'menunggu_bayar')
                            ->whereNotNull('bayar_sebelum')
                            ->where('bayar_sebelum', '<', now());
                    })
                    ->orWhere(function (Builder $sub): void {
                        $sub->where('status', 'lunas')
                            ->where('payment_status', 'paid')
                            ->whereNull('packed_at')
                            ->whereNotNull('dibayar_pada')
                            ->where('dibayar_pada', '<=', now()->subHours(24));
                    })
                    ->orWhere(function (Builder $sub): void {
                        $sub->where('status', 'lunas')
                            ->where('pickup_status', 'pickup_arrived')
                            ->whereNotNull('pickup_verified_at')
                            ->where('pickup_verified_at', '<=', now()->subDays(2));
                    });
            })
            ->count();

        return [
            Stat::make('Payment Pending Lama', $pendingPayments)
                ->description('Pending TriPay lebih dari 15 menit')
                ->color($pendingPayments > 0 ? 'warning' : 'success')
                ->url(PaymentAttemptResource::getUrl('index')),
            Stat::make('Transaksi Overdue', $overdueTransactions)
                ->description('Bayar, packing, atau konfirmasi melewati SLA')
                ->color($overdueTransactions > 0 ? 'danger' : 'success')
                ->url(TransaksiResource::getUrl('index')),
            Stat::make('Sengketa Tua', $oldDisputes)
                ->description('Open lebih dari 24 jam')
                ->color($oldDisputes > 0 ? 'danger' : 'success')
                ->url(TransactionDisputeResource::getUrl('index')),
            Stat::make('Settlement Held Lama', $heldSettlements)
                ->description('Ditahan lebih dari 3 hari')
                ->color($heldSettlements > 0 ? 'warning' : 'success')
                ->url(SellerSettlementResource::getUrl('index')),
            Stat::make('Outbox Gagal', $failedOutbox)
                ->description('Notifikasi gagal permanen')
                ->color($failedOutbox > 0 ? 'warning' : 'success'),
            Stat::make('Job Gagal', $failedJobs)
                ->description('Baris failed_jobs')
                ->color($failedJobs > 0 ? 'danger' : 'success'),
        ];
    }
}
