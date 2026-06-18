<?php

namespace App\Filament\Resources\SystemSettingResource\Pages;

use App\Filament\Pages\SystemSettings;
use App\Filament\Resources\SystemSettingResource;
use Filament\Resources\Pages\EditRecord;

class EditSystemSetting extends EditRecord
{
    protected static string $resource = SystemSettingResource::class;

    public function mount(int|string $record): void
    {
        $this->redirect(SystemSettings::getUrl());
    }
}
