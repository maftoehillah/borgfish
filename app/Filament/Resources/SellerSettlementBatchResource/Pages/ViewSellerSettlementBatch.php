<?php

namespace App\Filament\Resources\SellerSettlementBatchResource\Pages;

use App\Filament\Resources\SellerSettlementBatchResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSellerSettlementBatch extends ViewRecord
{
    protected static string $resource = SellerSettlementBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download_proof')
                ->label('Buka Bukti Transfer')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->visible(fn (): bool => filled($this->record->transfer_proof_path))
                ->url(fn (): string => asset('storage/' . $this->record->transfer_proof_path), shouldOpenInNewTab: true),
        ];
    }
}
