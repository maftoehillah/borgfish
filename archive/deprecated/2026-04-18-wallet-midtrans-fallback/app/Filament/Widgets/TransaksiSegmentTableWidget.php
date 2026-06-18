<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\TransaksiResource;
use App\Models\Transaksi;
use App\Services\NotificationOutboxService;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class TransaksiSegmentTableWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public string $tipeLelang = 'naik';

    public string $status = 'menunggu_bayar';

    public string $segmentHeading = '';

    public function table(Table $table): Table
    {
        return $table
            ->heading($this->resolveHeading())
            ->query(
                Transaksi::query()
                    ->with(['ikan', 'pemenang'])
                    ->where('status', $this->status)
                    ->whereHas('ikan', fn (Builder $query) => $query->where('tipe_lelang', $this->tipeLelang))
                    ->orderByDesc('created_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('ikan.nama_ikan')
                    ->label('Lot')
                    ->searchable(),
                Tables\Columns\TextColumn::make('pemenang.name')
                    ->label('Pemenang')
                    ->searchable(),
                Tables\Columns\TextColumn::make('harga_final')
                    ->label('Harga Final')
                    ->formatStateUsing(fn ($state) => formatRupiah($state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('bayar_sebelum')
                    ->label('Batas Bayar')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('dibayar_pada')
                    ->label('Dibayar Pada')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
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
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->url(fn (Transaksi $record): string => TransaksiResource::getUrl('edit', ['record' => $record])),
                Actions\ViewAction::make()
                    ->url(fn (Transaksi $record): string => TransaksiResource::getUrl('view', ['record' => $record])),
                Actions\DeleteAction::make(),
                Actions\Action::make('tandai_lunas')
                    ->label('Tandai Lunas')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (Transaksi $r) => ! in_array($r->status, ['lunas', 'kadaluarsa', 'gagal'], true))
                    ->requiresConfirmation()
                    ->action(function (Transaksi $record): void {
                        $record->update([
                            'status' => 'lunas',
                            'dibayar_pada' => now(),
                            'escrow_status' => 'ditahan',
                            'escrow_amount' => $record->harga_final,
                            'escrow_locked_at' => now(),
                            'delivery_status' => 'diproses',
                        ]);

                        $record->ikan?->update(['status' => 'terbayar']);
                    }),
                Actions\Action::make('batalkan')
                    ->label('Batalkan')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->visible(fn (Transaksi $r) => $r->status === 'menunggu_bayar')
                    ->requiresConfirmation()
                    ->action(fn (Transaksi $record) => $record->update(['status' => 'gagal'])),
                Actions\Action::make('lepas_escrow')
                    ->label('Lepas Escrow')
                    ->color('success')
                    ->icon('heroicon-o-lock-open')
                    ->visible(fn (Transaksi $r) => $r->escrow_status === 'ditahan')
                    ->requiresConfirmation()
                    ->action(function (Transaksi $record): void {
                        $record->releaseEscrow();
                        $record->save();
                        app(NotificationOutboxService::class)->processPending(100);
                    }),
                Actions\Action::make('hanguskan_escrow')
                    ->label('Hanguskan Escrow')
                    ->color('danger')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->visible(fn (Transaksi $r) => $r->escrow_status === 'ditahan')
                    ->requiresConfirmation()
                    ->action(function (Transaksi $record): void {
                        $record->forfeitEscrow();
                        $record->save();
                        app(NotificationOutboxService::class)->processPending(100);
                    }),
            ])
            ->recordUrl(fn (Transaksi $record): string => TransaksiResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading($this->resolveEmptyStateHeading())
            ->emptyStateDescription('Belum ada transaksi pada kelompok ini.');
    }

    protected function resolveHeading(): string
    {
        if ($this->segmentHeading !== '') {
            return $this->segmentHeading;
        }

        $tipeLabel = $this->tipeLelang === 'turun' ? 'Lelang Turun' : 'Lelang Naik';
        $statusLabel = $this->status === 'lunas' ? 'Sudah Bayar' : 'Belum Bayar';

        return "{$tipeLabel} • {$statusLabel}";
    }

    protected function resolveEmptyStateHeading(): string
    {
        return $this->status === 'lunas'
            ? 'Belum ada transaksi yang sudah bayar.'
            : 'Belum ada transaksi yang menunggu pembayaran.';
    }
}
