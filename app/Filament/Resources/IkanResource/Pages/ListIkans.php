<?php

namespace App\Filament\Resources\IkanResource\Pages;

use App\Filament\Resources\IkanResource;
use App\Filament\Widgets\IkanSegmentTableWidget;
use App\Models\Ikan;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Builder;

class ListIkans extends ListRecords
{
    protected static string $resource = IkanResource::class;

    public function getDefaultActiveTab(): string | int | null
    {
        return 'naik';
    }

    public function getTabs(): array
    {
        $statusUtama = ['menunggu', 'aktif', 'selesai', 'terbayar'];
        $statusBerlangsung = ['aktif', 'menunggu'];
        $statusSelesai = ['selesai', 'terbayar'];

        $countBerlangsungNaik = Ikan::query()
            ->whereIn('status', $statusBerlangsung)
            ->where('tipe_lelang', 'naik')
            ->count();

        $countSelesaiNaik = Ikan::query()
            ->whereIn('status', $statusSelesai)
            ->where('tipe_lelang', 'naik')
            ->count();

        $countBerlangsungTurun = Ikan::query()
            ->whereIn('status', $statusBerlangsung)
            ->where('tipe_lelang', 'turun')
            ->count();

        $countSelesaiTurun = Ikan::query()
            ->whereIn('status', $statusSelesai)
            ->where('tipe_lelang', 'turun')
            ->count();

        return [
            'naik' => Tab::make("Lelang Naik (Berlangsung {$countBerlangsungNaik} | Selesai {$countSelesaiNaik})")
                ->badge(
                    Ikan::query()
                        ->whereIn('status', $statusUtama)
                        ->where('tipe_lelang', 'naik')
                        ->count()
                )
                ->modifyQueryUsing(
                    fn (Builder $query) => $query
                        ->whereIn('status', $statusUtama)
                        ->where('tipe_lelang', 'naik')
                        ->orderByRaw("FIELD(status, 'aktif', 'menunggu', 'selesai', 'terbayar')")
                        ->orderByDesc('created_at')
                ),
            'turun' => Tab::make("Lelang Turun (Berlangsung {$countBerlangsungTurun} | Selesai {$countSelesaiTurun})")
                ->badge(
                    Ikan::query()
                        ->whereIn('status', $statusUtama)
                        ->where('tipe_lelang', 'turun')
                        ->count()
                )
                ->modifyQueryUsing(
                    fn (Builder $query) => $query
                        ->whereIn('status', $statusUtama)
                        ->where('tipe_lelang', 'turun')
                        ->orderByRaw("FIELD(status, 'aktif', 'menunggu', 'selesai', 'terbayar')")
                        ->orderByDesc('created_at')
                ),
            'status_lain' => Tab::make('Status Lain')
                ->badge(
                    Ikan::query()
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

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                Grid::make([
                    'default' => 1,
                    '2xl' => 2,
                ])
                    ->components([
                        Section::make('Lelang Berlangsung')
                            ->icon('heroicon-o-bolt')
                            ->iconColor('warning')
                            ->extraAttributes([
                                'class' => 'rounded-xl border border-amber-200/80 bg-amber-50/40 shadow-sm dark:border-amber-500/30 dark:bg-amber-500/10',
                            ])
                            ->components([
                                Livewire::make(
                                    IkanSegmentTableWidget::class,
                                    fn (): array => [
                                        'tipeLelang' => $this->resolveTipeLelangTab(),
                                        'segment' => 'berlangsung',
                                        'segmentHeading' => $this->resolveTipeLelangLabel() . ' • Berlangsung',
                                    ],
                                )->key(fn (): string => 'ikan-segment-' . $this->resolveTipeLelangTab() . '-berlangsung'),
                            ]),
                        Section::make('Lelang Selesai')
                            ->icon('heroicon-o-check-badge')
                            ->iconColor('success')
                            ->extraAttributes([
                                'class' => 'rounded-xl border border-emerald-200/80 bg-emerald-50/40 shadow-sm dark:border-emerald-500/30 dark:bg-emerald-500/10',
                            ])
                            ->components([
                                Livewire::make(
                                    IkanSegmentTableWidget::class,
                                    fn (): array => [
                                        'tipeLelang' => $this->resolveTipeLelangTab(),
                                        'segment' => 'selesai',
                                        'segmentHeading' => $this->resolveTipeLelangLabel() . ' • Selesai',
                                    ],
                                )->key(fn (): string => 'ikan-segment-' . $this->resolveTipeLelangTab() . '-selesai'),
                            ]),
                    ])
                    ->visible(fn (): bool => $this->isTipeLelangTabActive()),
                EmbeddedTable::make()
                    ->visible(fn (): bool => $this->activeTab === 'status_lain'),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }

    protected function isTipeLelangTabActive(): bool
    {
        return in_array($this->activeTab, ['naik', 'turun'], true);
    }

    protected function resolveTipeLelangTab(): string
    {
        return $this->activeTab === 'turun' ? 'turun' : 'naik';
    }

    protected function resolveTipeLelangLabel(): string
    {
        return $this->resolveTipeLelangTab() === 'turun' ? 'Lelang Turun' : 'Lelang Naik';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
