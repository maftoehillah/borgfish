<?php

namespace App\Filament\Resources\ViolationResource\Pages;

use App\Filament\Resources\ViolationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateViolation extends CreateRecord
{
    protected static string $resource = ViolationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['admin_executor_id'] = auth()->id();
        $data['effective_from'] = $data['effective_from'] ?? now();

        return $data;
    }
}
