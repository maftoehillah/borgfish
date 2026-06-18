<?php

namespace App\Filament\Resources\PenjualResource\Pages;

use App\Filament\Resources\Concerns\HasResetUserDataActions;
use App\Filament\Resources\PenjualResource;
use App\Models\SellerProfile;
use Filament\Resources\Pages\EditRecord;

class EditPenjual extends EditRecord
{
    use HasResetUserDataActions;

    protected static string $resource = PenjualResource::class;

    protected function afterSave(): void
    {
        $data = $this->data;

        $sellerData = [];

        if (isset($data['sellerProfile']['store_name'])) {
            $sellerData['store_name'] = $data['sellerProfile']['store_name'];
        }
        if (isset($data['sellerProfile']['full_address'])) {
            $sellerData['full_address'] = $data['sellerProfile']['full_address'];
        }
        if (isset($data['sellerProfile']['bank_name'])) {
            $sellerData['bank_name'] = $data['sellerProfile']['bank_name'];
        }
        if (isset($data['sellerProfile']['bank_account_number'])) {
            $sellerData['bank_account_number'] = $data['sellerProfile']['bank_account_number'];
        }
        if (isset($data['sellerProfile']['bank_account_name'])) {
            $sellerData['bank_account_name'] = $data['sellerProfile']['bank_account_name'];
        }

        if (! empty($sellerData)) {
            SellerProfile::updateOrCreate(
                ['user_id' => $this->record->id],
                $sellerData
            );
        }
    }
}
