<?php

namespace App\Filament\Resources\BidResource\Pages;

use App\Filament\Resources\BidResource;
use App\Models\Bid;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListBids extends ListRecords
{
    protected static string $resource = BidResource::class;

    public function getDefaultActiveTab(): string | int | null
    {
        return 'naik';
    }

    public function getTabs(): array
    {
        return [
            'naik' => Tab::make('Bid Lelang Naik')
                ->badge(Bid::query()->whereHas('ikan', fn (Builder $query) => $query->where('tipe_lelang', 'naik'))->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('ikan', fn (Builder $ikanQuery) => $ikanQuery->where('tipe_lelang', 'naik'))),
            'turun' => Tab::make('Bid Lelang Turun')
                ->badge(Bid::query()->whereHas('ikan', fn (Builder $query) => $query->where('tipe_lelang', 'turun'))->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('ikan', fn (Builder $ikanQuery) => $ikanQuery->where('tipe_lelang', 'turun'))),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
