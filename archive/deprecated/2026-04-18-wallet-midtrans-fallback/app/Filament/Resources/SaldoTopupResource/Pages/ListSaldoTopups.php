<?php

namespace App\Filament\Resources\SaldoTopupResource\Pages;

use App\Filament\Resources\SaldoTopupResource;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\ListRecords;

class ListSaldoTopups extends ListRecords
{
    protected static string $resource = SaldoTopupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_topups_csv')
                ->label('Export Top Up CSV')
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
                            'success' => 'Berhasil',
                            'failed' => 'Gagal',
                            'expired' => 'Kadaluarsa',
                        ])
                        ->default(''),
                    Toggle::make('needs_reconciliation')
                        ->label('Hanya yang perlu rekonsiliasi')
                        ->default(false),
                ])
                ->action(function (array $data): void {
                    $params = array_filter([
                        'from_date' => $data['from_date'] ?? null,
                        'to_date' => $data['to_date'] ?? null,
                        'status' => $data['status'] ?? null,
                        'needs_reconciliation' => ! empty($data['needs_reconciliation']) ? 1 : null,
                    ], fn ($value) => $value !== null && $value !== '');

                    $this->redirect(route('admin.exports.saldo-topups', $params), navigate: false);
                }),
            Action::make('export_ledgers_csv')
                ->label('Export Ledger CSV')
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
                            'topup_only' => 'Hanya ledger top up',
                            'all' => 'Semua mutasi saldo',
                        ])
                        ->default('topup_only')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $params = array_filter([
                        'from_date' => $data['from_date'] ?? null,
                        'to_date' => $data['to_date'] ?? null,
                        'entry_scope' => $data['entry_scope'] ?? 'topup_only',
                    ], fn ($value) => $value !== null && $value !== '');

                    $this->redirect(route('admin.exports.saldo-ledgers', $params), navigate: false);
                }),
        ];
    }
}
