<?php

namespace App\Filament\Resources\PembeliResource\Pages;

use App\Filament\Resources\Concerns\HasResetUserDataActions;
use App\Filament\Resources\PembeliResource;
use Filament\Resources\Pages\EditRecord;

class EditPembeli extends EditRecord
{
    use HasResetUserDataActions;

    protected static string $resource = PembeliResource::class;
}
