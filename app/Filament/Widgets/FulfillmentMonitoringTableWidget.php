<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\TransaksiResource;
use App\Models\Transaksi;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class FulfillmentMonitoringTableWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Penjemputan';

    public string $segment = 'packing';

    private ?array $segmentStatsCache = null;

    public function table(Table $table): Table
    {
        return $table
            ->poll('15s')
            ->heading($this->resolveSegmentHeading())
            ->description((string) $this->resolveSegmentTotal())
            ->query($this->resolveSegmentQuery())
            ->columns([
                Tables\Columns\TextColumn::make('order_code')
                    ->label('Order ID')
                    ->searchable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('ikan.nama_ikan')
                    ->label('Lot')
                    ->searchable(),
                Tables\Columns\TextColumn::make('ikan.user.name')
                    ->label('Penjual')
                    ->searchable(),
                Tables\Columns\TextColumn::make('pemenang.name')
                    ->label('Pembeli')
                    ->searchable(),
                Tables\Columns\TextColumn::make('harga_final')
                    ->label('Harga Final')
                    ->formatStateUsing(fn ($state): string => formatRupiah((float) $state))
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('payment_status')
                    ->label('Payment')
                    ->formatStateUsing(fn (?string $state): string => paymentStatusLabel($state))
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'paid',
                        'danger' => fn ($state) => in_array($state, ['failed', 'expired', 'cancelled'], true),
                        'info' => 'refunded',
                    ]),
                Tables\Columns\BadgeColumn::make('pickup_status')
                    ->label('Penjemputan')
                    ->formatStateUsing(fn (?string $state): string => pickupStatusLabel($state))
                    ->colors([
                        'gray' => 'waiting_payment',
                        'warning' => 'awaiting_pickup',
                        'info' => 'pickup_arrived',
                        'success' => 'completed',
                        'danger' => fn ($state) => in_array($state, ['payment_failed', 'payment_expired'], true),
                    ]),
                Tables\Columns\TextColumn::make('packing_location')
                    ->label('Lokasi Packing')
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('buyer_pickup_plate_number')
                    ->label('Plat Pembeli')
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Update Terakhir')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->recordUrl(fn (Transaksi $record): string => TransaksiResource::getUrl('view', ['record' => $record]))
            ->defaultSort('updated_at', 'desc')
            ->emptyStateHeading($this->resolveEmptyStateHeading())
            ->emptyStateDescription('Data akan muncul ketika transaksi masuk alur packing, penjemputan, atau selesai.');
    }

    private function resolveSegmentQuery(): Builder
    {
        $query = Transaksi::query()
            ->with(['ikan.user', 'pemenang'])
            ->latest('updated_at');

        if ($this->segment === 'packing') {
            return $this->applyPackingFilter($query);
        }

        if ($this->segment === 'penjemputan') {
            return $this->applyPickupFilter($query);
        }

        return $this->applyCompletedFilter($query);
    }

    private function resolveSegmentHeading(): string
    {
        $stats = $this->resolveSegmentStats();

        return match ($this->segment) {
            'packing' => "1. Packing ({$stats['packing_total']})",
            'penjemputan' => "2. Penjemputan ({$stats['pickup_total']})",
            default => "3. Selesai ({$stats['completed_total']})",
        };
    }

    private function resolveSegmentTotal(): int
    {
        $stats = $this->resolveSegmentStats();

        return match ($this->segment) {
            'packing' => $stats['packing_total'],
            'penjemputan' => $stats['pickup_total'],
            default => $stats['completed_total'],
        };
    }

    private function resolveSegmentStats(): array
    {
        if ($this->segmentStatsCache !== null) {
            return $this->segmentStatsCache;
        }

        $this->segmentStatsCache = [
            'packing_total' => $this->applyPackingFilter(Transaksi::query())->count(),
            'pickup_total' => $this->applyPickupFilter(Transaksi::query())->count(),
            'completed_total' => $this->applyCompletedFilter(Transaksi::query())->count(),
        ];

        return $this->segmentStatsCache;
    }

    private function resolveEmptyStateHeading(): string
    {
        return match ($this->segment) {
            'packing' => 'Belum ada transaksi di tahap packing.',
            'penjemputan' => 'Belum ada transaksi di tahap penjemputan.',
            default => 'Belum ada transaksi selesai.',
        };
    }

    private function applyPackingFilter(Builder $query): Builder
    {
        return $query
            ->where('payment_status', 'paid')
            ->whereNotNull('packed_at')
            ->whereNotIn('pickup_status', ['awaiting_pickup', 'pickup_arrived', 'completed']);
    }

    private function applyPickupFilter(Builder $query): Builder
    {
        return $query
            ->where('payment_status', 'paid')
            ->whereIn('pickup_status', ['awaiting_pickup', 'pickup_arrived'])
            ->where('fulfillment_state', '!=', 'SELESAI');
    }

    private function applyCompletedFilter(Builder $query): Builder
    {
        return $query
            ->where('payment_status', 'paid')
            ->where(function (Builder $sub): void {
                $sub
                    ->where('pickup_status', 'completed')
                    ->orWhereNotNull('completed_by_buyer_at')
                    ->orWhere('fulfillment_state', 'SELESAI');
            });
    }
}
