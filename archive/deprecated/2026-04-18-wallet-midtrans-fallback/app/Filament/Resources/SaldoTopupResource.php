<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaldoTopupResource\Pages;
use App\Models\SaldoTopup;
use App\Services\NotificationOutboxService;
use App\Services\SaldoTopupService;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class SaldoTopupResource extends Resource
{
    protected static ?string $model = SaldoTopup::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?string $modelLabel = 'Top Up Saldo';

    protected static ?string $pluralModelLabel = 'Top Up Saldo';

    protected static ?string $slug = 'saldo-topups';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $count = SaldoTopup::query()
            ->where('status', 'pending')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user'])
            ->withCount('ledgerEntries');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Buyer')
                    ->description(fn (SaldoTopup $record): ?string => $record->user?->email)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Nominal')
                    ->money('IDR', locale: 'id')
                    ->sortable(),
                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'success',
                        'danger' => 'failed',
                        'gray' => 'expired',
                    ]),
                IconColumn::make('ledger_entries_count')
                    ->label('Masuk Saldo')
                    ->state(fn (SaldoTopup $record): bool => (int) ($record->ledger_entries_count ?? 0) > 0)
                    ->boolean(),
                TextColumn::make('payment_method')
                    ->label('Metode')
                    ->formatStateUsing(fn (?string $state): string => $state ? strtoupper(str_replace('_', ' ', $state)) : '-')
                    ->toggleable(),
                TextColumn::make('midtrans_order_id')
                    ->label('Order ID')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('requested_at')
                    ->label('Diminta')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('paid_at')
                    ->label('Dibayar')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('expired_at')
                    ->label('Kadaluarsa')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'success' => 'Berhasil',
                        'failed' => 'Gagal',
                        'expired' => 'Kadaluarsa',
                    ]),
                SelectFilter::make('payment_method')
                    ->label('Metode')
                    ->options(fn (): array => SaldoTopup::query()
                        ->whereNotNull('payment_method')
                        ->orderBy('payment_method')
                        ->pluck('payment_method', 'payment_method')
                        ->mapWithKeys(fn ($label, $value): array => [$value => strtoupper(str_replace('_', ' ', (string) $label))])
                        ->all()),
                Filter::make('needs_reconciliation')
                    ->label('Perlu Rekonsiliasi')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('status', 'success')
                        ->whereDoesntHave('ledgerEntries')),
                Filter::make('credited')
                    ->label('Sudah Masuk Saldo')
                    ->query(fn (Builder $query): Builder => $query->whereHas('ledgerEntries')),
            ])
            ->actions([
                Actions\Action::make('mark_success')
                    ->label('Rekonsiliasi Berhasil')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (SaldoTopup $record): bool => ! $record->isSuccess() || (int) ($record->ledger_entries_count ?? 0) === 0)
                    ->form([
                        Select::make('payment_method')
                            ->label('Metode Konfirmasi')
                            ->options([
                                'manual_admin' => 'Manual Admin',
                                'bank_transfer' => 'Transfer Bank',
                                'qris' => 'QRIS',
                                'ewallet' => 'E-Wallet',
                                'cash' => 'Tunai / Offline',
                            ])
                            ->default('manual_admin')
                            ->required(),
                        Textarea::make('note')
                            ->label('Catatan Rekonsiliasi')
                            ->rows(3)
                            ->default('Top up saldo direkonsiliasi manual oleh admin.'),
                    ])
                    ->requiresConfirmation()
                    ->action(function (SaldoTopup $record, array $data): void {
                        app(SaldoTopupService::class)->markSucceededByAdmin(
                            $record,
                            (string) ($data['payment_method'] ?? 'manual_admin'),
                            (string) ($data['note'] ?? 'Top up saldo direkonsiliasi manual oleh admin.')
                        );

                        app(NotificationOutboxService::class)->processPending(50);
                    }),
                Actions\Action::make('mark_failed')
                    ->label('Tandai Gagal')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (SaldoTopup $record): bool => $record->isPending())
                    ->requiresConfirmation()
                    ->action(function (SaldoTopup $record): void {
                        app(SaldoTopupService::class)->markUnsuccessfulByAdmin($record, 'failed');
                        app(NotificationOutboxService::class)->processPending(50);
                    }),
                Actions\Action::make('mark_expired')
                    ->label('Tandai Kadaluarsa')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->visible(fn (SaldoTopup $record): bool => $record->isPending())
                    ->requiresConfirmation()
                    ->action(function (SaldoTopup $record): void {
                        app(SaldoTopupService::class)->markUnsuccessfulByAdmin($record, 'expired');
                        app(NotificationOutboxService::class)->processPending(50);
                    }),
            ])
            ->defaultSort('requested_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSaldoTopups::route('/'),
        ];
    }
}
