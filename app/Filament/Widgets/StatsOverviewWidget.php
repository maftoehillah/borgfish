<?php

namespace App\Filament\Widgets;

use App\Models\Bid;
use App\Models\Ikan;
use App\Models\Transaksi;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '10s';

    protected function getStats(): array
    {
        return [
            Stat::make('Total Pengguna', User::where('is_admin', false)->count())
                ->description(
                    User::where('is_admin', false)->where('role', 'penjual')->count()
                    . ' penjual | '
                    . User::where('is_admin', false)->where('role', 'pembeli')->count()
                    . ' pembeli'
                )
                ->color('info'),

            Stat::make('Lelang Aktif', Ikan::where('status', 'aktif')->count())
                ->description('dari ' . Ikan::count() . ' total ikan')
                ->color('success'),

            Stat::make('Bid Hari Ini', Bid::whereDate('created_at', today())->count())
                ->description('Total: ' . Bid::count() . ' bid')
                ->color('warning'),

            Stat::make('Bid Anomali', Bid::where('is_suspicious', true)->count())
                ->description('Perlu review anti-shill')
                ->color('danger'),

            Stat::make(
                'Pendapatan Lunas',
                'Rp ' . number_format((float) Transaksi::where('payment_status', 'paid')->sum('harga_final'), 0, ',', '.')
            )
                ->description(Transaksi::where('payment_status', 'paid')->count() . ' transaksi terbayar')
                ->color('success'),

            Stat::make('Menunggu Bayar', Transaksi::where('payment_status', 'pending')->where('status', 'menunggu_bayar')->count())
                ->description('Deadline 30 menit aktif')
                ->color('info'),

            Stat::make(
                'Lelang Urgent',
                Ikan::where('status', 'aktif')
                    ->where('waktu_selesai', '>', now())
                    ->where('waktu_selesai', '<=', now()->copy()->addMinutes(30))
                    ->count()
            )
                ->description('Sisa waktu <= 30 menit')
                ->color('warning'),

            Stat::make(
                'Butuh Tindakan',
                Transaksi::query()
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
                                    ->where('dibayar_pada', '<=', now()->copy()->subHours(24));
                            })
                            ->orWhere(function (Builder $sub): void {
                                $sub->where('status', 'lunas')
                                    ->where('pickup_status', 'pickup_arrived')
                                    ->whereNotNull('pickup_verified_at')
                                    ->where('pickup_verified_at', '<=', now()->copy()->subDays(2));
                            });
                    })
                    ->count()
            )
                ->description('Pembayaran/packing/konfirmasi overdue')
                ->color('danger'),
        ];
    }
}
