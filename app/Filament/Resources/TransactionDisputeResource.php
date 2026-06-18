<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionDisputeResource\Pages;
use App\Models\TransactionDispute;
use App\Services\NotificationOutboxService;
use App\Services\TransaksiFulfillmentService;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class TransactionDisputeResource extends Resource
{
    protected static ?string $model = TransactionDispute::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static string|UnitEnum|null $navigationGroup = 'Monitoring';

    protected static ?string $modelLabel = 'Sengketa Transaksi';

    protected static ?string $pluralModelLabel = 'Sengketa Transaksi';

    protected static ?int $navigationSort = 13;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->canAdmin('support');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['transaksi.ikan', 'buyer', 'seller']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transaksi_id')->label('Transaksi ID')->sortable(),
                TextColumn::make('transaksi.ikan.nama_ikan')->label('Lot')->searchable(),
                TextColumn::make('buyer.name')->label('Pembeli')->searchable(),
                TextColumn::make('seller.name')->label('Penjual')->searchable(),
                BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'open',
                        'success' => 'resolved_completed',
                        'danger' => 'resolved_failed',
                        'gray' => 'rejected',
                    ]),
                TextColumn::make('complaint_reason')->label('Alasan')->searchable(),
                TextColumn::make('complaint_detail')->label('Detail')->limit(60)->toggleable(),
                TextColumn::make('resolution_note')->label('Resolusi')->limit(60)->toggleable(),
                TextColumn::make('opened_at')->label('Dibuka')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('resolved_at')->label('Diselesaikan')->dateTime('d M Y H:i')->placeholder('-')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'open' => 'Open',
                    'resolved_completed' => 'Resolved Completed',
                    'resolved_failed' => 'Resolved Failed',
                    'rejected' => 'Rejected',
                ]),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\Action::make('resolve_completed')
                    ->label('Resolve: Selesai')
                    ->color('success')
                    ->visible(fn (TransactionDispute $record): bool => (bool) auth()->user()?->canAdmin('ops')
                        && $record->status === 'open')
                    ->form([
                        Textarea::make('note')->label('Catatan Admin')->required()->maxLength(500),
                    ])
                    ->requiresConfirmation()
                    ->action(function (TransactionDispute $record, array $data): void {
                        app(TransaksiFulfillmentService::class)->resolveOpenDisputeByAdmin(
                            $record->transaksi,
                            (int) auth()->id(),
                            'completed',
                            (string) ($data['note'] ?? ''),
                        );

                        app(NotificationOutboxService::class)->processPending(100);
                    }),
                Actions\Action::make('resolve_failed')
                    ->label('Resolve: Gagal')
                    ->color('danger')
                    ->visible(fn (TransactionDispute $record): bool => (bool) auth()->user()?->canAdmin('ops')
                        && $record->status === 'open')
                    ->form([
                        Textarea::make('note')->label('Catatan Admin')->required()->maxLength(500),
                    ])
                    ->requiresConfirmation()
                    ->action(function (TransactionDispute $record, array $data): void {
                        app(TransaksiFulfillmentService::class)->resolveOpenDisputeByAdmin(
                            $record->transaksi,
                            (int) auth()->id(),
                            'failed',
                            (string) ($data['note'] ?? ''),
                        );

                        app(NotificationOutboxService::class)->processPending(100);
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactionDisputes::route('/'),
            'view' => Pages\ViewTransactionDispute::route('/{record}'),
        ];
    }
}
