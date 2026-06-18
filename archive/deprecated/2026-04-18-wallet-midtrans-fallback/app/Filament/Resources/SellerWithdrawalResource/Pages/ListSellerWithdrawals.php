<?php

namespace App\Filament\Resources\SellerWithdrawalResource\Pages;

use App\Filament\Resources\SellerWithdrawalResource;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;

class ListSellerWithdrawals extends ListRecords
{
    protected static string $resource = SellerWithdrawalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_withdrawals_csv')
                ->label('Export Withdraw CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->form([
                    DatePicker::make('from_date')
                        ->label('Dari Tanggal')
                        ->default(today())
                        ->required(),
                    DatePicker::make('to_date')
                        ->label('Sampai Tanggal')
                        ->default(today())
                        ->required(),
                    Select::make('status')
                        ->label('Status')
                        ->options([
                            '' => 'Semua Status',
                            'pending' => 'Pending',
                            'approved' => 'Approved',
                            'paid' => 'Paid',
                            'rejected' => 'Rejected',
                        ])
                        ->default(''),
                ])
                ->action(function (array $data): void {
                    $params = array_filter([
                        'from_date' => $data['from_date'] ?? null,
                        'to_date' => $data['to_date'] ?? null,
                        'status' => $data['status'] ?? null,
                    ], fn ($value) => $value !== null && $value !== '');

                    $this->redirect(route('admin.exports.seller-withdrawals', $params), navigate: false);
                }),
            Action::make('export_seller_ledgers_csv')
                ->label('Export Ledger Seller CSV')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->form([
                    DatePicker::make('from_date')
                        ->label('Dari Tanggal')
                        ->default(today())
                        ->required(),
                    DatePicker::make('to_date')
                        ->label('Sampai Tanggal')
                        ->default(today())
                        ->required(),
                    Select::make('entry_scope')
                        ->label('Scope Ledger')
                        ->options([
                            'all' => 'Semua mutasi seller',
                            'escrow_only' => 'Hanya dana masuk escrow',
                            'withdraw_only' => 'Hanya mutasi withdraw',
                        ])
                        ->default('all')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $params = array_filter([
                        'from_date' => $data['from_date'] ?? null,
                        'to_date' => $data['to_date'] ?? null,
                        'entry_scope' => $data['entry_scope'] ?? 'all',
                    ], fn ($value) => $value !== null && $value !== '');

                    $this->redirect(route('admin.exports.seller-ledgers', $params), navigate: false);
                }),
        ];
    }
}
