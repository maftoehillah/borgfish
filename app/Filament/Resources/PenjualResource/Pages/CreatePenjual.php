<?php

namespace App\Filament\Resources\PenjualResource\Pages;

use App\Filament\Resources\PenjualResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreatePenjual extends CreateRecord
{
    protected static string $resource = PenjualResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['password'] = Hash::make(Str::random(32));

        return $data;
    }
}
