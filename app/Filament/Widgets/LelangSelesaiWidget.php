<?php

namespace App\Filament\Widgets;

use App\Models\Ikan;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LelangSelesaiWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Lelang Selesai (Admin)';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Ikan::query()
                    ->with(['user'])
                    ->withCount('bids')
                    ->whereIn('status', ['selesai', 'terbayar'])
                    ->orderByRaw("FIELD(status, 'terbayar', 'selesai')")
                    ->orderByDesc('waktu_selesai')
                    ->limit(8)
            )
            ->columns([
                Tables\Columns\TextColumn::make('nama_ikan')->label('Lot')->searchable(),
                Tables\Columns\TextColumn::make('user.name')->label('Penjual')->searchable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'info' => 'terbayar',
                        'gray' => 'selesai',
                    ]),
                Tables\Columns\TextColumn::make('harga_tertinggi')
                    ->label('Harga Penutupan')
                    ->formatStateUsing(fn ($state) => formatRupiah($state)),
                Tables\Columns\TextColumn::make('bids_count')->label('Bid'),
                Tables\Columns\TextColumn::make('waktu_selesai')->label('Selesai')->dateTime('d M Y H:i'),
            ]);
    }
}
