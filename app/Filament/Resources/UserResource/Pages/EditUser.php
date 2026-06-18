<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\Concerns\HasResetUserDataActions;
use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    use HasResetUserDataActions;

    protected static string $resource = UserResource::class;
}
