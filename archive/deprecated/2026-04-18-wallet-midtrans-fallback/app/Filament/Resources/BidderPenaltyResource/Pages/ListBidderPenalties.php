<?php

namespace App\Filament\Resources\BidderPenaltyResource\Pages;

use App\Filament\Resources\BidderPenaltyResource;
use Filament\Resources\Pages\ListRecords;

class ListBidderPenalties extends ListRecords
{
    protected static string $resource = BidderPenaltyResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
