<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BidderPenaltyResource\Pages;
use App\Models\BidderPenalty;
use App\Models\Ikan;
use App\Models\User;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use UnitEnum;

class BidderPenaltyResource extends Resource
{
    protected static ?string $model = BidderPenalty::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static string|UnitEnum|null $navigationGroup = 'Monitoring';

    protected static ?string $modelLabel = 'Penalty Bidder';

    protected static ?string $pluralModelLabel = 'Penalty Bidder';

    protected static ?int $navigationSort = 11;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('user', function (Builder $userQuery): void {
                $userQuery
                    ->where('is_blacklisted', true)
                    ->orWhere(function (Builder $cooldownQuery): void {
                        $cooldownQuery
                            ->whereNotNull('auction_cooldown_until')
                            ->where('auction_cooldown_until', '>', now());
                    });
            })
            ->with(['user', 'ikan.user']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Bidder')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(),
                IconColumn::make('user.is_blacklisted')
                    ->label('Blacklist')
                    ->boolean(),
                TextColumn::make('user.auction_cooldown_until')
                    ->label('Cooldown User')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-'),
                TextColumn::make('user.reputation_score')
                    ->label('Reputasi User')
                    ->sortable(),
                TextColumn::make('ikan.id')
                    ->label('Lot ID')
                    ->sortable(),
                TextColumn::make('ikan.nama_ikan')
                    ->label('Lot')
                    ->searchable(),
                TextColumn::make('ikan.user.name')
                    ->label('Penjual')
                    ->searchable(),
                BadgeColumn::make('reason')
                    ->label('Alasan')
                    ->colors([
                        'danger' => 'payment_default',
                        'gray' => fn (?string $state) => $state !== 'payment_default',
                    ]),
                TextColumn::make('reputation_delta')
                    ->label('Delta Reputasi')
                    ->sortable(),
                TextColumn::make('cooldown_until')
                    ->label('Cooldown Penalty')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-')
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
                        Select::make('bidder_id')
                            ->label('Bidder')
                            ->searchable()
                            ->options(fn (): array => User::query()->where('role', 'pembeli')->orderBy('name')->limit(300)->pluck('name', 'id')->all()),
                        Select::make('lot_id')
                            ->label('Lot')
                            ->searchable()
                            ->options(fn (): array => Ikan::query()->orderByDesc('id')->limit(300)->pluck('nama_ikan', 'id')->all()),
                        Select::make('seller_id')
                            ->label('Penjual')
                            ->searchable()
                            ->options(fn (): array => User::query()->where('role', 'penjual')->orderBy('name')->limit(300)->pluck('name', 'id')->all()),
                        Select::make('reason')
                            ->label('Alasan')
                            ->options(fn (): array => BidderPenalty::query()->select('reason')->distinct()->orderBy('reason')->pluck('reason', 'reason')->all()),
                        Select::make('bidder_state')
                            ->label('Status Bidder')
                            ->options([
                                'blacklisted' => 'Blacklisted',
                                'cooldown_active' => 'Cooldown Aktif',
                            ]),
                        DatePicker::make('from_date')->label('Dari Tanggal'),
                        DatePicker::make('to_date')->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                ! empty($data['bidder_id']),
                                fn (Builder $q) => $q->where('user_id', (int) $data['bidder_id'])
                            )
                            ->when(
                                ! empty($data['lot_id']),
                                fn (Builder $q) => $q->where('ikan_id', (int) $data['lot_id'])
                            )
                            ->when(
                                ! empty($data['seller_id']),
                                fn (Builder $q) => $q->whereHas('ikan', fn (Builder $ikanQuery) => $ikanQuery->where('user_id', (int) $data['seller_id']))
                            )
                            ->when(
                                ! empty($data['reason']),
                                fn (Builder $q) => $q->where('reason', (string) $data['reason'])
                            )
                            ->when(
                                ($data['bidder_state'] ?? null) === 'blacklisted',
                                fn (Builder $q) => $q->whereHas('user', fn (Builder $userQuery) => $userQuery->where('is_blacklisted', true))
                            )
                            ->when(
                                ($data['bidder_state'] ?? null) === 'cooldown_active',
                                fn (Builder $q) => $q->whereHas('user', fn (Builder $userQuery) => $userQuery->whereNotNull('auction_cooldown_until')->where('auction_cooldown_until', '>', now()))
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
            ->actions([
                Actions\Action::make('extend_penalty')
                    ->label('Perpanjang Hukuman')
                    ->icon('heroicon-o-clock')
                    ->color('warning')
                    ->visible(fn (BidderPenalty $record): bool => $record->user !== null)
                    ->form([
                        DateTimePicker::make('cooldown_until')
                            ->label('Cooldown Sampai')
                            ->required()
                            ->seconds(false)
                            ->minDate(now())
                            ->default(function (BidderPenalty $record) {
                                return $record->user?->auction_cooldown_until
                                    ?? $record->cooldown_until
                                    ?? now()->addDay();
                            })
                            ->helperText('Tanggal ini diterapkan ke cooldown user dan record penalty.'),
                        Toggle::make('set_blacklist')
                            ->label('Aktifkan blacklist bidder')
                            ->default(false),
                    ])
                    ->requiresConfirmation()
                    ->action(function (BidderPenalty $record, array $data): void {
                        $cooldownInput = $data['cooldown_until'] ?? null;
                        $requestedCooldown = $cooldownInput instanceof \DateTimeInterface
                            ? Carbon::instance($cooldownInput)
                            : Carbon::parse((string) $cooldownInput);

                        self::extendPenalty($record, $requestedCooldown, (bool) ($data['set_blacklist'] ?? false));
                    }),
                Actions\Action::make('release_penalty')
                    ->label('Lepas Hukuman')
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->visible(function (BidderPenalty $record): bool {
                        $user = $record->user;
                        if (! $user) {
                            return false;
                        }

                        return (bool) $user->is_blacklisted
                            || ($user->auction_cooldown_until && $user->auction_cooldown_until->isFuture());
                    })
                    ->requiresConfirmation()
                    ->action(function (BidderPenalty $record): void {
                        self::releasePenalty($record);
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBidderPenalties::route('/'),
        ];
    }

    private static function extendPenalty(BidderPenalty $record, Carbon $requestedCooldownUntil, bool $setBlacklist): void
    {
        $record->loadMissing('user');

        $effectiveCooldown = $requestedCooldownUntil->copy();

        if ($record->cooldown_until && $record->cooldown_until->gt($effectiveCooldown)) {
            $effectiveCooldown = $record->cooldown_until->copy();
        }

        $user = $record->user;
        if ($user && $user->auction_cooldown_until && $user->auction_cooldown_until->gt($effectiveCooldown)) {
            $effectiveCooldown = $user->auction_cooldown_until->copy();
        }

        $record->cooldown_until = $effectiveCooldown;
        $record->save();

        if (! $user) {
            return;
        }

        $user->auction_cooldown_until = $effectiveCooldown;

        if ($setBlacklist) {
            $user->is_blacklisted = true;
        }

        $user->save();
    }

    private static function releasePenalty(BidderPenalty $record): void
    {
        $record->loadMissing('user');

        $record->cooldown_until = now();
        $record->save();

        $user = $record->user;
        if (! $user) {
            return;
        }

        $user->auction_cooldown_until = null;
        $user->is_blacklisted = false;

        $user->save();
    }
}
