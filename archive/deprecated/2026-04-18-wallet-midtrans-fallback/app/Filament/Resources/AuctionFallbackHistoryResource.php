<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuctionFallbackHistoryResource\Pages;
use App\Models\AuctionFallbackHistory;
use App\Models\Ikan;
use App\Models\User;
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

class AuctionFallbackHistoryResource extends Resource
{
    protected static ?string $model = AuctionFallbackHistory::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static string|UnitEnum|null $navigationGroup = 'Monitoring';

    protected static ?string $modelLabel = 'Fallback Lelang';

    protected static ?string $pluralModelLabel = 'Fallback Lelang';

    protected static ?int $navigationSort = 10;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['ikan.user']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ikan.id')
                    ->label('Lot ID')
                    ->sortable(),
                TextColumn::make('ikan.nama_ikan')
                    ->label('Lot')
                    ->searchable(),
                TextColumn::make('ikan.user.name')
                    ->label('Penjual')
                    ->searchable(),
                TextColumn::make('from_rank')
                    ->label('Dari Rank')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('to_rank')
                    ->label('Ke Rank')
                    ->placeholder('-')
                    ->sortable(),
                BadgeColumn::make('reason')
                    ->label('Alasan')
                    ->colors([
                        'warning' => fn (?string $state) => str_contains((string) $state, 'payment_expired'),
                        'danger' => fn (?string $state) => str_contains((string) $state, 'max_fallback_reached') || str_contains((string) $state, 'all_bidder_failed') || str_contains((string) $state, 'reserve_not_met'),
                        'info' => fn (?string $state) => str_contains((string) $state, 'assignment_failed'),
                        'gray' => fn (?string $state) => ! empty($state),
                    ])
                    ->searchable(),
                BadgeColumn::make('triggered_by_type')
                    ->label('Sumber')
                    ->colors([
                        'info' => 'webhook',
                        'warning' => 'scheduler',
                        'success' => 'controller',
                        'gray' => fn (?string $state) => ! in_array($state, ['webhook', 'scheduler', 'controller'], true),
                    ]),
                TextColumn::make('fallback_count_after')
                    ->label('Fallback Ke')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('monitoring')
                    ->label('Filter Monitoring')
                    ->form([
                        Select::make('lot_id')
                            ->label('Lot')
                            ->searchable()
                            ->options(fn (): array => Ikan::query()->orderByDesc('id')->limit(300)->pluck('nama_ikan', 'id')->all()),
                        Select::make('seller_id')
                            ->label('Penjual')
                            ->searchable()
                            ->options(fn (): array => User::query()->where('role', 'penjual')->orderBy('name')->limit(300)->pluck('name', 'id')->all()),
                        Select::make('triggered_by_type')
                            ->label('Sumber')
                            ->options([
                                'system' => 'System',
                                'scheduler' => 'Scheduler',
                                'controller' => 'Controller',
                                'webhook' => 'Webhook',
                            ]),
                        Select::make('reason')
                            ->label('Alasan')
                            ->searchable()
                            ->options(fn (): array => AuctionFallbackHistory::query()->select('reason')->distinct()->orderBy('reason')->pluck('reason', 'reason')->all()),
                        DatePicker::make('from_date')->label('Dari Tanggal'),
                        DatePicker::make('to_date')->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                ! empty($data['lot_id']),
                                fn (Builder $q) => $q->where('ikan_id', (int) $data['lot_id'])
                            )
                            ->when(
                                ! empty($data['seller_id']),
                                fn (Builder $q) => $q->whereHas('ikan', fn (Builder $ikanQuery) => $ikanQuery->where('user_id', (int) $data['seller_id']))
                            )
                            ->when(
                                ! empty($data['triggered_by_type']),
                                fn (Builder $q) => $q->where('triggered_by_type', (string) $data['triggered_by_type'])
                            )
                            ->when(
                                ! empty($data['reason']),
                                fn (Builder $q) => $q->where('reason', (string) $data['reason'])
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
            'index' => Pages\ListAuctionFallbackHistories::route('/'),
        ];
    }
}
