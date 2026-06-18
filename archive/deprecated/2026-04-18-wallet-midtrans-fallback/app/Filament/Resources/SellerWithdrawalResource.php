<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SellerWithdrawalResource\Pages;
use App\Models\SellerWithdrawal;
use App\Services\NotificationOutboxService;
use App\Services\SellerWalletService;
use BackedEnum;
use Filament\Actions;
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

class SellerWithdrawalResource extends Resource
{
    protected static ?string $model = SellerWithdrawal::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?string $modelLabel = 'Pencairan Seller';

    protected static ?string $pluralModelLabel = 'Pencairan Seller';

    protected static ?string $slug = 'seller-withdrawals';

    protected static ?int $navigationSort = 4;

    public static function getNavigationBadge(): ?string
    {
        $count = SellerWithdrawal::query()
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
            ->with(['user', 'reviewedBy', 'paidBy'])
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
                    ->label('Penjual')
                    ->description(fn (SellerWithdrawal $record): ?string => $record->user?->email)
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
                        'info' => 'approved',
                        'success' => 'paid',
                        'danger' => 'rejected',
                    ]),
                TextColumn::make('bank_name')
                    ->label('Bank')
                    ->description(fn (SellerWithdrawal $record): string => $record->account_holder_name . ' · ' . $record->account_number)
                    ->searchable(),
                TextColumn::make('reviewedBy.name')
                    ->label('Reviewer')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('paidBy.name')
                    ->label('Payout By')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('transfer_reference')
                    ->label('Ref Transfer')
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('ledger_entries_count')
                    ->label('Ledger')
                    ->sortable(),
                TextColumn::make('requested_at')
                    ->label('Diminta')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('approved_at')
                    ->label('Approved')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('paid_at')
                    ->label('Paid')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('review_note')
                    ->label('Catatan')
                    ->limit(60)
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'paid' => 'Paid',
                        'rejected' => 'Rejected',
                    ]),
                Filter::make('needs_action')
                    ->label('Perlu Tindakan')
                    ->query(fn (Builder $query): Builder => $query->whereIn('status', ['pending', 'approved'])),
            ])
            ->actions([
                Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('info')
                    ->visible(fn (SellerWithdrawal $record): bool => $record->isPending())
                    ->form([
                        Textarea::make('review_note')
                            ->label('Catatan Approval')
                            ->rows(3)
                            ->default('Withdraw seller disetujui admin dan siap ditransfer.'),
                    ])
                    ->requiresConfirmation()
                    ->action(function (SellerWithdrawal $record, array $data): void {
                        app(SellerWalletService::class)->approveWithdrawal(
                            $record,
                            (int) auth('admin')->id(),
                            (string) ($data['review_note'] ?? '')
                        );

                        app(NotificationOutboxService::class)->processPending(100);
                    }),
                Actions\Action::make('mark_paid')
                    ->label('Tandai Sudah Dibayar')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (SellerWithdrawal $record): bool => $record->isApproved())
                    ->form([
                        TextInput::make('transfer_reference')
                            ->label('Referensi Transfer')
                            ->maxLength(120)
                            ->placeholder('opsional'),
                        Textarea::make('review_note')
                            ->label('Catatan Payout')
                            ->rows(3)
                            ->default('Payout seller sudah diproses admin.'),
                    ])
                    ->requiresConfirmation()
                    ->action(function (SellerWithdrawal $record, array $data): void {
                        app(SellerWalletService::class)->markWithdrawalPaid(
                            $record,
                            (int) auth('admin')->id(),
                            isset($data['transfer_reference']) ? (string) $data['transfer_reference'] : null,
                            (string) ($data['review_note'] ?? '')
                        );

                        app(NotificationOutboxService::class)->processPending(100);
                    }),
                Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (SellerWithdrawal $record): bool => in_array((string) $record->status, ['pending', 'approved'], true))
                    ->form([
                        Textarea::make('review_note')
                            ->label('Alasan Penolakan')
                            ->rows(3)
                            ->required(),
                    ])
                    ->requiresConfirmation()
                    ->action(function (SellerWithdrawal $record, array $data): void {
                        app(SellerWalletService::class)->rejectWithdrawal(
                            $record,
                            (int) auth('admin')->id(),
                            (string) ($data['review_note'] ?? '')
                        );

                        app(NotificationOutboxService::class)->processPending(100);
                    }),
            ])
            ->defaultSort('requested_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSellerWithdrawals::route('/'),
        ];
    }
}
