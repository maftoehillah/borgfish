<?php

namespace App\Filament\Resources\AuctionFallbackHistoryResource\Pages;

use App\Filament\Resources\AuctionFallbackHistoryResource;
use Filament\Resources\Pages\ListRecords;

class ListAuctionFallbackHistories extends ListRecords
{
    protected static string $resource = AuctionFallbackHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
