<?php

namespace App\Filament\Resources\TransactionDisputeResource\Pages;

use App\Filament\Resources\TransactionDisputeResource;
use Filament\Resources\Pages\ListRecords;

class ListTransactionDisputes extends ListRecords
{
    protected static string $resource = TransactionDisputeResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
