<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SellerSettlementResource\Pages;
use App\Models\SellerSettlement;
use App\Services\AuditService;
use App\Services\NotificationOutboxService;
use App\Services\SellerSettlementBatchPayoutService;
use BackedEnum;
use Filament\Actions;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class SellerSettlementResource extends Resource
{
    protected static ?string $model = SellerSettlement::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Transaksi';

    protected static ?string $modelLabel = 'Settlement Seller';

    protected static ?string $pluralModelLabel = 'Settlement Seller';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('transaksi.order_code')
                    ->label('Order ID')
                    ->formatStateUsing(fn (?SellerSettlement $record): string => (string) ($record?->transaksi?->order_code ?: '-'))
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('seller.name')
                    ->label('Seller')
                    ->formatStateUsing(fn (?SellerSettlement $record): string => (string) ($record?->seller?->name ?: '-'))
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('amount')
                    ->label('Nominal Settlement')
                    ->numeric()
                    ->prefix('Rp')
                    ->disabled(),
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending Review',
                        'ready_to_pay' => 'Siap Dibayar',
                        'held' => 'Ditahan',
                        'paid' => 'Sudah Dibayar',
                        'cancelled' => 'Dibatalkan',
                    ])
                    ->required(),
                TextInput::make('bank_name')->label('Nama Bank')->disabled(),
                TextInput::make('bank_account_number')->label('Nomor Rekening')->disabled(),
                TextInput::make('bank_account_name')->label('Nama Pemilik Rekening')->disabled(),
                TextInput::make('transfer_reference')
                    ->label('Referensi Transfer')
                    ->maxLength(120),
                FileUpload::make('transfer_proof_path')
                    ->label('Bukti Transfer')
                    ->disk('public')
                    ->directory('seller-settlements')
                    ->columnSpanFull(),
                Textarea::make('admin_note')
                    ->label('Catatan Admin')
                    ->rows(4)
                    ->columnSpanFull(),
                Textarea::make('hold_reason')
                    ->label('Alasan Ditahan')
                    ->rows(3)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transaksi.order_code')->label('Order ID')->searchable()->sortable(),
                TextColumn::make('transaksi.ikan.nama_ikan')->label('Lot')->searchable()->toggleable(),
                TextColumn::make('seller.name')->label('Seller')->searchable(),
                TextColumn::make('batch.batch_number')->label('Batch')->placeholder('-')->toggleable(),
                TextColumn::make('amount')
                    ->label('Nominal')
                    ->formatStateUsing(fn ($state) => formatRupiah($state))
                    ->sortable(),
                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'ready_to_pay',
                        'danger' => 'held',
                        'success' => 'paid',
                        'gray' => 'cancelled',
                    ]),
                TextColumn::make('bank_name')->label('Bank')->toggleable(),
                TextColumn::make('transfer_reference')->label('Ref Transfer')->placeholder('-')->toggleable(),
                TextColumn::make('paid_at')->label('Dibayar')->dateTime('d M Y H:i')->placeholder('-')->sortable(),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending Review',
                        'ready_to_pay' => 'Siap Dibayar',
                        'held' => 'Ditahan',
                        'paid' => 'Sudah Dibayar',
                        'cancelled' => 'Dibatalkan',
                    ]),
                Filter::make('action_required')
                    ->label('Butuh Tindakan')
                    ->query(fn (Builder $query): Builder => $query->whereIn('status', ['pending', 'ready_to_pay', 'held'])),
                Filter::make('today')
                    ->label('Hari Ini')
                    ->query(fn (Builder $query): Builder => $query->whereDate('created_at', today())),
                Filter::make('this_week')
                    ->label('Minggu Ini')
                    ->query(fn (Builder $query): Builder => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
                Actions\Action::make('ready_to_pay')
                    ->label('Siap Dibayar')
                    ->color('info')
                    ->visible(fn (SellerSettlement $record): bool => in_array((string) $record->status, ['pending', 'held'], true))
                    ->requiresConfirmation()
                    ->action(function (SellerSettlement $record): void {
                        $oldStatus = (string) $record->status;
                        $record->status = 'ready_to_pay';
                        $record->ready_to_pay_at = now();
                        $record->updated_by_id = auth()->id();
                        $record->hold_reason = null;
                        $record->save();

                        AuditService::log('admin', auth()->id(), 'seller_settlement.ready_to_pay', 'seller_settlements', (int) $record->id, [
                            'old_status' => $oldStatus,
                            'new_status' => $record->status,
                            'transaksi_id' => (int) $record->transaksi_id,
                            'seller_id' => (int) $record->seller_id,
                        ]);

                        app(NotificationOutboxService::class)->queueForSellerSettlementReady($record->fresh());
                        app(NotificationOutboxService::class)->processPending(50);
                    }),
                Actions\Action::make('hold')
                    ->label('Tahan')
                    ->color('danger')
                    ->form([
                        Textarea::make('hold_reason')
                            ->label('Alasan Ditahan')
                            ->required()
                            ->maxLength(500),
                    ])
                    ->visible(fn (SellerSettlement $record): bool => in_array((string) $record->status, ['pending', 'ready_to_pay'], true))
                    ->requiresConfirmation()
                    ->action(function (SellerSettlement $record, array $data): void {
                        $oldStatus = (string) $record->status;
                        $record->status = 'held';
                        $record->held_at = now();
                        $record->updated_by_id = auth()->id();
                        $record->hold_reason = (string) ($data['hold_reason'] ?? '');
                        $record->save();

                        AuditService::log('admin', auth()->id(), 'seller_settlement.held', 'seller_settlements', (int) $record->id, [
                            'old_status' => $oldStatus,
                            'new_status' => $record->status,
                            'hold_reason' => $record->hold_reason,
                            'transaksi_id' => (int) $record->transaksi_id,
                            'seller_id' => (int) $record->seller_id,
                        ]);
                    }),
                Actions\Action::make('mark_paid')
                    ->label('Tandai Dibayar')
                    ->color('success')
                    ->visible(fn (SellerSettlement $record): bool => (string) $record->status === 'ready_to_pay')
                    ->form([
                        TextInput::make('transfer_reference')
                            ->label('Referensi Transfer')
                            ->required()
                            ->maxLength(120),
                        FileUpload::make('transfer_proof_path')
                            ->label('Bukti Transfer')
                            ->disk('public')
                            ->directory('seller-settlements'),
                        Textarea::make('admin_note')
                            ->label('Catatan Payout')
                            ->rows(3),
                    ])
                    ->requiresConfirmation()
                    ->action(function (SellerSettlement $record, array $data): void {
                        app(SellerSettlementBatchPayoutService::class)->markAsPaid(
                            settlementIds: [(int) $record->id],
                            actorId: auth()->id(),
                            transferReference: (string) ($data['transfer_reference'] ?? ''),
                            transferProofPath: static::normalizeUploadState($data['transfer_proof_path'] ?? null),
                            adminNote: isset($data['admin_note']) ? (string) $data['admin_note'] : null,
                        );
                    }),
            ])
            ->bulkActions([
                BulkAction::make('mark_ready_selected')
                    ->label('Tandai Siap Dibayar')
                    ->icon('heroicon-o-check-circle')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function ($records): void {
                        $recordsToUpdate = collect($records)
                            ->filter(fn (SellerSettlement $record): bool => in_array((string) $record->status, ['pending', 'held'], true))
                            ->values();

                        if ($recordsToUpdate->isEmpty()) {
                            return;
                        }

                        $notificationService = app(NotificationOutboxService::class);

                        $recordsToUpdate->each(function (SellerSettlement $record) use ($notificationService): void {
                            $oldStatus = (string) $record->status;
                            $record->status = 'ready_to_pay';
                            $record->ready_to_pay_at = now();
                            $record->hold_reason = null;
                            $record->updated_by_id = auth()->id();
                            $record->save();

                            AuditService::log('admin', auth()->id(), 'seller_settlement.ready_to_pay', 'seller_settlements', (int) $record->id, [
                                'old_status' => $oldStatus,
                                'new_status' => $record->status,
                                'transaksi_id' => (int) $record->transaksi_id,
                                'seller_id' => (int) $record->seller_id,
                                'bulk' => true,
                            ]);

                            $notificationService->queueForSellerSettlementReady($record->fresh());
                        });

                        $notificationService->processPending(100);
                    }),
                BulkAction::make('mark_paid_selected')
                    ->label('Batch Payout')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->requiresConfirmation()
                    ->form([
                        TextInput::make('transfer_reference')
                            ->label('Referensi Transfer Batch')
                            ->required()
                            ->maxLength(120),
                        FileUpload::make('transfer_proof_path')
                            ->label('Bukti Transfer Batch')
                            ->disk('public')
                            ->directory('seller-settlements'),
                        Textarea::make('admin_note')
                            ->label('Catatan Batch')
                            ->rows(3),
                    ])
                    ->action(function ($records, array $data): void {
                        $ids = collect($records)
                            ->filter(fn (SellerSettlement $record): bool => (string) $record->status === 'ready_to_pay')
                            ->pluck('id')
                            ->map(fn ($id): int => (int) $id)
                            ->filter(fn (int $id): bool => $id > 0)
                            ->values()
                            ->all();

                        if ($ids === []) {
                            return;
                        }

                        app(SellerSettlementBatchPayoutService::class)->markAsPaid(
                            settlementIds: $ids,
                            actorId: auth()->id(),
                            transferReference: isset($data['transfer_reference']) ? (string) $data['transfer_reference'] : null,
                            transferProofPath: static::normalizeUploadState($data['transfer_proof_path'] ?? null),
                            adminNote: isset($data['admin_note']) ? (string) $data['admin_note'] : null,
                        );
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSellerSettlements::route('/'),
            'view' => Pages\ViewSellerSettlement::route('/{record}'),
            'edit' => Pages\EditSellerSettlement::route('/{record}/edit'),
        ];
    }

    private static function normalizeUploadState(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        if (! filled($value)) {
            return null;
        }

        return (string) $value;
    }
}
