<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Actions;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected static string|UnitEnum|null $navigationGroup = 'Monitoring';

    protected static ?string $modelLabel = 'Log Sistem';

    protected static ?string $pluralModelLabel = 'Log Sistem';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->canAdmin('support');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('payload')
                    ->label('Payload')
                    ->formatStateUsing(fn ($state): string => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : (string) $state)
                    ->disabled()
                    ->dehydrated(false)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                BadgeColumn::make('actor_type')->label('Aktor')->sortable(),
                TextColumn::make('actor_id')->label('Aktor ID')->sortable()->placeholder('-'),
                TextColumn::make('action')
                    ->label('Action')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => self::actionLabel($state)),
                TextColumn::make('resource_type')->label('Resource')->searchable()->placeholder('-'),
                TextColumn::make('resource_id')->label('Resource ID')->sortable()->placeholder('-'),
                TextColumn::make('payload_summary')
                    ->label('Ringkasan')
                    ->state(fn (AuditLog $record): string => self::payloadSummary($record))
                    ->wrap()
                    ->limit(100),
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i:s')->sortable(),
            ])
            ->filters([
                Filter::make('settings')
                    ->label('Settings')
                    ->query(fn (Builder $query): Builder => $query->where('action', 'system_setting.updated')),
                Filter::make('disputes')
                    ->label('Sengketa')
                    ->query(fn (Builder $query): Builder => $query->where('action', 'like', 'transaction.dispute_%')),
                Filter::make('settlements')
                    ->label('Settlement')
                    ->query(fn (Builder $query): Builder => $query->where('action', 'like', 'seller_settlement.%')),
                Filter::make('user_moderation')
                    ->label('Moderasi User')
                    ->query(fn (Builder $query): Builder => $query->where(function (Builder $subQuery): void {
                        $subQuery
                            ->where('action', 'like', 'user.%')
                            ->orWhere('action', 'like', 'violation.%');
                    })),
                Filter::make('payments')
                    ->label('Payment')
                    ->query(fn (Builder $query): Builder => $query->where('action', 'like', 'payment.%')),
                SelectFilter::make('actor_type')
                    ->label('Aktor')
                    ->options([
                        'admin' => 'Admin',
                        'system' => 'System',
                        'user' => 'User',
                    ]),
            ])
            ->actions([
                Actions\ViewAction::make()
                    ->label('Detail')
                    ->modalHeading('Detail log sistem')
                    ->schema([
                        Textarea::make('payload')
                            ->label('Payload')
                            ->formatStateUsing(fn (AuditLog $record): string => json_encode($record->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ]),
                Actions\DeleteAction::make()
                    ->label('Hapus')
                    ->visible(fn (): bool => (bool) auth()->user()?->isSuperAdmin())
                    ->modalHeading('Hapus log sistem?')
                    ->modalDescription('Log ini akan dihapus permanen dari database.'),
            ])
            ->bulkActions([
                BulkAction::make('delete_selected_logs')
                    ->label('Hapus Terpilih')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (): bool => (bool) auth()->user()?->isSuperAdmin())
                    ->requiresConfirmation()
                    ->modalHeading('Hapus log terpilih?')
                    ->modalDescription('Semua log yang dipilih akan dihapus permanen dari database.')
                    ->action(function ($records): void {
                        $ids = collect($records)
                            ->pluck('id')
                            ->map(fn ($id): int => (int) $id)
                            ->filter(fn (int $id): bool => $id > 0)
                            ->values()
                            ->all();

                        if ($ids === []) {
                            return;
                        }

                        AuditLog::query()
                            ->whereIn('id', $ids)
                            ->delete();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function payloadSummary(AuditLog $record): string
    {
        $payload = $record->payload ?? [];

        if ($record->action === 'system_setting.updated') {
            $key = (string) data_get($payload, 'key', '');

            return $key !== '' ? 'Perubahan setting: ' . $key : 'Perubahan setting sistem';
        }

        if (str_starts_with($record->action, 'seller_settlement.')) {
            $oldStatus = data_get($payload, 'old_status');
            $newStatus = data_get($payload, 'new_status');
            $transaksiId = data_get($payload, 'transaksi_id');

            $parts = [];
            if ($oldStatus !== null || $newStatus !== null) {
                $parts[] = trim('Status: ' . ($oldStatus ?: '-') . ' -> ' . ($newStatus ?: '-'));
            }
            if ($transaksiId !== null) {
                $parts[] = 'Transaksi #' . $transaksiId;
            }
            if (data_get($payload, 'hold_reason')) {
                $parts[] = 'Alasan: ' . data_get($payload, 'hold_reason');
            }

            return $parts !== [] ? implode(' | ', $parts) : 'Perubahan settlement seller';
        }

        if (str_starts_with($record->action, 'transaction.dispute_')) {
            $resolution = data_get($payload, 'resolution');
            $reasonText = data_get($payload, 'reason_text');
            $note = data_get($payload, 'note');

            $parts = [];
            if ($resolution) {
                $parts[] = 'Keputusan: ' . $resolution;
            }
            if ($reasonText) {
                $parts[] = 'Alasan: ' . $reasonText;
            }
            if ($note) {
                $parts[] = 'Catatan: ' . $note;
            }

            return $parts !== [] ? implode(' | ', $parts) : 'Perubahan sengketa transaksi';
        }

        if (str_starts_with($record->action, 'transaction.')) {
            $oldStatus = data_get($payload, 'old_status');
            $newStatus = data_get($payload, 'new_status');
            $paymentStatus = data_get($payload, 'payment_status');

            $parts = [];
            if ($oldStatus !== null || $newStatus !== null) {
                $parts[] = trim('Status: ' . ($oldStatus ?: '-') . ' -> ' . ($newStatus ?: '-'));
            }
            if ($paymentStatus) {
                $parts[] = 'Payment: ' . $paymentStatus;
            }

            return $parts !== [] ? implode(' | ', $parts) : 'Perubahan transaksi';
        }

        if (str_starts_with($record->action, 'user.')) {
            $reason = data_get($payload, 'reason');
            $durationHours = data_get($payload, 'duration_hours');

            $parts = [];
            if ($reason) {
                $parts[] = 'Alasan: ' . $reason;
            }
            if ($durationHours) {
                $parts[] = 'Durasi: ' . $durationHours . ' jam';
            }

            return $parts !== [] ? implode(' | ', $parts) : 'Perubahan status pengguna';
        }

        if (str_starts_with($record->action, 'payment.')) {
            $parts = [];
            if (data_get($payload, 'order_code')) {
                $parts[] = 'Order: ' . data_get($payload, 'order_code');
            }
            if (data_get($payload, 'payment_code')) {
                $parts[] = 'Invoice: ' . data_get($payload, 'payment_code');
            }
            if (data_get($payload, 'method')) {
                $parts[] = 'Metode: ' . data_get($payload, 'method');
            }

            return $parts !== [] ? implode(' | ', $parts) : 'Aktivitas pembayaran';
        }

        if ($payload === []) {
            return '-';
        }

        $pairs = collect($payload)
            ->map(function ($value, $key): string {
                if (is_array($value)) {
                    return $key . ': [data]';
                }

                return $key . ': ' . (string) $value;
            })
            ->take(3)
            ->values()
            ->all();

        return $pairs === [] ? '-' : implode(' | ', $pairs);
    }

    private static function actionLabel(string $action): string
    {
        return match ($action) {
            'system_setting.updated' => 'Setting sistem diubah',
            'transaction.mark_paid' => 'Transaksi ditandai lunas',
            'transaction.cancelled' => 'Transaksi dibatalkan',
            'transaction.override_status' => 'Status transaksi diubah manual',
            'transaction.dispute_opened' => 'Sengketa transaksi dibuka',
            'transaction.dispute_resolved' => 'Sengketa transaksi diselesaikan',
            'seller_settlement.ready_to_pay' => 'Settlement seller siap dibayar',
            'seller_settlement.held' => 'Settlement seller ditahan',
            'seller_settlement.mark_paid' => 'Settlement seller ditandai dibayar',
            'seller_settlement.batch_paid' => 'Batch payout settlement seller',
            'user.suspended' => 'Pengguna disuspend',
            'user.banned' => 'Pengguna diblokir',
            'user.activated' => 'Pengguna diaktifkan',
            'user.data_cleared' => 'Data marketplace pengguna dihapus',
            'violation.created' => 'Pelanggaran dibuat',
            'violation.suspend_applied' => 'Sanksi suspend diterapkan',
            'violation.ban_applied' => 'Sanksi ban diterapkan',
            'violation.resolved' => 'Pelanggaran diselesaikan',
            'payment.attempt_created' => 'Invoice pembayaran dibuat',
            'payment.callback_processed' => 'Callback pembayaran diproses',
            'payment.reconcile_processed' => 'Rekonsiliasi pembayaran diproses',
            default => str_replace('_', ' ', $action),
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
        ];
    }
}
