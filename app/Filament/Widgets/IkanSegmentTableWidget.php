<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\IkanResource;
use App\Models\Ikan;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class IkanSegmentTableWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public string $tipeLelang = 'naik';

    public string $segment = 'berlangsung';

    public string $segmentHeading = '';

    public function table(Table $table): Table
    {
        return $table
            ->heading($this->resolveHeading())
            ->query(
                Ikan::query()
                    ->with('user')
                    ->withCount('bids')
                    ->where('tipe_lelang', $this->tipeLelang)
                    ->whereIn('status', $this->resolveStatuses())
                    ->orderByRaw($this->resolveStatusOrderSql())
                    ->orderByDesc('created_at')
            )
            ->columns([
                Tables\Columns\ImageColumn::make('foto')
                    ->getStateUsing(fn (Ikan $record): ?string => $record->foto ? url(publicStorageUrl($record->foto)) : null)
                    ->circular(),
                Tables\Columns\TextColumn::make('nama_ikan')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Penjual')
                    ->searchable(),
                Tables\Columns\TextColumn::make('estimasi_jumlah_ekor')
                    ->label('Ekor')
                    ->sortable(),
                Tables\Columns\TextColumn::make('jenis_kemasan')
                    ->label('Kemasan')
                    ->badge(),
                Tables\Columns\BadgeColumn::make('kondisi')
                    ->formatStateUsing(fn (?string $state): string => $state === 'beku' ? 'Frozen' : 'Segar')
                    ->colors([
                        'success' => 'segar',
                        'info' => 'beku',
                    ]),
                Tables\Columns\TextColumn::make('harga_tertinggi')
                    ->label('Harga Saat Ini')
                    ->formatStateUsing(fn ($state) => formatRupiah($state))
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->formatStateUsing(fn (?string $state): string => $state === 'terbayar' ? 'Selesai' : ucfirst((string) $state))
                    ->colors([
                        'success' => 'aktif',
                        'warning' => 'menunggu',
                        'gray' => fn (?string $state): bool => in_array($state, ['selesai', 'terbayar'], true),
                    ]),
                Tables\Columns\TextColumn::make('bids_count')
                    ->label('Bid')
                    ->sortable(),
                Tables\Columns\TextColumn::make('anomali_bid')
                    ->label('Anomali')
                    ->state(fn (Ikan $record): int => $record->bids()->where('is_suspicious', true)->count()),
                Tables\Columns\TextColumn::make('buy_now_price')
                    ->label('Beli Sekarang')
                    ->formatStateUsing(fn ($state) => $state ? formatRupiah($state) : '-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('waktu_selesai')
                    ->label('Selesai')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->url(fn (Ikan $record): string => IkanResource::getUrl('edit', ['record' => $record])),
                Actions\ViewAction::make()
                    ->url(fn (Ikan $record): string => IkanResource::getUrl('view', ['record' => $record])),
                Actions\DeleteAction::make(),
            ])
            ->recordUrl(fn (Ikan $record): string => IkanResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading($this->resolveEmptyStateHeading())
            ->emptyStateDescription('Belum ada lot pada kelompok ini.');
    }

    /**
     * @return array<int, string>
     */
    protected function resolveStatuses(): array
    {
        return $this->segment === 'selesai'
            ? ['selesai', 'terbayar']
            : ['aktif', 'menunggu'];
    }

    protected function resolveStatusOrderSql(): string
    {
        return $this->segment === 'selesai'
            ? "FIELD(status, 'terbayar', 'selesai')"
            : "FIELD(status, 'aktif', 'menunggu')";
    }

    protected function resolveHeading(): string
    {
        if ($this->segmentHeading !== '') {
            return $this->segmentHeading;
        }

        $tipeLabel = $this->tipeLelang === 'turun' ? 'Lelang Turun' : 'Lelang Naik';
        $segmentLabel = $this->segment === 'selesai' ? 'Selesai' : 'Berlangsung';

        return "{$tipeLabel} • {$segmentLabel}";
    }

    protected function resolveEmptyStateHeading(): string
    {
        return $this->segment === 'selesai'
            ? 'Belum ada lot yang selesai.'
            : 'Belum ada lot berlangsung di kelompok ini.';
    }
}
