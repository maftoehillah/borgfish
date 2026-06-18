<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InAppNotificationResource\Pages;
use App\Models\InAppNotification;
use BackedEnum;
use Filament\Actions;
use Filament\Actions\BulkAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class InAppNotificationResource extends Resource
{
    protected static ?string $model = InAppNotification::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static string|UnitEnum|null $navigationGroup = 'Monitoring';

    protected static ?string $modelLabel = 'Notifikasi Saya';

    protected static ?string $pluralModelLabel = 'Notifikasi Saya';

    protected static ?string $slug = 'notifikasi';

    protected static ?int $navigationSort = 14;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()?->isAdminUser();
    }

    public static function getNavigationBadge(): ?string
    {
        $adminId = auth()->id();
        if (! $adminId) {
            return null;
        }

        $count = InAppNotification::query()
            ->where('user_id', (int) $adminId)
            ->whereNull('read_at')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        $adminId = auth()->id();

        if (! $adminId) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->where('user_id', (int) $adminId);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                BadgeColumn::make('status_baca')
                    ->label('Status')
                    ->state(fn (InAppNotification $record): string => $record->read_at ? 'Sudah Dibaca' : 'Belum Dibaca')
                    ->colors([
                        'success' => 'Sudah Dibaca',
                        'warning' => 'Belum Dibaca',
                    ]),
                TextColumn::make('title')
                    ->label('Judul')
                    ->searchable(),
                TextColumn::make('message')
                    ->label('Pesan')
                    ->limit(90)
                    ->searchable(),
                BadgeColumn::make('category')
                    ->label('Kategori')
                    ->colors([
                        'success' => 'pesanan',
                        'info' => 'pembayaran',
                        'primary' => 'penjemputan',
                        'danger' => 'sengketa',
                        'warning' => 'pelanggaran',
                        'gray' => 'operasional',
                    ])
                    ->sortable(),
                TextColumn::make('payload.transaksi_id')
                    ->label('Transaksi ID')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('payload.violation_id')
                    ->label('Pelanggaran ID')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('read_at')
                    ->label('Dibaca')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('unread')
                    ->label('Belum Dibaca')
                    ->query(fn (Builder $query): Builder => $query->whereNull('read_at')),
                SelectFilter::make('category')
                    ->label('Kategori')
                    ->options([
                        'pembayaran' => 'Pembayaran',
                        'pesanan' => 'Pesanan',
                        'penjemputan' => 'Penjemputan',
                        'sengketa' => 'Sengketa',
                        'pelanggaran' => 'Pelanggaran',
                        'operasional' => 'Operasional',
                    ]),
            ])
            ->actions([
                Actions\Action::make('open_related_transaction')
                    ->label('Buka Terkait')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (InAppNotification $record): string => self::resolveRelatedUrl($record)),
                Actions\Action::make('mark_read_notification')
                    ->label('Tandai Dibaca')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (InAppNotification $record): bool => $record->read_at === null)
                    ->action(function (InAppNotification $record): void {
                        $record->read_at = now();
                        $record->save();
                    }),
            ])
            ->bulkActions([
                BulkAction::make('mark_read_selected')
                    ->label('Tandai Dibaca (Terpilih)')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($records): void {
                        $adminId = auth()->id();
                        if (! $adminId) {
                            return;
                        }

                        $selectedIds = collect($records)
                            ->pluck('id')
                            ->map(fn ($id): int => (int) $id)
                            ->filter(fn (int $id): bool => $id > 0)
                            ->values()
                            ->all();

                        if ($selectedIds === []) {
                            return;
                        }

                        InAppNotification::query()
                            ->where('user_id', (int) $adminId)
                            ->whereIn('id', $selectedIds)
                            ->whereNull('read_at')
                            ->update([
                                'read_at' => now(),
                                'updated_at' => now(),
                            ]);
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function resolveRelatedUrl(InAppNotification $record): string
    {
        $admin = auth()->user();
        if (! $admin) {
            return self::getUrl('index');
        }

        return notificationDestinationUrl($admin, $record, self::getUrl('index'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInAppNotifications::route('/'),
        ];
    }
}
