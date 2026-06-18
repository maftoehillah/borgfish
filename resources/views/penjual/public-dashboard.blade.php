@extends('layouts.app')
@section('title', 'Toko ' . ($sellerProfile?->store_name ?: $seller->name) . ' - Borgfish')

@section('content')
@php
    $storeStats = $storeStats ?? [];
    $storeName = $sellerProfile?->store_name ?: $seller->name;
    $storePhoto = $sellerProfile?->store_photo_path ? publicStorageUrl($sellerProfile->store_photo_path) : null;
    $returnUrl = request('return_url', route('ikans.index'));
    $ratingAverage = array_key_exists('rating_average', $storeStats) && $storeStats['rating_average'] !== null
        ? (float) $storeStats['rating_average']
        : null;
    $ratingCount = (int) ($storeStats['rating_count'] ?? 0);
    $lotAktif = (int) ($storeStats['lot_aktif'] ?? 0);
    $lotSelesai = (int) ($storeStats['lot_selesai'] ?? 0);
    $totalLot = (int) ($storeStats['total_lot'] ?? 0);
    $ratingLabel = $ratingAverage !== null ? number_format($ratingAverage, 1) : 'Baru';
    $ratingFullLabel = $ratingAverage !== null ? number_format($ratingAverage, 1) . ' / 5' : 'Belum ada penilaian';
    $ratingCaptionDesktop = $ratingCount > 0 ? 'dari ' . number_format($ratingCount) . ' penilaian' : 'Belum ada penilaian';
    $ratingCaptionMobile = $ratingCount > 0 ? number_format($ratingCount) . ' ulasan' : 'Belum ada';
    $lotAktifCaptionDesktop = 'Lot yang sedang aktif atau menunggu tayang.';
    $lotAktifCaptionMobile = $lotAktif > 0 ? 'Sedang tayang' : 'Belum ada';
    $lotSelesaiCaptionDesktop = 'Riwayat lot yang sudah tuntas terjual.';
    $lotSelesaiCaptionMobile = $lotSelesai > 0 ? 'Sudah terjual' : 'Belum ada';
@endphp

