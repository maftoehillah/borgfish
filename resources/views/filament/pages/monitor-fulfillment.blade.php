<x-filament-panels::page>
    <x-filament::tabs class="mb-6">
        @foreach($segmentOptions as $key => $option)
            <x-filament::tabs.item
                tag="a"
                :href="\App\Filament\Pages\MonitorFulfillment::getUrl(['segment' => $key])"
                :active="$activeSegment === $key"
                :badge="$option['total']"
                badge-color="gray"
            >
                {{ $option['label'] }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    @livewire(\App\Filament\Widgets\FulfillmentMonitoringTableWidget::class, ['segment' => $activeSegment], key('fulfillment-monitor-' . $activeSegment))

    <p class="mt-4 text-xs text-gray-500">
        Pantau alur 3 tahap: 1. Packing, 2. Penjemputan, 3. Selesai.
    </p>
</x-filament-panels::page>
