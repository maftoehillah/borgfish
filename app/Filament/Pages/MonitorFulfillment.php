<?php

namespace App\Filament\Pages;

use App\Models\Transaksi;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class MonitorFulfillment extends Page
{
    protected static ?string $slug = 'monitor-fulfillment';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationLabel = 'Penjemputan';

    protected static string | \UnitEnum | null $navigationGroup = 'Operasional';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.monitor-fulfillment';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->canAdmin('support');
    }

    public function getTitle(): string | Htmlable
    {
        return 'Penjemputan';
    }

    public function getViewData(): array
    {
        $activeSegment = request()->query('segment', 'packing');
        if (! in_array($activeSegment, ['packing', 'penjemputan', 'selesai'], true)) {
            $activeSegment = 'packing';
        }

        $packingTotal = $this->applyPackingFilter(Transaksi::query())->count();
        $pickupTotal = $this->applyPickupFilter(Transaksi::query())->count();
        $completedTotal = $this->applyCompletedFilter(Transaksi::query())->count();

        return [
            'activeSegment' => $activeSegment,
            'segmentOptions' => [
                'packing' => [
                    'label' => '1. Packing (Sudah Dipacking)',
                    'total' => $packingTotal,
                ],
                'penjemputan' => [
                    'label' => '2. Penjemputan',
                    'total' => $pickupTotal,
                ],
                'selesai' => [
                    'label' => '3. Selesai',
                    'total' => $completedTotal,
                ],
            ],
        ];
    }

    private function applyPackingFilter(Builder $query): Builder
    {
        return $query
            ->whereNotNull('packed_at')
            ->whereNotIn('pickup_status', ['awaiting_pickup', 'pickup_arrived', 'completed']);
    }

    private function applyPickupFilter(Builder $query): Builder
    {
        return $query
            ->whereIn('pickup_status', ['awaiting_pickup', 'pickup_arrived'])
            ->where('fulfillment_state', '!=', 'SELESAI');
    }

    private function applyCompletedFilter(Builder $query): Builder
    {
        return $query->where(function (Builder $sub): void {
            $sub
                ->where('pickup_status', 'completed')
                ->orWhereNotNull('completed_by_buyer_at')
                ->orWhere('fulfillment_state', 'SELESAI');
        });
    }
}
