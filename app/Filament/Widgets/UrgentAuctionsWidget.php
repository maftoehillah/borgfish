<?php

namespace App\Filament\Widgets;

use App\Models\Ikan;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Carbon;

class UrgentAuctionsWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Lelang Mendekati Deadline (<= 30 Menit)';

    public function table(Table $table): Table
    {
        return $table
            ->poll('10s')
            ->query(
                Ikan::query()
                    ->with(['user', 'bids'])
                    ->where('status', 'aktif')
                    ->where('waktu_selesai', '>', now())
                    ->where('waktu_selesai', '<=', now()->copy()->addMinutes(30))
                    ->orderBy('waktu_selesai')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('nama_ikan')
                    ->label('Nama Ikan')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Penjual')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('tipe_lelang')
                    ->label('Mode')
                    ->state(fn (Ikan $record): string => $record->isLelangTurun() ? 'Lelang Turun' : 'Lelang Naik')
                    ->colors([
                        'warning' => 'Lelang Turun',
                        'success' => 'Lelang Naik',
                    ]),
                Tables\Columns\TextColumn::make('harga_tertinggi')
                    ->label('Harga Saat Ini')
                    ->formatStateUsing(fn ($state) => formatRupiah($state)),
                Tables\Columns\TextColumn::make('jumlah_bid')
                    ->label('Total Bid')
                    ->state(fn (Ikan $record): int => $record->bids->count()),
                Tables\Columns\TextColumn::make('waktu_selesai')
                    ->label('Sisa Waktu')
                    ->formatStateUsing(function ($state): string {
                        if ($state === null) {
                            return '-';
                        }

                        $deadline = $state instanceof Carbon
                            ? $state
                            : Carbon::parse((string) $state);

                        return now()->lt($deadline)
                            ? now()->diffForHumans($deadline, true)
                            : 'Selesai';
                    }),
            ])
            ->emptyStateHeading('Tidak ada lelang urgent saat ini.')
            ->emptyStateDescription('Lelang aktif dengan sisa waktu <= 30 menit akan tampil di sini.');
    }
}