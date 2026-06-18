<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\SellerSettlementResource;
use App\Models\SellerSettlement;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class SellerSettlementActionRequiredWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Settlement Seller Perlu Tindakan';

    public function table(Table $table): Table
    {
        return $table
            ->poll('15s')
            ->query(
                SellerSettlement::query()
                    ->with(['transaksi.ikan', 'seller'])
                    ->whereIn('status', ['pending', 'ready_to_pay', 'held'])
                    ->latest('updated_at')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('transaksi.order_code')
                    ->label('Order ID')
                    ->searchable(),
                Tables\Columns\TextColumn::make('transaksi.ikan.nama_ikan')
                    ->label('Lot')
                    ->searchable(),
                Tables\Columns\TextColumn::make('seller.name')
                    ->label('Seller')
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Nominal')
                    ->formatStateUsing(fn ($state): string => formatRupiah($state))
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'ready_to_pay',
                        'danger' => 'held',
                    ]),
                Tables\Columns\TextColumn::make('hold_reason')
                    ->label('Catatan')
                    ->limit(40)
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Update Terakhir')
                    ->since(),
            ])
            ->recordUrl(fn (SellerSettlement $record): string => SellerSettlementResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Tidak ada settlement yang butuh tindakan.')
            ->emptyStateDescription('Settlement pending, siap dibayar, atau ditahan akan muncul di sini.');
    }
}
