<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentAttemptResource\Pages;
use App\Models\PaymentAttempt;
use App\Services\PembayaranService;
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
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PaymentAttemptResource extends Resource
{
    protected static ?string $model = PaymentAttempt::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|UnitEnum|null $navigationGroup = 'Transaksi';

    protected static ?string $modelLabel = 'Payment Attempt';

    protected static ?string $pluralModelLabel = 'Payment Attempts';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->canAdmin('support');
    }

    public static function canEdit(Model $record): bool
    {
        return (bool) auth()->user()?->canAdmin('finance');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('payment_code')->label('Payment Internal ID')->readOnly(),
                TextInput::make('provider')->label('Provider')->readOnly(),
                TextInput::make('provider_transaction_id')->label('Provider Transaction ID')->readOnly(),
                Select::make('status_code')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'failed' => 'Failed',
                        'expired' => 'Expired',
                        'cancelled' => 'Cancelled',
                        'refunded' => 'Refunded',
                    ])
                    ->required(),
                TextInput::make('amount_due')->label('Tagihan')->numeric()->prefix('Rp'),
                TextInput::make('payment_method_code')->label('Kode Metode'),
                TextInput::make('payment_method_name')->label('Nama Metode'),
                TextInput::make('checkout_url')->label('Checkout URL')->columnSpanFull(),
                DateTimePicker::make('checkout_expires_at')->label('Checkout Expired'),
                DateTimePicker::make('paid_at')->label('Paid At'),
                DateTimePicker::make('expired_at')->label('Expired At'),
                DateTimePicker::make('failed_at')->label('Failed At'),
                Textarea::make('callback_payload')
                    ->label('Callback Payload')
                    ->formatStateUsing(fn ($state): string => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : (string) $state)
                    ->disabled()
                    ->dehydrated(false)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payment_code')->label('Payment ID')->searchable()->sortable(),
                TextColumn::make('transaksi.order_code')->label('Order ID')->searchable()->placeholder('-'),
                TextColumn::make('ikan.nama_ikan')->label('Lot')->searchable()->toggleable(),
                TextColumn::make('bidder.name')->label('Pembeli')->searchable(),
                TextColumn::make('provider')->label('Provider')->badge(),
                BadgeColumn::make('status_code')
                    ->label('Status')
                    ->formatStateUsing(fn (?string $state): string => paymentStatusLabel($state))
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'paid',
                        'danger' => fn ($state) => in_array($state, ['failed', 'expired', 'cancelled'], true),
                        'info' => 'refunded',
                    ]),
                TextColumn::make('amount_due')->label('Tagihan')->formatStateUsing(fn ($state) => formatRupiah($state))->sortable(),
                TextColumn::make('payment_method_code')->label('Metode')->placeholder('-'),
                TextColumn::make('provider_transaction_id')->label('Provider ID')->searchable()->placeholder('-')->toggleable(),
                TextColumn::make('callback_processed_at')->label('Callback')->dateTime('d M Y H:i')->placeholder('-')->toggleable(),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y H:i')->sortable(),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make()
                    ->visible(fn (): bool => (bool) auth()->user()?->canAdmin('finance')),
                Actions\Action::make('sync_tripay')
                    ->label('Sinkronkan TriPay')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->visible(fn (PaymentAttempt $record): bool => (bool) auth()->user()?->canAdmin('finance')
                        && (string) $record->provider === 'tripay'
                        && (string) $record->status_code === 'pending'
                        && filled($record->provider_transaction_id))
                    ->requiresConfirmation()
                    ->action(function (PaymentAttempt $record): void {
                        try {
                            $result = app(PembayaranService::class)->refreshPaymentAttempt($record);

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
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentAttempts::route('/'),
            'view' => Pages\ViewPaymentAttempt::route('/{record}'),
            'edit' => Pages\EditPaymentAttempt::route('/{record}/edit'),
        ];
    }
}
