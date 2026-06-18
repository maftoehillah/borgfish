<?php

namespace App\Filament\Resources\TransaksiResource\Pages;

use App\Filament\Resources\TransaksiResource;
use App\Models\Transaksi;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTransaksis extends ListRecords
{
    protected static string $resource = TransaksiResource::class;

    public function getDefaultActiveTab(): string | int | null
    {
        return 'naik';
    }

    public function getTabs(): array
    {
        $statusUtama = ['menunggu_bayar', 'lunas'];

        $countBelumNaik = Transaksi::query()
            ->where('status', 'menunggu_bayar')
            ->whereHas('ikan', fn (Builder $query) => $query->where('tipe_lelang', 'naik'))
            ->count();

        $countSudahNaik = Transaksi::query()
            ->where('status', 'lunas')
            ->whereHas('ikan', fn (Builder $query) => $query->where('tipe_lelang', 'naik'))
            ->count();

        $countBelumTurun = Transaksi::query()
            ->where('status', 'menunggu_bayar')
            ->whereHas('ikan', fn (Builder $query) => $query->where('tipe_lelang', 'turun'))
            ->count();

        $countSudahTurun = Transaksi::query()
            ->where('status', 'lunas')
            ->whereHas('ikan', fn (Builder $query) => $query->where('tipe_lelang', 'turun'))
            ->count();

        return [
            'naik' => Tab::make("Lelang Naik (Belum {$countBelumNaik} | Sudah {$countSudahNaik})")
                ->badge(
                    Transaksi::query()
                        ->whereIn('status', $statusUtama)
                        ->whereHas('ikan', fn (Builder $query) => $query->where('tipe_lelang', 'naik'))
                        ->count()
                )
                ->modifyQueryUsing(
                    fn (Builder $query) => $query
                        ->whereIn('status', $statusUtama)
                        ->whereHas('ikan', fn (Builder $ikanQuery) => $ikanQuery->where('tipe_lelang', 'naik'))
                        ->orderByDesc('created_at')
                ),
            'turun' => Tab::make("Lelang Turun (Belum {$countBelumTurun} | Sudah {$countSudahTurun})")
                ->badge(
                    Transaksi::query()
                        ->whereIn('status', $statusUtama)
                        ->whereHas('ikan', fn (Builder $query) => $query->where('tipe_lelang', 'turun'))
                        ->count()
                )
                ->modifyQueryUsing(
                    fn (Builder $query) => $query
                        ->whereIn('status', $statusUtama)
                        ->whereHas('ikan', fn (Builder $ikanQuery) => $ikanQuery->where('tipe_lelang', 'turun'))
                        ->orderByDesc('created_at')
                ),
            'status_lain' => Tab::make('Status Lain')
                ->badge(
                    Transaksi::query()
                        ->whereNotIn('status', $statusUtama)
                        ->count()
                )
                ->modifyQueryUsing(
                    fn (Builder $query) => $query
                        ->whereNotIn('status', $statusUtama)
                        ->orderByDesc('created_at')
                ),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
