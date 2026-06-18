<?php

namespace App\Filament\Resources\SellerSettlementResource\Pages;

use App\Filament\Resources\SellerSettlementResource;
use App\Services\NotificationOutboxService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditSellerSettlement extends EditRecord
{
    protected static string $resource = SellerSettlementResource::class;

    private ?string $originalStatus = null;

    protected function beforeSave(): void
    {
        $this->originalStatus = (string) $this->record->status;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by_id'] = auth()->id();

        $status = (string) ($data['status'] ?? $this->record->status);

        if ($status === 'paid' && $this->originalStatus !== 'paid') {
            throw ValidationException::withMessages([
                'data.status' => 'Gunakan aksi Tandai Dibayar atau Batch Payout agar settlement masuk batch dan memiliki referensi transfer.',
            ]);
        }

        if ($status === 'ready_to_pay' && ! $this->record->ready_to_pay_at) {
            $data['ready_to_pay_at'] = now();
            $data['held_at'] = null;
            $data['cancelled_at'] = null;
        }

        if ($status === 'held' && ! $this->record->held_at) {
            $data['held_at'] = now();
            $data['cancelled_at'] = null;
        }

        if ($status === 'paid' && ! $this->record->paid_at) {
            $data['paid_at'] = now();
            $data['cancelled_at'] = null;
        }

        if ($status === 'cancelled' && ! $this->record->cancelled_at) {
            $data['cancelled_at'] = now();
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $currentStatus = (string) $this->record->fresh()->status;
        $notificationService = app(NotificationOutboxService::class);

        if ($this->originalStatus !== 'ready_to_pay' && $currentStatus === 'ready_to_pay') {
            $notificationService->queueForSellerSettlementReady($this->record->fresh());
            $notificationService->processPending(50);
        }

        if ($this->originalStatus !== 'paid' && $currentStatus === 'paid') {
            $notificationService->queueForSellerSettlementPaid($this->record->fresh());
            $notificationService->processPending(50);
        }
    }
}
