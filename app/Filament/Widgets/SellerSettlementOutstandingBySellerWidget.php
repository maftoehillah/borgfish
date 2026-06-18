<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class SellerSettlementOutstandingBySellerWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Outstanding Settlement per Seller';

    public function table(Table $table): Table
    {
        return $table
            ->poll('30s')
            ->query(
                User::query()
                    ->whereHas('sellerSettlements', function ($query): void {
                        $query->whereIn('status', ['pending', 'ready_to_pay', 'held']);
                    })
                    ->withCount([
                        'sellerSettlements as outstanding_settlement_count' => function ($query): void {
                            $query->whereIn('status', ['pending', 'ready_to_pay', 'held']);
                        },
                    ])
                    ->withSum([
                        'sellerSettlements as outstanding_settlement_amount' => function ($query): void {
                            $query->whereIn('status', ['pending', 'ready_to_pay', 'held']);
                        },
                    ], 'amount')
                    ->orderByDesc('outstanding_settlement_amount')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Seller')
                    ->searchable(),
                Tables\Columns\TextColumn::make('whatsapp')
                    ->label('WhatsApp')
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('outstanding_settlement_count')
                    ->label('Jumlah Settlement')
                    ->badge()
                    ->color('warning'),
                Tables\Columns\TextColumn::make('outstanding_settlement_amount')
                    ->label('Nominal Outstanding')
                    ->formatStateUsing(fn ($state): string => formatRupiah((float) $state))
                    ->sortable(),
            ])
            ->defaultSort('outstanding_settlement_amount', 'desc')
            ->emptyStateHeading('Belum ada outstanding settlement.')
            ->emptyStateDescription('Seller dengan settlement pending, siap dibayar, atau ditahan akan muncul di sini.');
    }
}
