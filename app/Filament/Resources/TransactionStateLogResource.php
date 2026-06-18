<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionStateLogResource\Pages;
use App\Models\TransactionStateLog;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class TransactionStateLogResource extends Resource
{
    protected static ?string $model = TransactionStateLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Monitoring';

    protected static ?string $modelLabel = 'Audit Transaksi';

    protected static ?string $pluralModelLabel = 'Audit Transaksi';

    protected static ?int $navigationSort = 12;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['transaksi.ikan', 'transaksi.pemenang']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transaksi.id')
                    ->label('Transaksi ID')
                    ->sortable(),
                TextColumn::make('transaksi.ikan.nama_ikan')
                    ->label('Lot')
                    ->searchable(),
                TextColumn::make('transaksi.pemenang.name')
                    ->label('Pembeli')
                    ->searchable(),
                BadgeColumn::make('from_state')
                    ->label('Dari')
                    ->placeholder('-')
                    ->formatStateUsing(fn (?string $state): string => transactionStateLabel($state))
                    ->colors([
                        'info' => 'DIBAYAR',
                        'warning' => 'DIPROSES_PENJUAL',
                        'primary' => 'DIKIRIM',
                        'success' => 'SELESAI',
                        'danger' => 'GAGAL',
                        'gray' => 'DISENGKETAKAN',
                    ]),
                BadgeColumn::make('to_state')
                    ->label('Ke')
                    ->formatStateUsing(fn (?string $state): string => transactionStateLabel($state))
                    ->colors([
                        'info' => 'DIBAYAR',
                        'warning' => 'DIPROSES_PENJUAL',
                        'primary' => 'DIKIRIM',
                        'success' => 'SELESAI',
                        'danger' => 'GAGAL',
                        'gray' => 'DISENGKETAKAN',
                    ]),
                TextColumn::make('event_name')
                    ->label('Event')
                    ->searchable(),
                TextColumn::make('actor_type')
                    ->label('Aktor')
                    ->badge(),
                TextColumn::make('reason_code')
                    ->label('Reason Code')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('reason_text')
                    ->label('Reason')
                    ->limit(60)
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i:s')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('monitoring')
                    ->label('Filter Monitoring')
                    ->form([
                        Select::make('to_state')
                            ->label('State Tujuan')
                            ->options([
                                'DIBAYAR' => transactionStateLabel('DIBAYAR'),
                                'DIPROSES_PENJUAL' => transactionStateLabel('DIPROSES_PENJUAL'),
                                'DIKIRIM' => transactionStateLabel('DIKIRIM'),
                                'SELESAI' => transactionStateLabel('SELESAI'),
                                'GAGAL' => transactionStateLabel('GAGAL'),
                                'DISENGKETAKAN' => transactionStateLabel('DISENGKETAKAN'),
                            ]),
                        Select::make('actor_type')
                            ->label('Aktor')
                            ->options([
                                'system' => 'System',
                                'seller' => 'Seller',
                                'buyer' => 'Buyer',
                                'admin' => 'Admin',
                            ]),
                        DatePicker::make('from_date')->label('Dari Tanggal'),
                        DatePicker::make('to_date')->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                ! empty($data['to_state']),
                                fn (Builder $q) => $q->where('to_state', (string) $data['to_state'])
                            )
                            ->when(
                                ! empty($data['actor_type']),
                                fn (Builder $q) => $q->where('actor_type', (string) $data['actor_type'])
                            )
                            ->when(
                                ! empty($data['from_date']),
                                fn (Builder $q) => $q->whereDate('created_at', '>=', (string) $data['from_date'])
                            )
                            ->when(
                                ! empty($data['to_date']),
                                fn (Builder $q) => $q->whereDate('created_at', '<=', (string) $data['to_date'])
                            );
                    }),
            ])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactionStateLogs::route('/'),
        ];
    }
}
