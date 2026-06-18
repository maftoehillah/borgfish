<?php

namespace App\Filament\Resources\SellerSettlementBatchResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SettlementsRelationManager extends RelationManager
{
    protected static string $relationship = 'settlements';

    protected static ?string $title = 'Isi Settlement Dalam Batch';

    public function table(Table $table): Table
    {
        return $table
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
                    ->formatStateUsing(fn ($state): string => formatRupiah((float) $state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('bank_name')
                    ->label('Bank')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('bank_account_number')
                    ->label('No. Rekening')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('bank_account_name')
                    ->label('Atas Nama')
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'ready_to_pay',
                        'danger' => 'held',
                        'success' => 'paid',
                        'gray' => 'cancelled',
                    ]),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Dibayar')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-')
                    ->sortable(),
            ])
            ->defaultSort('id')
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