<style>
    .public-store-hero {
        background:
            radial-gradient(circle at 12% 8%, rgba(14, 165, 233, 0.15), transparent 34%),
            radial-gradient(circle at 88% 0%, rgba(16, 185, 129, 0.14), transparent 36%),
            linear-gradient(145deg, #f8fcff 0%, #eff8ff 52%, #f8fcff 100%);
    }

    .public-store-surface {
        border: 1px solid rgba(226, 232, 240, 0.95);
        box-shadow: 0 16px 24px -22px rgba(15, 23, 42, 0.6);
    }

    .public-store-tile {
        background: rgba(248, 250, 252, 0.9);
        border: 1px solid rgba(226, 232, 240, 0.9);
    }

    .public-store-stats-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.75rem;
    }

    .public-store-stat-card {
        position: relative;
        display: flex;
        min-height: 8.25rem;
        flex-direction: column;
        justify-content: space-between;
        overflow: hidden;
        border-radius: 1.35rem;
        border: 1px solid rgba(207, 250, 254, 0.95);
        background:
            linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 250, 252, 0.96) 100%);
        box-shadow: 0 14px 24px -24px rgba(8, 47, 73, 0.7);
        padding: 0.95rem 0.9rem 0.9rem;
    }

    .public-store-stat-card::before {
        content: "";
        position: absolute;
        left: 0.9rem;
        right: 0.9rem;
        top: 0;
        height: 3px;
        border-radius: 999px;
        background: var(--stat-accent, linear-gradient(90deg, #0f766e 0%, #06b6d4 100%));
        opacity: 0.9;
    }

    .public-store-stat-card--rating {
        --stat-accent: linear-gradient(90deg, #f59e0b 0%, #f97316 100%);
    }

    .public-store-stat-card--active {
        --stat-accent: linear-gradient(90deg, #0891b2 0%, #2563eb 100%);
    }

    .public-store-stat-card--done {
        --stat-accent: linear-gradient(90deg, #059669 0%, #10b981 100%);
    }

    .public-store-stat-label {
        font-size: 0.7rem;
        line-height: 1.15;
        font-weight: 800;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: #64748b;
    }

    .public-store-stat-value {
        margin-top: 0.75rem;
        display: flex;
        align-items: flex-end;
        gap: 0.18rem;
    }

    .public-store-stat-number {
        font-size: 2rem;
        line-height: 0.95;
        font-weight: 900;
        letter-spacing: -0.04em;
        color: #0f172a;
    }

    .public-store-stat-number--rating {
        color: #d97706;
    }

    .public-store-stat-number--done {
        color: #047857;
    }

    .public-store-stat-suffix {
        margin-bottom: 0.18rem;
        font-size: 0.82rem;
        line-height: 1;
        font-weight: 800;
        color: #f59e0b;
    }

    .public-store-stat-caption {
        margin-top: 0.9rem;
        font-size: 0.76rem;
        line-height: 1.35;
        font-weight: 600;
        color: #64748b;
    }

    .public-store-card {
        border: 1px solid rgba(226, 232, 240, 0.95);
        box-shadow: 0 14px 26px -22px rgba(15, 23, 42, 0.55);
    }

    .lot-card {
        display: flex;
        flex-direction: column;
        border: 1px solid rgba(226, 232, 240, 0.95);
        box-shadow: 0 14px 26px -22px rgba(15, 23, 42, 0.55);
    }

    .lot-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 22px 32px -24px rgba(8, 47, 73, 0.65);
    }

    .lot-card-desktop {
        flex: 1;
        flex-direction: column;
    }

    .lot-card-body {
        display: flex;
        flex: 1;
        flex-direction: column;
        padding: 1rem 1.25rem 1.25rem;
    }

    .lot-card-cta {
        margin-top: auto;
        padding-top: 0.75rem;
    }

    .lot-card-img-wrap {
        position: relative;
        width: 100%;
        height: 200px;
        overflow: hidden;
        flex-shrink: 0;
        background: linear-gradient(135deg, #f0f9ff 0%, #dff6fb 100%);
    }

    .lot-card-img-wrap img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center;
        display: block;
    }

    .market-lot-media-mobile {
        position: relative;
        height: 8.75rem;
        overflow: hidden;
        flex-shrink: 0;
        background: linear-gradient(135deg, #f0f9ff 0%, #dff6fb 100%);
    }

    .market-lot-media-mobile img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center;
    }

    .market-lot-title {
        display: -webkit-box;
        overflow: hidden;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
    }

    .market-lot-meta {
        display: -webkit-box;
        overflow: hidden;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 1;
    }

    .public-store-gallery-image {
        position: absolute;
        top: 50%;
        left: 50%;
        width: auto;
        height: auto;
        min-width: 100%;
        min-height: 100%;
        max-width: none;
        transform: translate(-50%, -50%);
        display: block;
    }

    .public-store-gallery-frame {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        height: 16rem;
        min-height: 16rem;
        overflow: hidden;
        background: #f8fafc;
    }

    .public-store-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 22px 32px -24px rgba(8, 47, 73, 0.62);
    }

    .public-store-clamp-2 {
        display: -webkit-box;
        overflow: hidden;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
    }

    @media (max-width: 639px) {
        .public-store-hero {
            border-radius: 1.5rem;
        }

        .public-store-stats-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.68rem;
        }

        .public-store-stat-card {
            min-height: 6.7rem;
            border-radius: 1.1rem;
            padding: 0.82rem 0.76rem 0.78rem;
        }

        .public-store-stat-card::before {
            left: 0.76rem;
            right: 0.76rem;
        }

        .public-store-stat-card--rating {
            grid-column: 1 / -1;
            min-height: 6.2rem;
        }

        .public-store-stat-number {
            font-size: 1.65rem;
        }

        .public-store-stat-number--rating {
            font-size: 1.95rem;
        }

        .public-store-stat-caption {
            margin-top: 0.62rem;
            font-size: 0.72rem;
            line-height: 1.28;
        }

        .public-store-stat-card--rating .public-store-stat-caption {
            margin-top: 0.5rem;
        }

        .public-store-card {
            border-radius: 1.125rem;
        }

        .public-store-card-body {
            padding: 1rem;
        }

        .public-store-card-media {
            min-height: 9rem;
        }

        .public-store-gallery-frame {
            height: 14rem;
            min-height: 14rem;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .lot-card,
        .public-store-card {
            transition: none;
        }
    }
</style>

<section class="public-store-hero rounded-3xl border border-cyan-100/70 px-5 py-6 sm:px-8 sm:py-9 mb-8">
    <div class="max-w-3xl flex flex-col gap-4">
        <x-back-button :href="$returnUrl" label="Kembali" class="hidden sm:inline-flex w-fit self-start rounded-lg px-3 py-2" />

        <div class="flex items-start gap-4">
            @if($storePhoto)
                <img src="{{ $storePhoto }}" alt="{{ $storeName }}" class="aspect-square h-16 w-16 shrink-0 rounded-full object-cover border-4 border-white shadow-sm sm:h-20 sm:w-20" decoding="async">
            @else
                <span class="inline-flex aspect-square h-16 w-16 shrink-0 items-center justify-center rounded-full bg-cyan-100 text-lg font-black text-cyan-800 border-4 border-white shadow-sm sm:h-20 sm:w-20 sm:text-xl">
                    {{ $seller->initials() ?: 'TK' }}
                </span>
            @endif

            <div>
                <p class="inline-flex items-center px-3 py-1 rounded-full text-xs font-extrabold tracking-[0.14em] uppercase text-cyan-700 bg-cyan-100/70 border border-cyan-200/70">
                    Dashboard Toko Penjual
                </p>
                <h1 class="mt-3 text-3xl sm:text-4xl font-black tracking-tight text-slate-900">{{ $storeName }}</h1>
            </div>
        </div>
    </div>

    <div class="mt-6 public-store-stats-grid">
        <article class="public-store-stat-card public-store-stat-card--rating">
            <div>
                <p class="public-store-stat-label">Rating</p>
                <div class="public-store-stat-value" aria-label="{{ $ratingFullLabel }}" title="{{ $ratingFullLabel }}">
                    <span class="public-store-stat-number public-store-stat-number--rating">{{ $ratingLabel }}</span>
                    @if($ratingAverage !== null)
                        <span class="public-store-stat-suffix">/5</span>
                    @endif
                </div>
            </div>
            <p class="public-store-stat-caption">
                <span class="sm:hidden">{{ $ratingCaptionMobile }}</span>
                <span class="hidden sm:inline">{{ $ratingCaptionDesktop }}</span>
            </p>
        </article>

        <article class="public-store-stat-card public-store-stat-card--active">
            <div>
                <p class="public-store-stat-label">Lot Tayang</p>
                <div class="public-store-stat-value">
                    <span class="public-store-stat-number">{{ number_format($lotAktif) }}</span>
                </div>
            </div>
            <p class="public-store-stat-caption">
                <span class="sm:hidden">{{ $lotAktifCaptionMobile }}</span>
                <span class="hidden sm:inline">{{ $lotAktifCaptionDesktop }}</span>
            </p>
        </article>

        <article class="public-store-stat-card public-store-stat-card--done">
            <div>
                <p class="public-store-stat-label">Lot Selesai</p>
                <div class="public-store-stat-value">
                    <span class="public-store-stat-number public-store-stat-number--done">{{ number_format($lotSelesai) }}</span>
                </div>
            </div>
            <p class="public-store-stat-caption">
                <span class="sm:hidden">{{ $lotSelesaiCaptionMobile }}</span>
                <span class="hidden sm:inline">{{ $lotSelesaiCaptionDesktop }}</span>
            </p>
        </article>
    </div>
</section>

<section class="grid grid-cols-1 gap-6 mb-8 lg:grid-cols-3 lg:items-start">
    <article class="public-store-surface hidden self-start overflow-hidden rounded-3xl bg-white sm:block lg:col-span-1">
        <div class="public-store-gallery-frame" data-store-gallery-frame>
            @if($storePhoto)
                <img src="{{ $storePhoto }}" alt="{{ $storeName }}" class="public-store-gallery-image" loading="lazy" decoding="async">
            @else
                <div class="flex h-full items-center justify-center bg-slate-50">
                    <span class="text-sm font-semibold text-slate-400">Foto toko belum tersedia</span>
                </div>
            @endif
        </div>
    </article>

    <article class="public-store-surface self-start bg-white rounded-3xl p-5 sm:p-6 lg:col-span-2" data-store-identity-panel>
        <h2 class="text-lg font-black text-slate-900">Identitas Toko</h2>
        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
            <div class="public-store-tile rounded-xl px-4 py-3">
                <p class="text-xs text-slate-500">Nama Penjual</p>
                <p class="mt-1 font-bold text-slate-900">{{ $seller->name }}</p>
            </div>
            <div class="public-store-tile rounded-xl px-4 py-3">
                <p class="text-xs text-slate-500">Nama Toko</p>
                <p class="mt-1 font-bold text-slate-900">{{ $storeName }}</p>
            </div>
            <div class="public-store-tile rounded-xl px-4 py-3">
                <p class="text-xs text-slate-500">Titik GPS Toko</p>
                <p class="mt-1 break-all font-bold text-slate-900">
                    @if($sellerProfile?->store_latitude && $sellerProfile?->store_longitude)
                        {{ $sellerProfile->store_latitude }}, {{ $sellerProfile->store_longitude }}
                    @else
                        -
                    @endif
                </p>
                @if($sellerProfile?->store_gps_accuracy)
                    <p class="mt-1 text-xs text-slate-500">Akurasi: {{ $sellerProfile->store_gps_accuracy }} meter</p>
                @endif
            </div>
            <div class="public-store-tile rounded-xl px-4 py-3">
                <p class="text-xs text-slate-500">Status Toko</p>
                <p class="mt-1 font-bold text-emerald-700">Terverifikasi Sistem</p>
            </div>
            <div class="public-store-tile rounded-xl px-4 py-3">
                <p class="text-xs text-slate-500">Total Lot</p>
                <p class="mt-1 font-bold text-cyan-700">{{ number_format($totalLot) }}</p>
            </div>
            <div class="public-store-tile rounded-xl px-4 py-3 sm:col-span-2">
                <p class="text-xs text-slate-500">Alamat Lengkap</p>
                <p class="mt-1 break-words font-semibold text-slate-700">{{ $sellerProfile?->full_address ?: '-' }}</p>
            </div>
        </div>
    </article>
</section>

<section class="public-store-surface bg-white rounded-3xl p-5 sm:p-6 mb-8">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div>
            <h2 class="text-lg font-black text-slate-900">Isi Toko</h2>
            <p class="mt-1 text-sm text-slate-500">Lot yang sedang tayang atau menunggu jadwal lelang.</p>
        </div>
        <span class="inline-flex px-3 py-1 rounded-full bg-cyan-100 text-cyan-700 text-sm font-bold">{{ $activeLots->total() }} lot</span>
    </div>

    @if($activeLots->isEmpty())
        <div class="rounded-2xl border border-slate-100 bg-slate-50 px-5 py-10 text-center">
            <h3 class="text-lg font-black text-slate-700">Belum Ada Lot Tayang</h3>
            <p class="mt-1 text-sm text-slate-500">Lot aktif dari toko ini akan muncul di sini.</p>
        </div>
    @else
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-2 sm:gap-6 lg:grid-cols-3 xl:grid-cols-4">
            @foreach($activeLots as $lot)
                @php
                    $detailUrl = route('ikans.show', ['ikan' => $lot, 'return_url' => $returnUrl]);
                    $totalBid = (int) ($lot->bids_count ?? 0);
                @endphp

                <article class="lot-card rounded-2xl overflow-hidden bg-white transition-all duration-200">
                    <a href="{{ $detailUrl }}" class="block sm:hidden">
                        <div class="market-lot-media-mobile">
                            @if($lot->foto)
                                <img src="{{ publicStorageUrl($lot->foto) }}" alt="{{ $lot->nama_ikan }}" loading="lazy" decoding="async" sizes="50vw">
                            @else
                                <div class="flex h-full items-center justify-center px-3 text-center">
                                    <span class="text-xs font-semibold text-slate-400">Tidak ada foto</span>
                                </div>
                            @endif

                            <div class="absolute left-2 top-2 flex gap-1.5">
                                <span class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[9px] font-bold {{ $lot->isLelangTurun() ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">
                                    {{ $lot->isLelangTurun() ? 'Turun' : 'Naik' }}
                                </span>
                            </div>

                            <div class="absolute right-2 top-2">
                                @if($lot->status === 'aktif')
                                    <span class="rounded-full bg-emerald-500 px-1.5 py-0.5 text-[9px] font-black text-white">Aktif</span>
                                @else
                                    <span class="rounded-full bg-amber-300 px-1.5 py-0.5 text-[9px] font-black text-amber-900">Segera</span>
                                @endif
                            </div>
                        </div>

                        <div class="p-3">
                            <h3 class="market-lot-title text-sm font-bold leading-snug text-slate-900">{{ $lot->nama_ikan }}</h3>
                            <p class="market-lot-meta mt-0.5 text-[11px] text-slate-500">
                                {{ $lot->berat }} kg &bull; {{ $lot->kondisi === 'beku' ? 'Frozen' : ucfirst($lot->kondisi) }}
                            </p>
                        </div>

                        <div class="px-3 pb-3">
                            <div class="mb-2.5 border-t border-slate-100"></div>
                            <div class="flex items-end justify-between gap-1">
                                <div class="min-w-0">
                                    <p class="text-[9px] font-bold uppercase tracking-wide text-slate-400">{{ $lot->status === 'aktif' ? 'Harga saat ini' : 'Harga awal' }}</p>
                                    <p class="mt-0.5 text-[13px] font-black leading-tight text-cyan-700">{{ formatRupiah($lot->harga_tertinggi) }}</p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Bid</p>
                                    <p class="mt-0.5 text-[13px] font-black leading-tight text-slate-700">{{ number_format($totalBid) }}</p>
                                </div>
                            </div>

                            <div class="mt-2.5 flex items-center justify-between gap-1">
                                <p class="text-[9px] font-bold uppercase tracking-wide text-slate-400">{{ $lot->status === 'aktif' ? 'Berakhir' : 'Mulai' }}</p>
                                <span class="rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">
                                    {{ ($lot->status === 'aktif' ? $lot->waktu_selesai : $lot->waktu_mulai)->format('d M H:i') }}
                                </span>
                            </div>
                        </div>
                    </a>

                    <div class="lot-card-desktop hidden sm:flex sm:flex-col">
                        <div class="lot-card-img-wrap">
                            @if($lot->foto)
                                <img src="{{ publicStorageUrl($lot->foto) }}" alt="{{ $lot->nama_ikan }}" loading="lazy" decoding="async" sizes="(max-width: 1280px) 33vw, 25vw">
                            @else
                                <div class="flex h-full items-center justify-center">
                                    <span class="text-base font-semibold text-slate-400">Tidak ada foto</span>
                                </div>
                            @endif

                            <div class="absolute top-3 left-3">
                                <span class="inline-flex items-center rounded-full px-2 py-1 text-[10px] font-bold {{ $lot->isLelangTurun() ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">
                                    {{ $lot->isLelangTurun() ? 'Lelang Turun' : 'Lelang Naik' }}
                                </span>
                            </div>

                            <div class="absolute top-3 right-3">
                                @if($lot->status === 'aktif')
                                    <span class="rounded-full bg-emerald-500 px-2 py-1 text-[10px] font-black text-white">AKTIF</span>
                                @else
                                    <span class="rounded-full bg-amber-300 px-2 py-1 text-[10px] font-black text-amber-900">SEGERA</span>
                                @endif
                            </div>
                        </div>

                        <div class="lot-card-body">
                            <h3 class="font-bold leading-tight text-slate-900 text-lg">{{ $lot->nama_ikan }}</h3>
                            <div class="mt-2 flex items-center gap-2 flex-wrap text-sm text-slate-500">
                                <span>{{ $lot->berat }} kg</span>
                                <span>&bull;</span>
                                <span class="capitalize">{{ $lot->kondisi === 'beku' ? 'Frozen' : $lot->kondisi }}</span>
                                @if($lot->estimasi_jumlah_ekor)
                                    <span>&bull;</span>
                                    <span>{{ $lot->estimasi_jumlah_ekor }} ekor</span>
                                @endif
                            </div>

                            <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                                <div class="rounded-lg border border-slate-100 bg-slate-50 px-2 py-2">
                                    <p class="text-[10px] uppercase tracking-wide text-slate-400">Total Bid</p>
                                    <p class="mt-1 font-black text-slate-700">{{ number_format($totalBid) }}</p>
                                </div>
                                <div class="rounded-lg border border-slate-100 bg-slate-50 px-2 py-2">
                                    <p class="text-[10px] uppercase tracking-wide text-slate-400">{{ $lot->status === 'aktif' ? 'Berakhir' : 'Mulai' }}</p>
                                    <p class="mt-1 font-black text-slate-700">{{ ($lot->status === 'aktif' ? $lot->waktu_selesai : $lot->waktu_mulai)->format('d M H:i') }}</p>
                                </div>
                            </div>

                            <div class="border-t border-slate-100 pt-3 mt-3" style="min-height: 74px;">
                                <p class="text-xs text-slate-400">{{ $lot->status === 'aktif' ? 'Harga saat ini' : 'Harga awal' }}</p>
                                <p class="text-xl font-black text-cyan-700">{{ formatRupiah($lot->harga_tertinggi) }}</p>
                            </div>

                            <div class="lot-card-cta">
                                <x-secondary-action-link :href="$detailUrl" class="flex w-full text-sm">
                                    Lihat Detail
                                </x-secondary-action-link>
                            </div>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
        <div class="mt-5">
            {{ $activeLots->links() }}
        </div>
    @endif
</section>

<section class="public-store-surface bg-white rounded-3xl p-5 sm:p-6">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div>
            <h2 class="text-lg font-black text-slate-900">Riwayat Lot Selesai</h2>
            <p class="mt-1 text-sm text-slate-500">Lot yang sudah selesai dikonfirmasi di toko ini.</p>
        </div>
        <span class="inline-flex px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 text-sm font-bold">{{ $completedLots->total() }} lot</span>
    </div>

    @if($completedLots->isEmpty())
        <div class="rounded-2xl border border-slate-100 bg-slate-50 px-5 py-10 text-center">
            <h3 class="text-lg font-black text-slate-700">Belum Ada Riwayat Selesai</h3>
            <p class="mt-1 text-sm text-slate-500">Lot selesai dari toko ini akan tampil setelah pembeli mengonfirmasi pesanan.</p>
        </div>
    @else
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-2 sm:gap-6 lg:grid-cols-3 xl:grid-cols-4">
            @foreach($completedLots as $lot)
                @php
                    $detailUrl = route('ikans.show', ['ikan' => $lot, 'return_url' => $returnUrl]);
                    $totalBid = (int) ($lot->bids_count ?? 0);
                    $finalPrice = $lot->transaksi?->harga_final ?? $lot->harga_tertinggi;
                @endphp

                <article class="lot-card rounded-2xl overflow-hidden bg-white transition-all duration-200">
                    <a href="{{ $detailUrl }}" class="block sm:hidden">
                        <div class="market-lot-media-mobile bg-gradient-to-br from-slate-50 to-slate-100">
                            @if($lot->foto)
                                <img src="{{ publicStorageUrl($lot->foto) }}" alt="{{ $lot->nama_ikan }}" class="opacity-90" loading="lazy" decoding="async" sizes="50vw">
                            @else
                                <div class="flex h-full items-center justify-center px-3 text-center">
                                    <span class="text-xs font-semibold text-slate-400">Tidak ada foto</span>
                                </div>
                            @endif

                            <div class="absolute left-2 top-2">
                                <span class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[9px] font-bold {{ $lot->isLelangTurun() ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">
                                    {{ $lot->isLelangTurun() ? 'Turun' : 'Naik' }}
                                </span>
                            </div>

                            <div class="absolute right-2 top-2">
                                <span class="rounded-full bg-slate-500 px-1.5 py-0.5 text-[9px] font-black text-white">{{ strtoupper(lotStatusLabel($lot->status)) }}</span>
                            </div>
                        </div>

                        <div class="p-3">
                            <h3 class="market-lot-title text-sm font-bold leading-snug text-slate-900">{{ $lot->nama_ikan }}</h3>
                            <p class="market-lot-meta mt-0.5 text-[11px] text-slate-500">
                                {{ $lot->berat }} kg &bull; {{ $lot->kondisi === 'beku' ? 'Frozen' : ucfirst($lot->kondisi) }}
                            </p>
                        </div>

                        <div class="px-3 pb-3">
                            <div class="mb-2.5 border-t border-slate-100"></div>
                            <div class="flex items-end justify-between gap-1">
                                <div class="min-w-0">
                                    <p class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Harga final</p>
                                    <p class="mt-0.5 text-[13px] font-black leading-tight text-emerald-700">{{ formatRupiah($finalPrice) }}</p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Bid</p>
                                    <p class="mt-0.5 text-[13px] font-black leading-tight text-slate-700">{{ number_format($totalBid) }}</p>
                                </div>
                            </div>

                            <div class="mt-2.5 flex items-center justify-between gap-1">
                                <p class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Selesai</p>
                                <span class="rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">{{ $lot->waktu_selesai->format('d M Y') }}</span>
                            </div>
                        </div>
                    </a>

                    <div class="lot-card-desktop hidden sm:flex sm:flex-col">
                        <div class="lot-card-img-wrap bg-gradient-to-br from-slate-50 to-slate-100">
                            @if($lot->foto)
                                <img src="{{ publicStorageUrl($lot->foto) }}" alt="{{ $lot->nama_ikan }}" class="opacity-90" loading="lazy" decoding="async" sizes="(max-width: 1280px) 33vw, 25vw">
                            @else
                                <div class="flex h-full items-center justify-center">
                                    <span class="text-base font-semibold text-slate-400">Tidak ada foto</span>
                                </div>
                            @endif

                            <div class="absolute top-3 left-3">
                                <span class="inline-flex items-center rounded-full px-2 py-1 text-[10px] font-bold {{ $lot->isLelangTurun() ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">
                                    {{ $lot->isLelangTurun() ? 'Lelang Turun' : 'Lelang Naik' }}
                                </span>
                            </div>

                            <div class="absolute top-3 right-3">
                                <span class="rounded-full bg-slate-500 px-2 py-1 text-[10px] font-black text-white">{{ strtoupper(lotStatusLabel($lot->status)) }}</span>
                            </div>
                        </div>

                        <div class="lot-card-body">
                            <h3 class="font-bold leading-tight text-slate-900 text-lg">{{ $lot->nama_ikan }}</h3>
                            <div class="mt-2 flex items-center gap-2 flex-wrap text-sm text-slate-500">
                                <span>{{ $lot->berat }} kg</span>
                                <span>&bull;</span>
                                <span class="capitalize">{{ $lot->kondisi === 'beku' ? 'Frozen' : $lot->kondisi }}</span>
                                @if($lot->estimasi_jumlah_ekor)
                                    <span>&bull;</span>
                                    <span>{{ $lot->estimasi_jumlah_ekor }} ekor</span>
                                @endif
                            </div>

                            <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                                <div class="rounded-lg border border-slate-100 bg-slate-50 px-2 py-2">
                                    <p class="text-[10px] uppercase tracking-wide text-slate-400">Total Bid</p>
                                    <p class="mt-1 font-black text-slate-700">{{ number_format($totalBid) }}</p>
                                </div>
                                <div class="rounded-lg border border-slate-100 bg-slate-50 px-2 py-2">
                                    <p class="text-[10px] uppercase tracking-wide text-slate-400">Selesai</p>
                                    <p class="mt-1 font-black text-slate-700">{{ $lot->waktu_selesai->format('d M Y') }}</p>
                                </div>
                            </div>

                            <div class="border-t border-slate-100 pt-3 mt-3" style="min-height: 74px;">
                                <p class="text-xs text-slate-400">Harga final</p>
                                <p class="text-xl font-black text-emerald-700">{{ formatRupiah($finalPrice) }}</p>
                            </div>

                            <div class="lot-card-cta">
                                <x-secondary-action-link :href="$detailUrl" class="flex w-full text-sm">
                                    Lihat Detail
                                </x-secondary-action-link>
                            </div>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
        <div class="mt-5">
            {{ $completedLots->links() }}
        </div>
    @endif
</section>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const galleryFrame = document.querySelector('[data-store-gallery-frame]');
    const identityPanel = document.querySelector('[data-store-identity-panel]');

    if (!galleryFrame || !identityPanel) {
        return;
    }

    const desktopQuery = window.matchMedia('(min-width: 1024px)');
    let syncFrameHeightRaf = null;

    const syncFrameHeight = () => {
        if (syncFrameHeightRaf !== null) {
            window.cancelAnimationFrame(syncFrameHeightRaf);
        }

        syncFrameHeightRaf = window.requestAnimationFrame(() => {
            syncFrameHeightRaf = null;

            if (!desktopQuery.matches) {
                galleryFrame.style.height = '';
                return;
            }

            const nextHeight = Math.ceil(identityPanel.getBoundingClientRect().height);

            if (nextHeight > 0) {
                galleryFrame.style.height = `${nextHeight}px`;
            }
        });
    };

    syncFrameHeight();
    window.addEventListener('load', syncFrameHeight, { passive: true });
    window.addEventListener('resize', syncFrameHeight, { passive: true });

    if (typeof desktopQuery.addEventListener === 'function') {
        desktopQuery.addEventListener('change', syncFrameHeight);
    } else if (typeof desktopQuery.addListener === 'function') {
        desktopQuery.addListener(syncFrameHeight);
    }

    if (typeof ResizeObserver === 'function') {
        const observer = new ResizeObserver(syncFrameHeight);
        observer.observe(identityPanel);
    }
});
</script>
@endpush
@endsection
