<?php

namespace App\Filament\Widgets;

use App\Models\Bid;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LiveBidFeedWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Live Feed Bid Terakhir';

    public function table(Table $table): Table
    {
        return $table
            ->poll('10s')
            ->query(
                Bid::query()
                    ->with(['ikan.user', 'user'])
                    ->latest('created_at')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('ikan.nama_ikan')
                    ->label('Nama Ikan')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('ikan.tipe_lelang')
                    ->label('Mode')
                    ->state(fn (Bid $record): string => $record->ikan?->tipe_lelang === 'turun' ? 'Lelang Turun' : 'Lelang Naik')
                    ->colors([
                        'warning' => 'Lelang Turun',
                        'success' => 'Lelang Naik',
                    ]),
                Tables\Columns\TextColumn::make('ikan.user.name')
                    ->label('Penjual')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Nama Pembeli')
                    ->searchable(),
                Tables\Columns\TextColumn::make('jumlah_bid')
                    ->label('Harga Bid')
                    ->formatStateUsing(fn ($state) => formatRupiah($state)),
                Tables\Columns\BadgeColumn::make('is_suspicious')
                    ->label('Validasi')
                    ->state(fn (Bid $record): string => $record->is_suspicious ? 'Anomali' : 'Normal')
                    ->colors([
                        'danger' => 'Anomali',
                        'success' => 'Normal',
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->since(),
            ])
            ->emptyStateHeading('Belum ada aktivitas bid.')
            ->emptyStateDescription('Live feed akan tampil otomatis saat ada bid baru.');
    }
}