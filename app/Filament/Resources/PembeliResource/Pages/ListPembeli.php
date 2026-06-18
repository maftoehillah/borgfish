<?php

namespace App\Filament\Resources\PembeliResource\Pages;

use App\Filament\Resources\PembeliResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPembeli extends ListRecords
{
    protected static string $resource = PembeliResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
