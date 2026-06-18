<?php

namespace App\Filament\Resources\PenjualResource\Pages;

use App\Filament\Resources\PenjualResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPenjual extends ListRecords
{
    protected static string $resource = PenjualResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
