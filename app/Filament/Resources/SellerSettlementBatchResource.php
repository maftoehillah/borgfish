<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SellerSettlementBatchResource\Pages;
use App\Filament\Resources\SellerSettlementBatchResource\RelationManagers\SettlementsRelationManager;
use App\Models\SellerSettlementBatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class SellerSettlementBatchResource extends Resource
{
    protected static ?string $model = SellerSettlementBatch::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static string|UnitEnum|null $navigationGroup = 'Transaksi';

    protected static ?string $modelLabel = 'Batch Settlement Seller';

    protected static ?string $pluralModelLabel = 'Batch Settlement Seller';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->canAdmin('finance');
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('batch_number')->label('Nomor Batch')->disabled(),
                TextInput::make('status')->label('Status')->disabled(),
                TextInput::make('total_amount')
                    ->label('Total Nominal')
                    ->formatStateUsing(fn ($state): string => formatRupiah((float) $state))
                    ->disabled(),
                TextInput::make('settlement_count')->label('Jumlah Settlement')->disabled(),
                TextInput::make('transfer_reference')->label('Referensi Transfer')->disabled(),
                TextInput::make('transfer_proof_path')->label('Bukti Transfer')->disabled(),
                Textarea::make('admin_note')->label('Catatan Admin')->disabled()->rows(4)->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('batch_number')->label('Nomor Batch')->searchable()->sortable(),
                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'paid',
                    ]),
                TextColumn::make('total_amount')
                    ->label('Total Nominal')
                    ->formatStateUsing(fn ($state): string => formatRupiah((float) $state))
                    ->sortable(),
                TextColumn::make('settlement_count')->label('Jumlah Settlement')->sortable(),
                TextColumn::make('transfer_reference')->label('Ref Transfer')->placeholder('-')->toggleable(),
                TextColumn::make('creator.name')->label('Dibuat Oleh')->placeholder('-')->toggleable(),
                TextColumn::make('processed_at')->label('Diproses')->dateTime('d M Y H:i')->sortable(),
            ])
            ->defaultSort('processed_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSellerSettlementBatches::route('/'),
            'view' => Pages\ViewSellerSettlementBatch::route('/{record}'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            SettlementsRelationManager::class,
        ];
    }
}
