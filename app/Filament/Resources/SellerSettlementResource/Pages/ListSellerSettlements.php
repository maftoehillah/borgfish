<?php

namespace App\Filament\Resources\SellerSettlementResource\Pages;

use App\Filament\Resources\SellerSettlementResource;
use App\Models\SellerSettlement;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListSellerSettlements extends ListRecords
{
    protected static string $resource = SellerSettlementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_csv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->form([
                    Select::make('status')
                        ->label('Status')
                        ->options([
                            'all' => 'Semua Status',
                            'pending' => 'Pending Review',
                            'ready_to_pay' => 'Siap Dibayar',
                            'held' => 'Ditahan',
                            'paid' => 'Sudah Dibayar',
                            'cancelled' => 'Dibatalkan',
                        ])
                        ->default('all')
                        ->required(),
                    DatePicker::make('from_date')
                        ->label('Dari Tanggal'),
                    DatePicker::make('to_date')
                        ->label('Sampai Tanggal'),
                ])
                ->action(fn (array $data): StreamedResponse => $this->streamCsvExport($data)),
            Action::make('export_summary_csv')
                ->label('Export Ringkasan Seller')
                ->icon('heroicon-o-document-chart-bar')
                ->color('info')
                ->form([
                    Select::make('status')
                        ->label('Status')
                        ->options([
                            'all' => 'Semua Status',
                            'pending' => 'Pending Review',
                            'ready_to_pay' => 'Siap Dibayar',
                            'held' => 'Ditahan',
                            'paid' => 'Sudah Dibayar',
                            'cancelled' => 'Dibatalkan',
                        ])
                        ->default('paid')
                        ->required(),
                    DatePicker::make('from_date')
                        ->label('Dari Tanggal'),
                    DatePicker::make('to_date')
                        ->label('Sampai Tanggal'),
                ])
                ->action(fn (array $data): StreamedResponse => $this->streamSellerSummaryCsvExport($data)),
        ];
    }

    private function streamCsvExport(array $data): StreamedResponse
    {
        $fileName = 'seller-settlements-' . now()->format('Ymd-His') . '.csv';
        $query = $this->buildSettlementExportQuery($data)
            ->with(['transaksi.ikan', 'seller'])
            ->orderByDesc('created_at');

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'Order ID',
                'Lot',
                'Seller',
                'Nominal',
                'Status',
                'Bank',
                'Nomor Rekening',
                'Nama Rekening',
                'Referensi Transfer',
                'Dibayar Pada',
                'Dibuat Pada',
            ]);

            $query->chunk(200, function ($records) use ($handle): void {
                foreach ($records as $record) {
                    fputcsv($handle, [
                        (string) ($record->transaksi?->order_code ?? ''),
                        (string) ($record->transaksi?->ikan?->nama_ikan ?? ''),
                        (string) ($record->seller?->name ?? ''),
                        (string) (float) $record->amount,
                        (string) $record->status,
                        (string) $record->bank_name,
                        (string) $record->bank_account_number,
                        (string) $record->bank_account_name,
                        (string) ($record->transfer_reference ?? ''),
                        (string) ($record->paid_at?->format('Y-m-d H:i:s') ?? ''),
                        (string) ($record->created_at?->format('Y-m-d H:i:s') ?? ''),
                    ]);
                }
            });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function streamSellerSummaryCsvExport(array $data): StreamedResponse
    {
        $fileName = 'seller-settlement-summary-' . now()->format('Ymd-His') . '.csv';

        $query = $this->buildSettlementExportQuery($data)
            ->selectRaw('seller_id, COUNT(*) as settlement_count, SUM(amount) as total_amount')
            ->with('seller')
            ->groupBy('seller_id')
            ->orderByDesc('total_amount');

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'Seller',
                'Email',
                'WhatsApp',
                'Jumlah Settlement',
                'Total Nominal',
            ]);

            $query->chunk(200, function ($records) use ($handle): void {
                foreach ($records as $record) {
                    fputcsv($handle, [
                        (string) ($record->seller?->name ?? ''),
                        (string) ($record->seller?->email ?? ''),
                        (string) ($record->seller?->whatsapp_number ?? ''),
                        (string) ($record->settlement_count ?? 0),
                        (string) (float) ($record->total_amount ?? 0),
                    ]);
                }
            });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function buildSettlementExportQuery(array $data): Builder
    {
        $status = (string) ($data['status'] ?? 'all');
        $fromDate = $data['from_date'] ?? null;
        $toDate = $data['to_date'] ?? null;
        $dateColumn = $status === 'paid' ? 'paid_at' : 'created_at';

        return SellerSettlement::query()
            ->when(
                $status !== 'all',
                fn (Builder $builder): Builder => $builder->where('status', $status)
            )
            ->when(
                filled($fromDate),
                fn (Builder $builder): Builder => $builder->whereDate($dateColumn, '>=', (string) $fromDate)
            )
            ->when(
                filled($toDate),
                fn (Builder $builder): Builder => $builder->whereDate($dateColumn, '<=', (string) $toDate)
            );
    }
}
