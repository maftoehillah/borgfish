<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\TransaksiResource;
use App\Models\Transaksi;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ActionRequiredTransactionsWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Transaksi Butuh Tindakan';

    public function table(Table $table): Table
    {
        $now = now();

        return $table
            ->poll('10s')
            ->query(
                Transaksi::query()
                    ->with(['ikan.user', 'pemenang'])
                    ->where(function (Builder $query) use ($now): void {
                        $query
                            // Pembeli belum bayar padahal deadline sudah lewat.
                            ->where(function (Builder $sub) use ($now): void {
                                $sub->where('status', 'menunggu_bayar')
                                    ->whereNotNull('bayar_sebelum')
                                    ->where('bayar_sebelum', '<', $now);
                            })
                            // Penjual belum upload packing walau pembayaran sudah cukup lama.
                            ->orWhere(function (Builder $sub) use ($now): void {
                                $sub->where('status', 'lunas')
                                    ->where('payment_status', 'paid')
                                    ->whereNull('packed_at')
                                    ->whereNotNull('dibayar_pada')
                                    ->where('dibayar_pada', '<=', $now->copy()->subHours(24));
                            })
                            // Penjemput sudah datang tapi belum ada konfirmasi selesai > 2 hari.
                            ->orWhere(function (Builder $sub) use ($now): void {
                                $sub->where('status', 'lunas')
                                    ->where('pickup_status', 'pickup_arrived')
                                    ->whereNotNull('pickup_verified_at')
                                    ->where('pickup_verified_at', '<=', $now->copy()->subDays(2));
                            });
                    })
                    ->latest('updated_at')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('ikan.nama_ikan')
                    ->label('Nama Ikan')
                    ->searchable(),
                Tables\Columns\TextColumn::make('ikan.user.name')
                    ->label('Penjual')
                    ->searchable(),
                Tables\Columns\TextColumn::make('pemenang.name')
                    ->label('Pembeli')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('issue')
                    ->label('Isu')
                    ->state(fn (Transaksi $record): string => $this->resolveIssueLabel($record))
                    ->colors([
                        'danger' => 'Lewat batas bayar',
                        'warning' => 'Belum packing',
                        'info' => 'Belum konfirmasi selesai',
                    ]),
                Tables\Columns\BadgeColumn::make('pickup_status')
                    ->label('Penjemputan')
                    ->formatStateUsing(fn (?string $state): string => pickupStatusLabel($state))
                    ->colors([
                        'gray' => 'waiting_payment',
                        'warning' => 'awaiting_pickup',
                        'info' => 'pickup_arrived',
                        'success' => 'completed',
                    ]),
                Tables\Columns\TextColumn::make('progress')
                    ->label('Progress')
                    ->state(fn (Transaksi $record): string => $record->buyerProgressLabel()),
                Tables\Columns\TextColumn::make('overdue_since')
                    ->label('Melewati Sejak')
                    ->state(fn (Transaksi $record): ?Carbon => $this->resolveDeadlineAt($record))
                    ->since(),
            ])
            ->recordUrl(fn (Transaksi $record): string => TransaksiResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Tidak ada transaksi bermasalah saat ini.')
            ->emptyStateDescription('Kasus melewati deadline bayar, packing, atau konfirmasi akan muncul di sini.');
    }

    protected function resolveIssueLabel(Transaksi $record): string
    {
        if (
            $record->status === 'menunggu_bayar'
            && $record->bayar_sebelum
            && now()->gt($record->bayar_sebelum)
        ) {
            return 'Lewat batas bayar';
        }

        if (
            $record->status === 'lunas'
            && $record->payment_status === 'paid'
            && $record->packed_at === null
            && $record->dibayar_pada
            && now()->gt($record->dibayar_pada->copy()->addHours(24))
        ) {
            return 'Belum packing';
        }

        return 'Belum konfirmasi selesai';
    }

    protected function resolveDeadlineAt(Transaksi $record): ?Carbon
    {
        if (
            $record->status === 'menunggu_bayar'
            && $record->bayar_sebelum
        ) {
            return $record->bayar_sebelum;
        }

        if (
            $record->status === 'lunas'
            && $record->payment_status === 'paid'
            && $record->packed_at === null
            && $record->dibayar_pada
        ) {
            return $record->dibayar_pada->copy()->addHours(24);
        }

        if ($record->pickup_verified_at) {
            return $record->pickup_verified_at->copy()->addDays(2);
        }

        return null;
    }
}
