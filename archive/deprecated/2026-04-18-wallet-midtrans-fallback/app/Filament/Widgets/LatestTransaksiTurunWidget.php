<?php

namespace App\Filament\Widgets;

use App\Models\Transaksi;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestTransaksiTurunWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Transaksi Lelang Turun Terbaru';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Transaksi::query()
                    ->with(['ikan', 'pemenang'])
                    ->whereHas('ikan', fn ($query) => $query->where('tipe_lelang', 'turun'))
                    ->latest()
                    ->limit(8)
            )
            ->columns([
                Tables\Columns\TextColumn::make('ikan.nama_ikan')->label('Ikan')->searchable(),
                Tables\Columns\TextColumn::make('pemenang.name')->label('Pemenang'),
                Tables\Columns\TextColumn::make('harga_final')
                    ->label('Harga Final')
                    ->formatStateUsing(fn ($state) => formatRupiah($state)),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'menunggu_bayar',
                        'info' => 'proses',
                        'success' => 'lunas',
                        'danger' => fn ($state) => in_array($state, ['gagal', 'kadaluarsa'], true),
                    ]),
                Tables\Columns\BadgeColumn::make('escrow_status')
                    ->label('Escrow')
                    ->colors([
                        'gray' => 'belum',
                        'warning' => 'ditahan',
                        'success' => 'dilepas',
                        'danger' => 'hangus',
                    ]),
                Tables\Columns\BadgeColumn::make('delivery_status')
                    ->label('Delivery')
                    ->colors([
                        'gray' => 'menunggu_pengiriman',
                        'info' => 'diproses',
                        'warning' => 'dikirim',
                        'success' => 'diterima',
                    ]),
                Tables\Columns\TextColumn::make('created_at')->label('Waktu')->since(),
            ]);
    }
}
