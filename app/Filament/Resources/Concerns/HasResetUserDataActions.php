<?php

namespace App\Filament\Resources\Concerns;

use App\Services\AuditService;
use App\Services\UserDataResetService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

trait HasResetUserDataActions
{
    protected function getHeaderActions(): array
    {
        return $this->canResetUserData()
            ? [
                ...parent::getHeaderActions(),
                $this->getResetUserDataAction(),
            ]
            : parent::getHeaderActions();
    }

    protected function getResetUserDataAction(): Action
    {
        return Action::make('resetUserData')
            ->label('Hapus Data User')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Hapus data user ini?')
            ->modalDescription($this->getResetUserDataModalDescription())
            ->action(function (): void {
                $record = $this->getRecord();
                $summary = app(UserDataResetService::class)->reset($record, $this->shouldResetSellerLots());

                AuditService::log('admin', auth()->id(), 'user.data_cleared', 'users', (int) $record->id, $summary);

                $record->refresh()->loadMissing('sellerProfile');
                $this->fillForm();

                Notification::make()
                    ->success()
                    ->title('Data user berhasil dihapus')
                    ->body('Akun dan data pendaftaran tetap utuh. Lot penjual, notifikasi, dan data turunannya sudah dibersihkan.')
                    ->send();
            });
    }

    protected function canResetUserData(): bool
    {
        return $this->shouldResetSellerLots();
    }

    protected function shouldResetSellerLots(): bool
    {
        return method_exists($this->getRecord(), 'isPenjual')
            ? (bool) $this->getRecord()->isPenjual()
            : false;
    }

    protected function getResetUserDataModalDescription(): string
    {
        return 'Tindakan ini menghapus seluruh lot penjual beserta bid, transaksi, settlement, file media turunannya, notifikasi, dan data sementara user. Akun, email, role, serta data pendaftaran user tetap dipertahankan.';
    }
}
