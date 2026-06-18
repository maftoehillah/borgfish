<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class SellerSettlementPaidBySellerThisMonthWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 8;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Payout Seller Bulan Ini';

    public function table(Table $table): Table
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        return $table
            ->poll('30s')
            ->query(
                User::query()
                    ->whereHas('sellerSettlements', function ($query) use ($startOfMonth, $endOfMonth): void {
                        $query
                            ->where('status', 'paid')
                            ->whereBetween('paid_at', [$startOfMonth, $endOfMonth]);
                    })
                    ->withCount([
                        'sellerSettlements as paid_this_month_count' => function ($query) use ($startOfMonth, $endOfMonth): void {
                            $query
                                ->where('status', 'paid')
                                ->whereBetween('paid_at', [$startOfMonth, $endOfMonth]);
                        },
                    ])
                    ->withSum([
                        'sellerSettlements as paid_this_month_amount' => function ($query) use ($startOfMonth, $endOfMonth): void {
                            $query
                                ->where('status', 'paid')
                                ->whereBetween('paid_at', [$startOfMonth, $endOfMonth]);
                        },
                    ], 'amount')
                    ->orderByDesc('paid_this_month_amount')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Seller')
                    ->searchable(),
                Tables\Columns\TextColumn::make('whatsapp_number')
                    ->label('WhatsApp')
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('paid_this_month_count')
                    ->label('Settlement Dibayar')
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('paid_this_month_amount')
                    ->label('Nominal Dibayar')
                    ->formatStateUsing(fn ($state): string => formatRupiah((float) $state))
                    ->sortable(),
            ])
            ->defaultSort('paid_this_month_amount', 'desc')
            ->emptyStateHeading('Belum ada payout seller bulan ini.')
            ->emptyStateDescription('Seller dengan settlement berstatus paid pada bulan berjalan akan muncul di sini.');
    }
}
