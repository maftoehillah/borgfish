<?php

namespace App\Filament\Resources\TransactionStateLogResource\Pages;

use App\Filament\Resources\TransactionStateLogResource;
use Filament\Resources\Pages\ListRecords;

class ListTransactionStateLogs extends ListRecords
{
    protected static string $resource = TransactionStateLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
