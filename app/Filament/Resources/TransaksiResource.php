<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransaksiResource\Pages;
use App\Models\AuctionStateLog;
use App\Models\SellerSettlement;
use App\Models\Transaksi;
use App\Services\AuditService;
use App\Services\NotificationOutboxService;
use App\Services\PembayaranService;
use App\Services\TransaksiFulfillmentService;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class TransaksiResource extends Resource
{
    protected static ?string $model = Transaksi::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Transaksi';

    protected static ?string $modelLabel = 'Transaksi';

    protected static ?string $pluralModelLabel = 'Transaksi';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->canAdmin('support');
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->canAdmin('ops');
    }

    public static function canEdit(Model $record): bool
    {
        return (bool) auth()->user()?->canAdmin('ops');
    }

    public static function canDelete(Model $record): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('order_code')
                    ->label('Order Internal ID')
                    ->maxLength(32)
                    ->readOnly(),
                Select::make('ikan_id')
                    ->label('Lot Lelang')
                    ->relationship('ikan', 'nama_ikan')
                    ->searchable()
                    ->required(),
                Select::make('pemenang_id')
                    ->label('Pembeli')
                    ->relationship('pemenang', 'name')
                    ->searchable()
                    ->required(),
                TextInput::make('harga_final')
                    ->label('Harga Final')
                    ->numeric()
                    ->prefix('Rp')
                    ->required(),
                Select::make('status')
                    ->label('Status Order')
                    ->options([
                        'menunggu_bayar' => 'Menunggu Bayar',
                        'proses' => 'Proses',
                        'lunas' => 'Lunas',
                        'gagal' => 'Gagal',
                        'kadaluarsa' => 'Kadaluarsa',
                    ])
                    ->required(),
                Select::make('payment_status')
                    ->label('Status Payment')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'failed' => 'Failed',
                        'expired' => 'Expired',
                        'cancelled' => 'Cancelled',
                        'refunded' => 'Refunded',
                    ])
                    ->default('pending')
                    ->required(),
                TextInput::make('metode_pembayaran')->label('Metode Pembayaran'),
                DateTimePicker::make('bayar_sebelum')->label('Deadline Bayar'),
                DateTimePicker::make('dibayar_pada')->label('Dibayar Pada'),
                Select::make('pickup_status')
                    ->label('Status Penjemputan')
                    ->options([
                        'waiting_payment' => 'Menunggu Pembayaran',
                        'awaiting_pickup' => 'Menunggu Data Penjemput',
                        'pickup_arrived' => 'Penjemput Datang',
                        'completed' => 'Selesai',
                        'payment_failed' => 'Pembayaran Gagal',
                        'payment_expired' => 'Pembayaran Expired',
                    ]),
                DateTimePicker::make('packed_at')->label('Packing Pada'),
                TextInput::make('packing_location')->label('Lokasi Packing'),
                DateTimePicker::make('buyer_pickup_submitted_at')->label('Data Penjemput Diisi'),
                DateTimePicker::make('pickup_verified_at')->label('Pickup Diverifikasi'),
                Textarea::make('buyer_review')->label('Review Pembeli')->maxLength(1000)->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_code')->label('Order ID')->searchable()->placeholder('-')->sortable(),
                TextColumn::make('ikan.nama_ikan')->label('Lot')->searchable(),
                TextColumn::make('pemenang.name')->label('Pembeli')->searchable(),
                TextColumn::make('harga_final')
                    ->label('Harga Final')
                    ->formatStateUsing(fn ($state) => formatRupiah($state))
                    ->sortable(),
                BadgeColumn::make('status')
                    ->label('Order')
                    ->formatStateUsing(fn (?string $state): string => transactionStatusLabel($state))
                    ->colors([
                        'warning' => 'menunggu_bayar',
                        'info' => 'proses',
                        'success' => 'lunas',
                        'danger' => fn ($state) => in_array($state, ['gagal', 'kadaluarsa'], true),
                    ]),
                BadgeColumn::make('payment_status')
                    ->label('Payment')
                    ->formatStateUsing(fn (?string $state): string => paymentStatusLabel($state))
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'paid',
                        'danger' => fn ($state) => in_array($state, ['failed', 'expired', 'cancelled'], true),
                        'info' => 'refunded',
                    ]),
                BadgeColumn::make('pickup_status')
                    ->label('Penjemputan')
                    ->formatStateUsing(fn (?string $state): string => pickupStatusLabel($state))
                    ->colors([
                        'gray' => 'waiting_payment',
                        'warning' => 'awaiting_pickup',
                        'info' => 'pickup_arrived',
                        'success' => 'completed',
                        'danger' => fn ($state) => in_array($state, ['payment_failed', 'payment_expired'], true),
                    ]),
                BadgeColumn::make('fulfillment_state')
                    ->label('Progress')
                    ->state(fn (Transaksi $record): string => $record->buyerProgressKey())
                    ->formatStateUsing(fn (string $state, Transaksi $record): string => $record->buyerProgressLabel())
                    ->colors([
                        'warning' => 'menunggu_pembayaran',
                        'info' => fn (string $state): bool => in_array($state, ['diproses_penjual', 'menunggu_penjemput'], true),
                        'primary' => fn (string $state): bool => in_array($state, ['siap_dijemput', 'dalam_penjemputan'], true),
                        'success' => 'selesai',
                        'danger' => fn (string $state): bool => in_array($state, ['gagal', 'kadaluarsa'], true),
                        'gray' => 'komplain',
                    ])
                    ->placeholder('-'),
                TextColumn::make('bayar_sebelum')->label('Deadline Bayar')->dateTime('d M Y H:i')->placeholder('-')->sortable(),
                TextColumn::make('dibayar_pada')->label('Dibayar')->dateTime('d M Y H:i')->placeholder('-')->sortable(),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y H:i')->sortable(),
            ])
            ->groups([
                Group::make('status')
                    ->label('Kelompok')
                    ->collapsible()
                    ->orderQueryUsing(
                        fn (Builder $query, string $direction) => $query->orderByRaw(
                            "FIELD(status, 'menunggu_bayar', 'lunas', 'proses', 'gagal', 'kadaluarsa') " . (strtolower($direction) === 'desc' ? 'DESC' : 'ASC')
                        )
                    )
                    ->getTitleFromRecordUsing(fn (Transaksi $record): string => match ($record->status) {
                        'menunggu_bayar' => 'Menunggu Pembayaran',
                        'lunas' => 'Sudah Dibayar',
                        'proses' => 'Sedang Diproses',
                        'gagal' => 'Gagal',
                        'kadaluarsa' => 'Kadaluarsa',
                        default => transactionStatusLabel($record->status),
                    }),
            ])
            ->defaultGroup('status')
            ->actions([
                Actions\EditAction::make()
                    ->visible(fn (): bool => (bool) auth()->user()?->canAdmin('ops')),
                Actions\ViewAction::make(),
                Actions\DeleteAction::make()
                    ->visible(fn (): bool => (bool) auth()->user()?->isSuperAdmin()),
                Actions\Action::make('sync_tripay')
                    ->label('Sinkronkan TriPay')
                    ->color('info')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (Transaksi $record): bool => (bool) auth()->user()?->canAdmin('finance')
                        && (string) $record->payment_status === 'pending'
                        && $record->paymentAttempts()->where('status_code', 'pending')->whereNotNull('provider_transaction_id')->exists())
                    ->requiresConfirmation()
                    ->action(function (Transaksi $record): void {
                        try {
                            $result = app(PembayaranService::class)->refreshPendingAttempt($record);

                            Notification::make()
                                ->title('Sinkronisasi TriPay selesai')
                                ->body('Status: ' . (string) ($result['payment_status'] ?? $result['status'] ?? 'ok'))
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            report($e);

                            Notification::make()
                                ->title('Sinkronisasi TriPay gagal')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Actions\Action::make('tandai_lunas')
                    ->label('Tandai Lunas')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (Transaksi $record): bool => (bool) auth()->user()?->canAdmin('finance')
                        && (string) $record->payment_status !== 'paid')
                    ->requiresConfirmation()
                    ->action(function (Transaksi $record): void {
                        $record->markPaid('admin_manual');
                        $record->save();

                        $record->ikan?->update([
                            'status' => 'terbayar',
                            'hard_stop_reason' => null,
                        ]);

                        app(TransaksiFulfillmentService::class)->markPaid($record, 'admin_manual');
                        app(NotificationOutboxService::class)->processPending(100);

                        AuditService::log('admin', auth()->id(), 'transaction.mark_paid', 'transaksis', (int) $record->id);
                    }),
                Actions\Action::make('batalkan')
                    ->label('Batalkan')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->visible(fn (Transaksi $record): bool => (bool) auth()->user()?->canAdmin('ops')
                        && in_array((string) $record->status, ['menunggu_bayar', 'proses'], true))
                    ->requiresConfirmation()
                    ->action(function (Transaksi $record): void {
                        $record->markPaymentFailed();
                        $record->payment_status = 'cancelled';
                        $record->save();

                        app(TransaksiFulfillmentService::class)->markFailed(
                            $record,
                            'ADMIN_CANCELLED',
                            'Transaksi dibatalkan oleh admin.',
                            TransaksiFulfillmentService::ACTOR_ADMIN,
                            auth()->id(),
                        );

                        AuditService::log('admin', auth()->id(), 'transaction.cancelled', 'transaksis', (int) $record->id);
                    }),
                Actions\Action::make('override_status')
                    ->label('Override Status')
                    ->color('warning')
                    ->icon('heroicon-o-shield-exclamation')
                    ->visible(fn (): bool => (bool) auth()->user()?->isSuperAdmin())
                    ->form([
                        Select::make('status')
                            ->label('Status Baru')
                            ->options([
                                'menunggu_bayar' => 'Menunggu Bayar',
                                'lunas' => 'Lunas',
                                'gagal' => 'Gagal',
                                'kadaluarsa' => 'Kadaluarsa',
                            ])
                            ->required(),
                        Select::make('payment_status')
                            ->label('Status Payment')
                            ->options([
                                'pending' => 'Pending',
                                'paid' => 'Paid',
                                'failed' => 'Failed',
                                'expired' => 'Expired',
                                'cancelled' => 'Cancelled',
                                'refunded' => 'Refunded',
                            ])
                            ->required(),
                        Textarea::make('reason')
                            ->label('Alasan Override')
                            ->required()
                            ->maxLength(500),
                    ])
                    ->requiresConfirmation()
                    ->action(function (Transaksi $record, array $data): void {
                        $oldStatus = $record->status;
                        $newStatus = (string) $data['status'];

                        $record->status = $newStatus;
                        $record->payment_status = (string) $data['payment_status'];
                        if ($newStatus === 'lunas' && ! $record->dibayar_pada) {
                            $record->dibayar_pada = now();
                        }
                        if (in_array($newStatus, ['gagal', 'kadaluarsa'], true)) {
                            $record->dibayar_pada = null;
                        }
                        $record->save();

                        $ikan = $record->ikan;
                        if (! $ikan) {
                            return;
                        }

                        $fromState = $ikan->auction_state;
                        $toState = match ($newStatus) {
                            'menunggu_bayar' => 'MENUNGGU_PEMBAYARAN',
                            'lunas' => 'DIBAYAR',
                            'kadaluarsa' => 'KADALUARSA',
                            'gagal' => 'GAGAL_TOTAL',
                            default => $ikan->auction_state,
                        };

                        $ikan->auction_state = $toState;
                        if ($newStatus === 'lunas') {
                            $ikan->status = 'terbayar';
                            $ikan->hard_stop_reason = null;
                        }
                        if (in_array($newStatus, ['kadaluarsa', 'gagal'], true)) {
                            $ikan->status = 'selesai';
                        }

                        $ikan->state_version = ((int) $ikan->state_version) + 1;
                        $ikan->save();

                        AuctionStateLog::create([
                            'ikan_id' => $ikan->id,
                            'from_state' => $fromState,
                            'to_state' => $toState,
                            'event_name' => 'admin_override_status',
                            'actor_type' => 'admin',
                            'actor_id' => auth()->id(),
                            'metadata' => [
                                'old_transaksi_status' => $oldStatus,
                                'new_transaksi_status' => $newStatus,
                                'new_payment_status' => $record->payment_status,
                                'reason' => (string) ($data['reason'] ?? ''),
                            ],
                            'created_at' => now(),
                        ]);

                        AuditService::log('admin', auth()->id(), 'transaction.override_status', 'transaksis', (int) $record->id, [
                            'old_status' => $oldStatus,
                            'new_status' => $newStatus,
                            'payment_status' => $record->payment_status,
                        ]);
                    }),
                Actions\Action::make('buka_sengketa')
                    ->label('Buka Sengketa')
                    ->color('gray')
                    ->icon('heroicon-o-exclamation-circle')
                    ->visible(fn (Transaksi $record): bool => (bool) auth()->user()?->canAdmin('ops')
                        && in_array((string) $record->fulfillment_state, ['DIBAYAR', 'DIPROSES_PENJUAL', 'DIKIRIM'], true))
                    ->form([
                        Textarea::make('reason')
                            ->label('Alasan Sengketa')
                            ->required()
                            ->maxLength(500),
                    ])
                    ->requiresConfirmation()
                    ->action(function (Transaksi $record, array $data): void {
                        app(TransaksiFulfillmentService::class)->openDispute(
                            $record,
                            'ADMIN_MANUAL_DISPUTE',
                            (string) ($data['reason'] ?? 'Sengketa dibuka admin.'),
                            TransaksiFulfillmentService::ACTOR_ADMIN,
                            auth()->id(),
                        );

                        app(NotificationOutboxService::class)->processPending(100);
                    }),
                Actions\Action::make('resolve_sengketa')
                    ->label('Resolve Sengketa')
                    ->color('success')
                    ->icon('heroicon-o-scale')
                    ->visible(fn (Transaksi $record): bool => (bool) auth()->user()?->canAdmin('ops')
                        && $record->fulfillment_state === 'DISENGKETAKAN')
                    ->form([
                        Select::make('resolution')
                            ->label('Keputusan')
                            ->options([
                                'completed' => 'Selesaikan Transaksi',
                                'failed' => 'Gagalkan Transaksi',
                            ])
                            ->required(),
                        Textarea::make('note')
                            ->label('Catatan Resolusi')
                            ->required()
                            ->maxLength(500),
                    ])
                    ->requiresConfirmation()
                    ->action(function (Transaksi $record, array $data): void {
                        app(TransaksiFulfillmentService::class)->resolveOpenDisputeByAdmin(
                            $record,
                            (int) auth()->id(),
                            (string) ($data['resolution'] ?? ''),
                            (string) ($data['note'] ?? ''),
                        );

                        app(NotificationOutboxService::class)->processPending(100);
                    }),
                Actions\Action::make('buat_settlement_seller')
                    ->label('Buat Settlement Seller')
                    ->color('info')
                    ->icon('heroicon-o-banknotes')
                    ->visible(function (Transaksi $record): bool {
                        if (! auth()->user()?->canAdmin('finance')) {
                            return false;
                        }

                        if ($record->sellerSettlement()->exists()) {
                            return false;
                        }

                        if (! $record->isCompletedForSettlement()) {
                            return false;
                        }

                        $sellerProfile = $record->ikan?->user?->sellerProfile;

                        return $sellerProfile
                            && filled($sellerProfile->bank_name)
                            && filled($sellerProfile->bank_account_number)
                            && filled($sellerProfile->bank_account_name);
                    })
                    ->requiresConfirmation()
                    ->action(function (Transaksi $record): void {
                        $record->loadMissing('ikan.user.sellerProfile');

                        $seller = $record->ikan?->user;
                        $sellerProfile = $seller?->sellerProfile;

                        if (! $seller || ! $sellerProfile) {
                            return;
                        }

                        SellerSettlement::query()->firstOrCreate(
                            ['transaksi_id' => $record->id],
                            [
                                'seller_id' => $seller->id,
                                'amount' => $record->harga_final,
                                'status' => 'pending',
                                'bank_name' => (string) $sellerProfile->bank_name,
                                'bank_account_number' => (string) $sellerProfile->bank_account_number,
                                'bank_account_name' => (string) $sellerProfile->bank_account_name,
                                'created_by_id' => auth()->id(),
                                'updated_by_id' => auth()->id(),
                            ]
                        );
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransaksis::route('/'),
            'create' => Pages\CreateTransaksi::route('/create'),
            'view' => Pages\ViewTransaksi::route('/{record}'),
            'edit' => Pages\EditTransaksi::route('/{record}/edit'),
        ];
    }
}
