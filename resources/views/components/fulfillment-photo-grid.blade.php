@props([
    'transaksi' => null,
    'title' => 'Foto Bukti',
])

@php
    $photoGroups = collect([
        [
            'title' => 'Packing',
            'description' => 'Bukti barang sudah dipacking oleh penjual.',
            'photos' => [
                ['short' => 'Packing', 'label' => 'Bukti Packing', 'path' => $transaksi?->packing_proof],
            ],
        ],
        [
            'title' => 'Data Penjemput dari Pembeli',
            'description' => 'Foto yang diisi pembeli sebelum penjemput datang.',
            'photos' => [
                ['short' => 'Sopir Pembeli', 'label' => 'Foto Sopir dari Pembeli', 'path' => $transaksi?->buyer_pickup_photo],
                ['short' => 'Kendaraan Pembeli', 'label' => 'Foto Kendaraan dari Pembeli', 'path' => $transaksi?->buyer_pickup_vehicle_photo],
            ],
        ],
        [
            'title' => 'Validasi Penjual',
            'description' => 'Foto penjemput yang divalidasi saat tiba di lokasi penjual.',
            'photos' => [
                ['short' => 'Sopir Penjual', 'label' => 'Foto Sopir Validasi Penjual', 'path' => $transaksi?->seller_pickup_driver_photo],
                ['short' => 'Kendaraan Penjual', 'label' => 'Foto Kendaraan Validasi Penjual', 'path' => $transaksi?->seller_pickup_vehicle_photo],
            ],
        ],
    ])
        ->map(function ($group) {
            $group['photos'] = collect($group['photos'])
                ->filter(fn ($item) => filled($item['path']))
                ->values();

            return $group;
        })
        ->filter(fn ($group) => $group['photos']->isNotEmpty())
        ->values();

    $photoCount = $photoGroups->sum(fn ($group) => $group['photos']->count());
@endphp

@if($photoGroups->isNotEmpty())
    <details {{ $attributes->merge(['class' => 'mt-3 overflow-hidden rounded-2xl border border-slate-200 bg-white group']) }}>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-sm font-black text-slate-800 transition hover:bg-slate-50">
            <span class="min-w-0">
                <span class="block truncate">{{ $title }}</span>
                <span class="mt-0.5 block text-[11px] font-semibold text-slate-500">Dikelompokkan per tahap transaksi</span>
            </span>
            <span class="inline-flex shrink-0 items-center rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-extrabold text-slate-600">
                {{ $photoCount }} foto
            </span>
        </summary>
        <div class="border-t border-slate-100 bg-slate-50/70 px-3 py-3 sm:px-4">
            @foreach($photoGroups as $group)
                <section class="{{ ! $loop->first ? 'mt-4 border-t border-slate-200/80 pt-4' : '' }}">
                    <div class="mb-2 flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="text-xs font-black uppercase tracking-wide text-slate-700">{{ $group['title'] }}</h3>
                            <p class="mt-0.5 text-[11px] leading-relaxed text-slate-500">{{ $group['description'] }}</p>
                        </div>
                        <span class="inline-flex min-w-[72px] shrink-0 items-center justify-center rounded-full bg-white px-3 py-1 text-[11px] font-extrabold text-slate-500 ring-1 ring-slate-200 whitespace-nowrap">
                            {{ $group['photos']->count() }} foto
                        </span>
                    </div>

                    <div class="space-y-2 w-full">
                    @foreach($group['photos'] as $photo)
                        <a href="{{ publicStorageUrl($photo['path']) }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="w-full flex items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 hover:border-cyan-300 transition">
                            <span>{{ $photo['label'] }}</span>
                            <span class="text-cyan-600 text-xs font-black uppercase">Lihat Foto</span>
                        </a>
                    @endforeach
                </div>
                </section>
            @endforeach
        </div>
    </details>
@endif
