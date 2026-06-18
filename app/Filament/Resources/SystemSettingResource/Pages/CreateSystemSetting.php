<?php

namespace App\Filament\Resources\SystemSettingResource\Pages;

use App\Filament\Pages\SystemSettings;
use App\Filament\Resources\SystemSettingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSystemSetting extends CreateRecord
{
    protected static string $resource = SystemSettingResource::class;

    public function mount(): void
    {
        $this->redirect(SystemSettings::getUrl());
    }
}
