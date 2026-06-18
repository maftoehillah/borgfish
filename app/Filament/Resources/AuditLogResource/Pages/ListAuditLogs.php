<?php

namespace App\Filament\Resources\AuditLogResource\Pages;

use App\Filament\Resources\AuditLogResource;
use App\Models\AuditLog;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;

class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('delete_old_logs')
                ->label('Hapus Log Lama')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->form([
                    Select::make('older_than_days')
                        ->label('Pilih log yang akan dihapus')
                        ->options([
                            7 => 'Lebih lama dari 7 hari',
                            30 => 'Lebih lama dari 30 hari',
                            90 => 'Lebih lama dari 90 hari',
                            180 => 'Lebih lama dari 180 hari',
                            365 => 'Lebih lama dari 1 tahun',
                            'all' => 'Semua log',
                        ])
                        ->default(90)
                        ->helperText('Pilih "Semua log" hanya saat maintenance karena data audit akan kosong.')
                        ->required(),
                ])
                ->requiresConfirmation()
                ->modalHeading('Hapus log sistem?')
                ->modalDescription('Log sesuai pilihan akan dihapus permanen dari database. Pastikan pilihan sudah benar sebelum melanjutkan.')
                ->action(function (array $data): void {
                    $selectedRange = $data['older_than_days'] ?? 90;

                    if ($selectedRange === 'all') {
                        AuditLog::query()->delete();

                        return;
                    }

                    $days = max(1, (int) $selectedRange);

                    AuditLog::query()
                        ->where('created_at', '<', now()->subDays($days))
                        ->delete();
                }),
        ];
    }
}
