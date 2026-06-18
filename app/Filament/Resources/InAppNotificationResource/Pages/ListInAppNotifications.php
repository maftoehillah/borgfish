<?php

namespace App\Filament\Resources\InAppNotificationResource\Pages;

use App\Filament\Resources\InAppNotificationResource;
use App\Models\InAppNotification;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListInAppNotifications extends ListRecords
{
    protected static string $resource = InAppNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('mark_all_read_notifications')
                ->label('Tandai Semua Dibaca')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->visible(function (): bool {
                    $adminId = auth()->id();

                    if (! $adminId) {
                        return false;
                    }

                    return InAppNotification::query()
                        ->where('user_id', (int) $adminId)
                        ->whereNull('read_at')
                        ->exists();
                })
                ->action(function (): void {
                    $adminId = auth()->id();

                    if (! $adminId) {
                        return;
                    }

                    InAppNotification::query()
                        ->where('user_id', (int) $adminId)
                        ->whereNull('read_at')
                        ->update([
                            'read_at' => now(),
                            'updated_at' => now(),
                        ]);
                }),
            Action::make('delete_all_notifications')
                ->label('Hapus Semua')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Hapus semua notifikasi?')
                ->modalDescription('Semua notifikasi admin akan dihapus permanen.')
                ->visible(function (): bool {
                    $adminId = auth()->id();

                    if (! $adminId) {
                        return false;
                    }

                    return InAppNotification::query()
                        ->where('user_id', (int) $adminId)
                        ->exists();
                })
                ->action(function (): void {
                    $adminId = auth()->id();

                    if (! $adminId) {
                        return;
                    }

                    InAppNotification::query()
                        ->where('user_id', (int) $adminId)
                        ->delete();
                }),
        ];
    }
}
