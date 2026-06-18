<?php

namespace App\Filament\Resources\PembeliResource\Pages;

use App\Filament\Resources\PembeliResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreatePembeli extends CreateRecord
{
    protected static string $resource = PembeliResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['password'] = Hash::make(Str::random(32));

        return $data;
    }
}
