@extends('layouts.app')
@section('title', 'Daftar Lelang Ikan')

@section('content')
@php
    $tipeLelang = $tipeLelang ?? 'semua';
    $fokusStat = $fokusStat ?? 'semua';
    $returnUrl = request()->fullUrl();
    $marketStats = $marketStats ?? [];

    $aktifTotal = (int) ($marketStats['aktif_total'] ?? 0);
    $menungguTotal = (int) ($marketStats['menunggu_total'] ?? 0);
    $berakhir30Menit = (int) ($marketStats['berakhir_30_menit'] ?? 0);
    $selesaiTotal = (int) ($marketStats['selesai_total'] ?? 0);

    $isBerlangsungEmpty = $lelangBerlangsung->isEmpty();
    $isSelesaiEmpty = $lelangSelesai->isEmpty();
    $now = now();

    $tipeLabel = match ($tipeLelang) {
        'naik' => 'Fokus: Lelang Naik',
        'turun' => 'Fokus: Lelang Turun',
        default => 'Fokus: Semua Lelang',
    };

    $fokusLabel = match ($fokusStat) {
        'aktif' => 'Lot Aktif',
        'menunggu' => 'Menunggu Tayang',
        'hampir_selesai' => 'Segera Berakhir',
        'selesai' => 'Lelang Selesai',
        'terpopuler' => 'Lot Terpopuler',
        default => 'Semua Kategori',
    };

    $showBerlangsungSection = $fokusStat !== 'selesai';
    $showSelesaiSection = in_array($fokusStat, ['semua', 'selesai'], true);

    $berlangsungSectionTitle = match ($fokusStat) {
        'aktif' => 'Lot Aktif',
        'menunggu' => 'Lot Menunggu Tayang',
        'hampir_selesai' => 'Lot Segera Berakhir',
        'terpopuler' => 'Lot Terpopuler',
        default => 'Lelang Sedang Berlangsung',
    };

    $berlangsungSectionDesc = match ($fokusStat) {
        'aktif' => 'Hanya lot yang saat ini aktif dan bisa diikuti bidding.',
        'menunggu' => 'Hanya lot yang terjadwal tayang dan belum aktif.',
        'hampir_selesai' => 'Hanya lot aktif yang akan berakhir dalam 30 menit.',
        'terpopuler' => 'Lot aktif diurutkan dari jumlah bid terbanyak, lalu penawar unik terbanyak.',
        default => 'Campuran lot aktif dan lot yang akan mulai tayang.',
    };

    $berlangsungEmptyTitle = match ($fokusStat) {
        'aktif' => 'Belum Ada Lot Aktif',
        'menunggu' => 'Belum Ada Lot Menunggu Tayang',
        'hampir_selesai' => 'Belum Ada Lot yang Segera Berakhir',
        'terpopuler' => 'Belum Ada Lot Populer',
        default => 'Belum Ada Lelang Berlangsung',
    };

    $berlangsungEmptyDesc = match ($fokusStat) {
        'aktif' => 'Lot aktif akan muncul di bagian ini.',
        'menunggu' => 'Lot terjadwal akan muncul di bagian ini.',
        'hampir_selesai' => 'Lot yang masuk tenggat akhir akan muncul di bagian ini.',
        'terpopuler' => 'Mulai bidding agar lot paling diminati muncul di bagian ini.',
        default => 'Lot aktif akan muncul di bagian ini.',
    };

    $selesaiSectionTitle = $fokusStat === 'selesai'
        ? 'Lelang Selesai'
        : 'Riwayat Lelang Selesai';

    $selesaiSectionDesc = $fokusStat === 'selesai'
        ? 'Hanya lot dengan status selesai atau terbayar.'
        : 'Status selesai dan terbayar untuk pelacakan histori harga.';

    $selesaiEmptyTitle = $fokusStat === 'selesai'
        ? 'Belum Ada Lot Selesai'
        : 'Belum Ada Riwayat Lelang Selesai';

    $selesaiEmptyDesc = $fokusStat === 'selesai'
        ? 'Lot selesai atau terbayar akan tampil di bagian ini.'
        : 'Lot yang selesai akan tampil otomatis di bagian ini.';

    $baseQueryParams = request()->except(['fokus', 'berlangsungPage', 'selesaiPage']);
    $focusUrl = function (string $focus) use ($baseQueryParams): string {
        $params = $baseQueryParams;

        if ($focus !== 'semua') {
            $params['fokus'] = $focus;
        }

        return route('ikans.index', $params);
    };

    $baseMarketplaceUrl = $focusUrl('semua');
    $focusCardUrls = [
        'aktif' => $fokusStat === 'aktif' ? $baseMarketplaceUrl : $focusUrl('aktif'),
        'menunggu' => $fokusStat === 'menunggu' ? $baseMarketplaceUrl : $focusUrl('menunggu'),
        'hampir_selesai' => $fokusStat === 'hampir_selesai' ? $baseMarketplaceUrl : $focusUrl('hampir_selesai'),
        'selesai' => $fokusStat === 'selesai' ? $baseMarketplaceUrl : $focusUrl('selesai'),
        'terpopuler' => $fokusStat === 'terpopuler' ? $baseMarketplaceUrl : $focusUrl('terpopuler'),
    ];

    $typeFilterBaseParams = $fokusStat !== 'semua' ? ['fokus' => $fokusStat] : [];
    $allTypeUrl = route('ikans.index', $typeFilterBaseParams);
    $naikTypeUrl = route('ikans.index', ['tipe_lelang' => 'naik', ...$typeFilterBaseParams]);
    $turunTypeUrl = route('ikans.index', ['tipe_lelang' => 'turun', ...$typeFilterBaseParams]);
@endphp

<style>
    .market-hero {
        background:
            radial-gradient(circle at 10% 15%, rgba(34, 211, 238, 0.22), transparent 36%),
            radial-gradient(circle at 90% 0%, rgba(14, 165, 233, 0.14), transparent 34%),
            linear-gradient(150deg, #f8fdff 0%, #eef9ff 52%, #f6fbff 100%);
    }

    .metric-card {
        background: rgba(255, 255, 255, 0.88);
        border: 1px solid rgba(186, 230, 253, 0.68);
        box-shadow: 0 12px 24px -20px rgba(12, 74, 110, 0.45);
        backdrop-filter: blur(1px);
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .market-metric-link:hover {
        transform: translateY(-2px);
        border-color: rgba(6, 182, 212, 0.45);
        box-shadow: 0 18px 30px -20px rgba(15, 23, 42, 0.58);
    }

    .market-metric-active {
        border-color: rgba(8, 145, 178, 0.78);
        box-shadow:
            0 0 0 3px rgba(34, 211, 238, 0.18),
            0 16px 28px -20px rgba(8, 145, 178, 0.72);
    }

    .market-metric-feature {
        position: relative;
        display: flex;
        min-height: 118px;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        isolation: isolate;
        background:
            radial-gradient(circle at 14% 20%, rgba(34, 211, 238, 0.18), transparent 34%),
            radial-gradient(circle at 88% 78%, rgba(45, 212, 191, 0.14), transparent 30%),
            linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(236, 248, 255, 0.98) 100%);
        border-color: rgba(103, 232, 249, 0.8);
        box-shadow:
            0 14px 26px -22px rgba(8, 145, 178, 0.58),
            inset 0 1px 0 rgba(255, 255, 255, 0.72);
    }

    .market-metric-feature::before {
        content: "";
        position: absolute;
        inset: 10px;
        border-radius: 1rem;
        border: 1px solid rgba(255, 255, 255, 0.72);
        background:
            linear-gradient(180deg, rgba(255, 255, 255, 0.4), rgba(255, 255, 255, 0.08));
        pointer-events: none;
        z-index: 0;
    }

    .market-metric-feature::after {
        content: "";
        position: absolute;
        inset: auto -10% 0;
        height: 58%;
        background: linear-gradient(180deg, rgba(14, 165, 233, 0), rgba(14, 165, 233, 0.12));
        opacity: 0;
        transform: translateY(14px);
        transition: opacity 0.2s ease, transform 0.2s ease;
        pointer-events: none;
        z-index: 0;
    }

    .market-metric-feature:hover::after,
    .market-metric-feature.market-metric-active::after {
        opacity: 1;
        transform: translateY(0);
    }

    .market-metric-feature-label {
        position: relative;
        z-index: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: min(100%, 17rem);
        padding: 0.85rem 1.4rem;
        border-radius: 999px;
        border: 1px solid rgba(14, 165, 233, 0.16);
        background: rgba(255, 255, 255, 0.82);
        box-shadow: 0 12px 20px -18px rgba(8, 145, 178, 0.85);
        text-align: center;
        font-size: 0.96rem;
        font-weight: 900;
        letter-spacing: 0.24em;
        text-transform: uppercase;
        color: #0f4c81;
    }

    .market-metric-feature.market-metric-active .market-metric-feature-label {
        border-color: rgba(14, 165, 233, 0.28);
        background: rgba(255, 255, 255, 0.9);
        color: #0a4f76;
    }

    /* =============================================
       LOT CARD — perbaikan utama
       ============================================= */

    .lot-card {
        border: 1px solid rgba(226, 232, 240, 0.95);
        box-shadow: 0 14px 26px -22px rgba(15, 23, 42, 0.55);
        /* Flex column agar konten bisa stretch dan tombol nempel bawah */
        display: flex;
        flex-direction: column;
    }

    .lot-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 22px 32px -24px rgba(8, 47, 73, 0.65);
    }

    /* Body konten (bawah foto) flex column & grow */
    /* NOTE: display untuk lot-card-mobile/desktop diatur via Tailwind (block sm:hidden / hidden sm:flex) */
    .lot-card-desktop {
        flex: 1;
        flex-direction: column;
    }

    .lot-card-body {
        display: flex;
        flex-direction: column;
        flex: 1;
        padding: 1rem 1.25rem 1.25rem;
    }

    .lot-card-cta {
        margin-top: auto;
        padding-top: 0.75rem;
    }

    /* Foto — tinggi fixed, selalu crop center */
    .lot-card-img-wrap {
        position: relative;
        width: 100%;
        height: 200px; /* fixed height desktop */
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

    /* Mobile foto */
    .market-lot-media-mobile {
        position: relative;
        height: 9.5rem;
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

    /* Judul & meta teks */
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

    .market-single-row {
        display: flex;
        gap: 0.5rem;
        overflow-x: auto;
        padding-bottom: 0.25rem;
        scrollbar-width: none;
    }

    .market-single-row::-webkit-scrollbar {
        display: none;
    }

    .market-single-row > * {
        flex: 0 0 auto;
        white-space: nowrap;
    }

    @keyframes revealUp {
        from {
            opacity: 0;
            transform: translateY(14px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .reveal-up {
        opacity: 0;
        animation: revealUp 0.55s ease-out forwards;
    }

    @media (prefers-reduced-motion: reduce) {
        .metric-card,
        .lot-card {
            transition: none;
        }

        .reveal-up {
            animation: none;
            opacity: 1;
        }
    }

    @media (max-width: 639px) {
        .market-lot-media-mobile {
            height: 8.75rem;
        }

        .market-metric-feature {
            min-height: 94px;
        }

        .market-metric-feature::before {
            inset: 8px;
        }

        .market-metric-feature-label {
            min-width: calc(100% - 1rem);
            padding: 0.78rem 1rem;
            font-size: 0.8rem;
            letter-spacing: 0.18em;
        }
    }
</style>

<section class="market-hero relative overflow-hidden rounded-3xl border border-cyan-100/80 px-5 py-6 sm:px-8 sm:py-9 mb-8">
    <div class="absolute -right-10 -bottom-12 w-44 h-44 rounded-full bg-cyan-200/35 blur-2xl"></div>
    <div class="absolute -left-10 -top-16 w-40 h-40 rounded-full bg-sky-200/40 blur-2xl"></div>

    <div class="relative grid gap-6 lg:grid-cols-[1.3fr,0.9fr]">
        <div>
            <p class="hidden sm:inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-extrabold uppercase tracking-[0.14em] text-cyan-700 bg-cyan-100/70 border border-cyan-200/70">
                {{ $tipeLabel }}
            </p>
            <h1 class="mt-2 sm:mt-4 text-2xl sm:text-4xl font-black tracking-tight text-slate-900">Marketplace Lelang Ikan</h1>
            <p class="mt-2 text-sm sm:text-base text-slate-600 max-w-2xl">
                Lihat lot yang sedang tayang, segera berakhir, atau sudah selesai.
            </p>
            <div class="market-single-row mt-4 hidden sm:flex sm:flex-wrap sm:overflow-visible sm:pb-0">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">{{ $aktifTotal }} aktif</span>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800">{{ $menungguTotal }} menunggu</span>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-700">{{ $berakhir30Menit }} hampir selesai</span>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-2 sm:gap-3">
            <a href="{{ $focusCardUrls['aktif'] }}" class="metric-card market-metric-link rounded-2xl p-3 sm:p-4 {{ $fokusStat === 'aktif' ? 'market-metric-active' : '' }}">
                <p class="text-[10px] sm:text-[11px] font-bold tracking-wide uppercase text-slate-500">Lot Aktif</p>
                <p class="mt-1 text-xl sm:text-2xl font-black text-slate-900">{{ number_format($aktifTotal) }}</p>
                <p class="mt-1 text-[10px] sm:text-[11px] font-semibold text-slate-500">
                    <span class="sm:hidden">{{ $fokusStat === 'aktif' ? 'Reset' : 'Live' }}</span>
                    <span class="hidden sm:inline">{{ $fokusStat === 'aktif' ? 'Klik untuk tampilkan semua' : 'Hanya lot yang aktif sekarang' }}</span>
                </p>
            </a>
            <a href="{{ $focusCardUrls['menunggu'] }}" class="metric-card market-metric-link rounded-2xl p-3 sm:p-4 {{ $fokusStat === 'menunggu' ? 'market-metric-active' : '' }}">
                <p class="text-[10px] sm:text-[11px] font-bold tracking-wide uppercase text-slate-500">Menunggu Tayang</p>
                <p class="mt-1 text-xl sm:text-2xl font-black text-slate-900">{{ number_format($menungguTotal) }}</p>
                <p class="mt-1 text-[10px] sm:text-[11px] font-semibold text-slate-500">
                    <span class="sm:hidden">{{ $fokusStat === 'menunggu' ? 'Reset' : 'Tunggu' }}</span>
                    <span class="hidden sm:inline">{{ $fokusStat === 'menunggu' ? 'Klik untuk tampilkan semua' : 'Lot terjadwal yang belum aktif' }}</span>
                </p>
            </a>
            <a href="{{ $focusCardUrls['hampir_selesai'] }}" class="metric-card market-metric-link rounded-2xl p-3 sm:p-4 {{ $fokusStat === 'hampir_selesai' ? 'market-metric-active' : '' }}">
                <p class="text-[10px] sm:text-[11px] font-bold tracking-wide uppercase text-slate-500">Segera Berakhir</p>
                <p class="mt-1 text-xl sm:text-2xl font-black text-rose-700">{{ number_format($berakhir30Menit) }}</p>
                <p class="mt-1 text-[10px] sm:text-[11px] font-semibold text-slate-500">
                    <span class="sm:hidden">{{ $fokusStat === 'hampir_selesai' ? 'Reset' : '<=30m' }}</span>
                    <span class="hidden sm:inline">{{ $fokusStat === 'hampir_selesai' ? 'Klik untuk tampilkan semua' : 'Lot aktif yang segera selesai' }}</span>
                </p>
            </a>
            <a href="{{ $focusCardUrls['selesai'] }}" class="metric-card market-metric-link rounded-2xl p-3 sm:p-4 {{ $fokusStat === 'selesai' ? 'market-metric-active' : '' }}">
                <p class="text-[10px] sm:text-[11px] font-bold tracking-wide uppercase text-slate-500">Lelang Selesai</p>
                <p class="mt-1 text-xl sm:text-2xl font-black text-slate-900">{{ number_format($selesaiTotal) }}</p>
                <p class="mt-1 text-[10px] sm:text-[11px] font-semibold text-slate-500">
                    <span class="sm:hidden">{{ $fokusStat === 'selesai' ? 'Reset' : 'Riwayat' }}</span>
                    <span class="hidden sm:inline">{{ $fokusStat === 'selesai' ? 'Klik untuk tampilkan semua' : 'Lot dengan status selesai/terbayar' }}</span>
                </p>
            </a>
            <a
                href="{{ $focusCardUrls['terpopuler'] }}"
                class="metric-card market-metric-link market-metric-feature rounded-2xl p-3 sm:p-4 col-span-2 {{ $fokusStat === 'terpopuler' ? 'market-metric-active' : '' }}"
                aria-label="{{ $fokusStat === 'terpopuler' ? 'Reset filter lot terpopuler' : 'Tampilkan lot terpopuler, diurutkan dari jumlah bid terbanyak ke paling sedikit' }}"
                title="{{ $fokusStat === 'terpopuler' ? 'Klik untuk tampilkan semua' : 'Urutkan lot aktif dari bid terbanyak ke paling sedikit' }}"
            >
                <span class="market-metric-feature-label">Lot Terpopuler</span>
            </a>
        </div>
    </div>
</section>

@include('components.auction-type-filter', [
    'tipeLelang' => $tipeLelang,
    'allUrl' => $allTypeUrl,
    'naikUrl' => $naikTypeUrl,
    'turunUrl' => $turunTypeUrl,
    'marginClass' => 'mb-10',
])

@if($fokusStat !== 'semua')
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-2xl border border-blue-100/90 bg-white/90 px-4 py-3">
        <p class="text-sm text-slate-700">
            Mode fokus aktif:
            <span class="font-extrabold text-slate-900">{{ $fokusLabel }}</span>
        </p>
        <a href="{{ $baseMarketplaceUrl }}" class="inline-flex items-center justify-center rounded-xl border border-blue-200 bg-blue-600 hover:bg-blue-700 px-4 py-2 text-xs font-extrabold tracking-wide text-white transition">
            Tampilkan semua
        </a>
    </div>
@endif

@if($isBerlangsungEmpty && $isSelesaiEmpty)
    <div class="text-center py-24 bg-white rounded-3xl border border-slate-200">
        <div class="mx-auto w-12 h-12 rounded-2xl bg-slate-100 flex items-center justify-center mb-4">
            <svg class="w-6 h-6 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 7h18M6 12h12m-9 5h6" />
            </svg>
        </div>
        <h3 class="text-xl font-bold text-slate-700">Belum Ada Lelang</h3>
        <p class="text-slate-500 mt-2">Belum ada lot yang cocok dengan filter yang dipilih.</p>
    </div>
@else
    @if($showBerlangsungSection)
        <section>
            <div class="mb-5 flex items-center justify-between gap-3 flex-wrap">
                <div>
                    <h2 class="text-2xl font-black tracking-tight text-slate-900">{{ $berlangsungSectionTitle }}</h2>
                    <p class="text-sm text-slate-500">{{ $berlangsungSectionDesc }}</p>
                </div>
                <span class="inline-flex items-center px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs font-bold border border-emerald-100">
                    {{ $lelangBerlangsung->total() }} lot
                </span>
            </div>

            @if($isBerlangsungEmpty)
                <div class="text-center py-16 bg-white rounded-3xl border border-slate-200">
                    <h3 class="text-lg font-bold text-slate-700">{{ $berlangsungEmptyTitle }}</h3>
                    <p class="text-slate-500 mt-2">{{ $berlangsungEmptyDesc }}</p>
                </div>
            @else
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-2 sm:gap-6 lg:grid-cols-3 xl:grid-cols-4">
                @foreach($lelangBerlangsung as $ikan)
                    @php
                        $minutesLeft = $ikan->status === 'aktif'
                            ? $now->diffInMinutes($ikan->waktu_selesai, false)
                            : null;
                        $isUrgent = $minutesLeft !== null && $minutesLeft <= 30;
                        $isCritical = $minutesLeft !== null && $minutesLeft <= 10;
                        $lotCounter = (($lelangBerlangsung->currentPage() - 1) * $lelangBerlangsung->perPage()) + $loop->iteration;
                        $totalBid = (int) ($ikan->bids_count ?? 0);
                        $buyNowTarget = $ikan->buyNowTarget();
                        $detailUrl = route('ikans.show', ['ikan' => $ikan, 'return_url' => $returnUrl]);
                        $mobileStartLabel = $ikan->waktu_mulai->format('d M');
                    @endphp

                    <article
                        class="lot-card reveal-up bg-white rounded-2xl overflow-hidden transition-all duration-200 {{ $isUrgent ? 'ring-1 ring-rose-200' : '' }}"
                        style="animation-delay: {{ ($loop->index % 12) * 45 }}ms;"
                        @if($ikan->status === 'aktif') x-data="countdown('{{ $ikan->waktu_mulai?->toISOString() }}', '{{ $ikan->waktu_selesai->toISOString() }}')" x-init="start()" @endif
                    >
                        {{-- ==================== MOBILE CARD ==================== --}}
                        <div class="lot-card-mobile block sm:hidden">
                            <a href="{{ $detailUrl }}" class="block">
                                <div class="market-lot-media-mobile">
                                    @if($ikan->foto)
                                        <img src="{{ publicStorageUrl($ikan->foto) }}" alt="{{ $ikan->nama_ikan }}" loading="lazy" decoding="async" sizes="50vw">
                                    @else
                                        <div class="flex h-full items-center justify-center px-3 text-center">
                                            <span class="text-xs text-slate-500 font-semibold">Tidak ada foto</span>
                                        </div>
                                    @endif

                                    <div class="absolute left-2 top-2 flex gap-1.5">
                                        <span class="inline-flex items-center rounded-full bg-slate-900/80 px-1.5 py-0.5 text-[9px] font-black tracking-wide text-white">Lot {{ $lotCounter }}</span>
                                        <span class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[9px] font-bold {{ $ikan->isLelangTurun() ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">
                                            {{ $ikan->isLelangTurun() ? 'Turun' : 'Naik' }}
                                        </span>
                                    </div>

                                    <div class="absolute right-2 top-2">
                                        @if($ikan->status === 'aktif')
                                            <span class="rounded-full px-1.5 py-0.5 text-[9px] font-black text-white {{ $isCritical ? 'bg-rose-500' : 'bg-emerald-500' }}">{{ $isCritical ? 'Kritis' : 'Aktif' }}</span>
                                        @else
                                            <span class="rounded-full bg-amber-300 px-1.5 py-0.5 text-[9px] font-black text-amber-900">Segera</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="p-3">
                                    <h3 class="market-lot-title text-sm font-bold leading-snug text-slate-900">{{ $ikan->nama_ikan }}</h3>
                                    <p class="market-lot-meta mt-0.5 text-[11px] text-slate-500">
                                        {{ $ikan->berat }} kg &bull; {{ $ikan->kondisi === 'beku' ? 'Frozen' : ucfirst($ikan->kondisi) }}
                                    </p>
                                </div>
                            </a>

                            <div class="px-3 pb-3">
                                <div class="mb-2.5 border-t border-slate-100"></div>

                                @if($ikan->status === 'aktif')
                                    <div class="flex items-end justify-between gap-1">
                                        <div class="min-w-0">
                                            <p class="text-[9px] font-bold uppercase tracking-wide text-slate-400">{{ $ikan->isLelangTurun() ? 'Bid teratas' : 'Harga saat ini' }}</p>
                                            <p class="mt-0.5 text-[13px] font-black leading-tight text-cyan-700">{{ formatRupiah($ikan->harga_tertinggi) }}</p>
                                        </div>
                                        <div class="shrink-0 text-right">
                                            <p class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Bid</p>
                                            <p class="mt-0.5 text-[13px] font-black leading-tight text-slate-700">{{ number_format($totalBid) }}</p>
                                        </div>
                                    </div>

                                    <div class="mt-2.5 flex items-center justify-between gap-1">
                                        <div class="min-w-0">
                                            <p class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Berakhir</p>
                                            <p class="mt-0.5 text-[10px] font-semibold text-slate-600">{{ $ikan->waktu_selesai->format('d M H:i') }}</p>
                                        </div>
                                        <span
                                            class="shrink-0 rounded-md px-2 py-0.5 text-[11px] font-bold bg-cyan-50 text-cyan-700"
                                            :class="danger ? 'bg-rose-50 text-rose-700' : 'bg-cyan-50 text-cyan-700'"
                                        ><span x-text="compactDisplay">{{ ceil($now->diffInMinutes($ikan->waktu_selesai, false)) > 60 ? floor($now->diffInMinutes($ikan->waktu_selesai, false)/60).'j' : $now->diffInMinutes($ikan->waktu_selesai, false).'m' }}</span></span>
                                    </div>

                                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                        <div
                                            class="h-full rounded-full transition-all duration-700 bg-cyan-500"
                                            :class="danger ? 'bg-rose-500' : 'bg-cyan-500'"
                                            :style="`width: max(${progress}%, 4%)`"
                                        ></div>
                                    </div>
                                @else
                                    <div class="flex items-end justify-between gap-1">
                                        <div class="min-w-0">
                                            <p class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Harga awal</p>
                                            <p class="mt-0.5 text-[13px] font-black leading-tight text-cyan-700">{{ formatRupiah($ikan->harga_tertinggi) }}</p>
                                        </div>
                                        <div class="shrink-0 text-right">
                                            <p class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Bid</p>
                                            <p class="mt-0.5 text-[13px] font-black leading-tight text-slate-700">{{ number_format($totalBid) }}</p>
                                        </div>
                                    </div>
                                    <div class="mt-2.5 rounded-lg bg-amber-50 border border-amber-100 px-2.5 py-1.5 flex items-center justify-between gap-1">
                                        <p class="text-[9px] font-bold uppercase tracking-wide text-amber-500">Mulai tayang</p>
                                        <p class="text-[11px] font-bold text-amber-700">{{ $ikan->waktu_mulai->format('d M H:i') }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- ==================== DESKTOP CARD ==================== --}}
                        <div class="lot-card-desktop hidden sm:flex sm:flex-col">
                            {{-- Foto — tinggi fixed, selalu crop --}}
                            <div class="lot-card-img-wrap">
                                @if($ikan->foto)
                                    <img
                                        src="{{ publicStorageUrl($ikan->foto) }}"
                                        alt="{{ $ikan->nama_ikan }}"
                                        loading="lazy"
                                        decoding="async"
                                        sizes="(max-width: 1280px) 33vw, 25vw"
                                    >
                                @else
                                    <div class="flex items-center justify-center h-full">
                                        <span class="text-base text-slate-500 font-semibold">Tidak ada foto</span>
                                    </div>
                                @endif

                                {{-- Badge kiri --}}
                                <div class="absolute top-3 left-3 flex gap-2">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-[10px] font-black bg-slate-900/80 text-white tracking-wide">Lot {{ $lotCounter }}</span>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-[10px] font-bold {{ $ikan->isLelangTurun() ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">
                                        {{ $ikan->isLelangTurun() ? 'Turun' : 'Naik' }}
                                    </span>
                                </div>

                                {{-- Badge kanan --}}
                                <div class="absolute top-3 right-3">
                                    @if($ikan->status === 'aktif')
                                        <span class="text-white text-[10px] font-black px-2 py-1 rounded-full {{ $isCritical ? 'bg-rose-500 animate-pulse' : 'bg-emerald-500' }}">{{ $isCritical ? 'KRITIS' : 'AKTIF' }}</span>
                                    @else
                                        <span class="bg-amber-300 text-amber-900 text-[10px] font-black px-2 py-1 rounded-full">SEGERA</span>
                                    @endif
                                </div>
                            </div>

                            {{-- Body konten --}}
                            <div class="lot-card-body">
                                <h3 class="font-bold text-slate-900 text-lg leading-tight">{{ $ikan->nama_ikan }}</h3>
                                <div class="flex items-center gap-2 mt-2 text-sm text-slate-500 flex-wrap">
                                    <span>{{ $ikan->berat }} kg</span>
                                    <span>&bull;</span>
                                    <span class="capitalize">{{ $ikan->kondisi === 'beku' ? 'Frozen' : $ikan->kondisi }}</span>
                                    @if($ikan->estimasi_jumlah_ekor)
                                        <span>&bull;</span>
                                        <span>{{ $ikan->estimasi_jumlah_ekor }} ekor</span>
                                    @endif
                                </div>
                                <p class="text-xs text-slate-400 mt-1">Oleh: {{ $ikan->user->name }}</p>

                                <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                                    <div class="rounded-lg bg-slate-50 px-2 py-2 border border-slate-100">
                                        <p class="text-[10px] uppercase tracking-wide text-slate-400">Total Bid</p>
                                        <p class="mt-1 font-black text-slate-700">{{ number_format($totalBid) }}</p>
                                    </div>
                                    <div class="rounded-lg bg-slate-50 px-2 py-2 border border-slate-100">
                                        <p class="text-[10px] uppercase tracking-wide text-slate-400">Kemasan</p>
                                        <p class="mt-1 font-black text-slate-700 capitalize">{{ $ikan->jenis_kemasan ?? 'Standar' }}</p>
                                    </div>
                                </div>

                                @if($isUrgent)
                                <div class="mt-3 flex items-center gap-2 text-xs flex-wrap">
                                    <span class="px-2 py-1 rounded-full {{ $isCritical ? 'bg-rose-100 text-rose-700' : 'bg-orange-100 text-orange-700' }} font-semibold">
                                        {{ $isCritical ? 'Urgensi tinggi' : 'Segera selesai' }}
                                    </span>
                                </div>
                                @endif

                                {{-- Harga & tutup — min-height agar baris kartu sejajar meski ada/tidak ada Beli Sekarang --}}
                                <div class="pt-3 border-t border-slate-100" style="min-height: 90px;">
                                    <div class="flex items-end justify-between gap-3">
                                        <div>
                                            <p class="text-xs text-slate-400">{{ $ikan->isLelangTurun() ? 'Bid teratas saat ini' : 'Harga saat ini' }}</p>
                                            <p class="text-xl font-black text-cyan-700">{{ formatRupiah($ikan->harga_tertinggi) }}</p>
                                        @if($buyNowTarget)
                                            <span class="inline-flex mt-1.5 px-2 py-0.5 rounded-full bg-cyan-50 text-cyan-700 font-semibold border border-cyan-100 text-xs">Beli Sekarang {{ formatRupiah($buyNowTarget) }}</span>
                                        @endif
                                        </div>
                                        <p class="text-[11px] text-slate-400 text-right shrink-0">
                                            Tutup<br>{{ $ikan->waktu_selesai->format('d M H:i') }}
                                        </p>
                                    </div>
                                </div>

                                {{-- Countdown + CTA: mt-auto agar selalu nempel bawah --}}
                                <div class="mt-auto pt-3">
                                    <div>
                                    @if($ikan->status === 'aktif')
                                        <div class="rounded-xl px-3 py-2 {{ $isUrgent ? 'bg-rose-50 border border-rose-100' : 'bg-cyan-50 border border-cyan-100' }}">
                                            <div class="flex items-center justify-between text-xs mb-2">
                                                <p class="font-semibold {{ $isUrgent ? 'text-rose-600' : 'text-cyan-700' }}">Berakhir dalam</p>
                                                <p x-text="progressLabel" class="font-bold {{ $isUrgent ? 'text-rose-700' : 'text-cyan-700' }}"></p>
                                            </div>
                                            <p x-text="display" class="text-sm font-black {{ $isUrgent ? 'text-rose-700' : 'text-cyan-800' }}">{{ $ikan->waktu_selesai->diffForHumans() }}</p>
                                            <div class="mt-2 h-1.5 rounded-full bg-white/80 overflow-hidden">
                                                <div class="h-full rounded-full transition-all duration-700" :class="danger ? 'bg-rose-500' : 'bg-cyan-500'" :style="`width: max(${progress}%, 4%)`"></div>
                                            </div>
                                        </div>
                                    @elseif($ikan->status === 'menunggu')
                                        <div class="text-center bg-amber-50 border border-amber-100 rounded-xl py-2">
                                            <p class="text-xs text-amber-700 font-semibold">Mulai: {{ $ikan->waktu_mulai->format('d M Y H:i') }}</p>
                                        </div>
                                    @endif
                                    </div>
                                    <div class="mt-3">
                                        <a href="{{ $detailUrl }}" class="block text-center bg-slate-900 hover:bg-slate-800 text-white font-bold py-2.5 rounded-xl transition text-sm">
                                            Lihat Detail
                                        </a>
                                    </div>
                                </div>{{-- end mt-auto --}}
                            </div>
                        </div>
                    </article>
                    @endforeach
                </div>
                <div class="mt-8">{{ $lelangBerlangsung->links() }}</div>
            @endif
        </section>
    @endif

    @if($showBerlangsungSection && $showSelesaiSection)
        <div class="my-12 border-t border-dashed border-slate-300"></div>
    @endif

    @if($showSelesaiSection)
        <section>
            <div class="mb-5 flex items-center justify-between gap-3 flex-wrap">
                <div>
                    <h2 class="text-2xl font-black tracking-tight text-slate-900">{{ $selesaiSectionTitle }}</h2>
                    <p class="text-sm text-slate-500">{{ $selesaiSectionDesc }}</p>
                </div>
                <span class="inline-flex items-center px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-bold border border-slate-200">
                    {{ $lelangSelesai->total() }} lot
                </span>
            </div>

            @if($isSelesaiEmpty)
                <div class="text-center py-16 bg-white rounded-3xl border border-slate-200">
                    <h3 class="text-lg font-bold text-slate-700">{{ $selesaiEmptyTitle }}</h3>
                    <p class="text-slate-500 mt-2">{{ $selesaiEmptyDesc }}</p>
                </div>
            @else
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-2 sm:gap-6 lg:grid-cols-3 xl:grid-cols-4">
                @foreach($lelangSelesai as $ikan)
                    @php
                        $totalBidSelesai = (int) ($ikan->bids_count ?? 0);
                        $detailUrl = route('ikans.show', ['ikan' => $ikan, 'return_url' => $returnUrl]);
                    @endphp

                    <article class="lot-card reveal-up bg-white rounded-2xl overflow-hidden transition-all duration-200" style="animation-delay: {{ ($loop->index % 12) * 45 }}ms;">
                        {{-- ==================== MOBILE SELESAI ==================== --}}
                        <a href="{{ $detailUrl }}" class="lot-card-mobile block sm:hidden">
                            <div class="market-lot-media-mobile bg-gradient-to-br from-slate-50 to-slate-100">
                                @if($ikan->foto)
                                    <img src="{{ publicStorageUrl($ikan->foto) }}" alt="{{ $ikan->nama_ikan }}" class="opacity-90" loading="lazy" decoding="async" sizes="50vw">
                                @else
                                    <div class="flex h-full items-center justify-center px-3 text-center">
                                        <span class="text-xs text-slate-500 font-semibold">Tidak ada foto</span>
                                    </div>
                                @endif
                                <div class="absolute top-2 right-2">
                                    @if($ikan->status === 'terbayar')
                                        <span class="rounded-full bg-cyan-500 px-1.5 py-0.5 text-[9px] font-bold text-white">{{ lotStatusLabel($ikan->status) }}</span>
                                    @else
                                        <span class="rounded-full bg-slate-500 px-1.5 py-0.5 text-[9px] font-bold text-white">{{ lotStatusLabel($ikan->status) }}</span>
                                    @endif
                                </div>

                                <div class="absolute top-2 left-2">
                                    <span class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[9px] font-bold {{ $ikan->isLelangTurun() ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">
                                        {{ $ikan->isLelangTurun() ? 'Turun' : 'Naik' }}
                                    </span>
                                </div>
                            </div>

                            <div class="p-3">
                                <h3 class="market-lot-title text-sm font-bold leading-snug text-slate-900">{{ $ikan->nama_ikan }}</h3>
                                <p class="market-lot-meta mt-0.5 text-[11px] text-slate-500">
                                    {{ $ikan->berat }} kg &bull; {{ $ikan->kondisi === 'beku' ? 'Frozen' : ucfirst($ikan->kondisi) }}
                                </p>

                                <div class="my-2.5 border-t border-slate-100"></div>

                                <div class="flex items-end justify-between gap-1">
                                    <div class="min-w-0">
                                        <p class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Harga penutupan</p>
                                        <p class="mt-0.5 text-[13px] font-black leading-tight text-slate-800">{{ formatRupiah($ikan->harga_tertinggi) }}</p>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <p class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Bid</p>
                                        <p class="mt-0.5 text-[13px] font-black leading-tight text-slate-700">{{ number_format($totalBidSelesai) }}</p>
                                    </div>
                                </div>

                                <div class="mt-2.5 flex items-center justify-between gap-1">
                                    <p class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Selesai</p>
                                    <span class="rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">{{ $ikan->waktu_selesai->format('d M Y') }}</span>
                                </div>
                            </div>
                        </a>

                        {{-- ==================== DESKTOP SELESAI ==================== --}}
                        <div class="lot-card-desktop hidden sm:flex sm:flex-col">
                            <div class="lot-card-img-wrap bg-gradient-to-br from-slate-50 to-slate-100">
                                @if($ikan->foto)
                                    <img src="{{ publicStorageUrl($ikan->foto) }}" alt="{{ $ikan->nama_ikan }}" class="opacity-90" loading="lazy" decoding="async" sizes="(max-width: 1280px) 33vw, 25vw">
                                @else
                                    <div class="flex items-center justify-center h-full">
                                        <span class="text-base text-slate-500 font-semibold">Tidak ada foto</span>
                                    </div>
                                @endif
                                <div class="absolute top-3 right-3">
                                    @if($ikan->status === 'terbayar')
                                        <span class="bg-cyan-500 text-white text-xs font-bold px-2 py-1 rounded-full">{{ lotStatusLabel($ikan->status) }}</span>
                                    @else
                                        <span class="bg-slate-500 text-white text-xs font-bold px-2 py-1 rounded-full">{{ lotStatusLabel($ikan->status) }}</span>
                                    @endif
                                </div>

                                <div class="absolute top-3 left-3">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-[10px] font-bold {{ $ikan->isLelangTurun() ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">
                                        {{ $ikan->isLelangTurun() ? 'Lelang Turun' : 'Lelang Naik' }}
                                    </span>
                                </div>
                            </div>

                            <div class="lot-card-body">
                                <h3 class="font-bold text-slate-900 text-lg leading-tight">{{ $ikan->nama_ikan }}</h3>
                                <div class="flex items-center gap-2 mt-2 text-sm text-slate-500 flex-wrap">
                                    <span>{{ $ikan->berat }} kg</span>
                                    <span>&bull;</span>
                                    <span class="capitalize">{{ $ikan->kondisi === 'beku' ? 'Frozen' : $ikan->kondisi }}</span>
                                    @if($ikan->estimasi_jumlah_ekor)
                                        <span>&bull;</span>
                                        <span>{{ $ikan->estimasi_jumlah_ekor }} ekor</span>
                                    @endif
                                </div>
                                <p class="text-xs text-slate-400 mt-1">Oleh: {{ $ikan->user->name }}</p>

                                <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                                    <div class="rounded-lg bg-slate-50 px-2 py-2 border border-slate-100">
                                        <p class="text-[10px] uppercase tracking-wide text-slate-400">Total Bid</p>
                                        <p class="mt-1 font-black text-slate-700">{{ number_format($totalBidSelesai) }}</p>
                                    </div>
                                    <div class="rounded-lg bg-slate-50 px-2 py-2 border border-slate-100">
                                        <p class="text-[10px] uppercase tracking-wide text-slate-400">Kemasan</p>
                                        <p class="mt-1 font-black text-slate-700 capitalize">{{ $ikan->jenis_kemasan ?? 'Standar' }}</p>
                                    </div>
                                </div>



                                <div class="mt-4 pt-3 border-t border-slate-100">
                                    <p class="text-xs text-slate-400">Harga Penutupan</p>
                                    <p class="text-xl font-black text-slate-800">{{ formatRupiah($ikan->harga_tertinggi) }}</p>
                                    <p class="text-xs text-slate-400 mt-1">Selesai: {{ $ikan->waktu_selesai->format('d M Y H:i') }}</p>
                                </div>

                                <div class="lot-card-cta">
                                    <x-secondary-action-link :href="$detailUrl" class="flex w-full text-sm">
                                        Lihat Riwayat
                                    </x-secondary-action-link>
                                </div>
                            </div>
                        </div>
                    </article>
                    @endforeach
                </div>
                <div class="mt-8">{{ $lelangSelesai->links() }}</div>
            @endif
        </section>
    @endif
@endif
@endsection

@push('scripts')
<script>
    function countdown(startDate, targetDate) {
        return {
            display: '',
            compactDisplay: '',
            progress: 0,
            progressLabel: '0%',
            danger: false,
            start() {
                const startTime = new Date(startDate).getTime();
                const endTime = new Date(targetDate).getTime();

                const update = () => {
                    const nowTime = Date.now();
                    const diff = endTime - nowTime;
                    const totalDuration = Math.max(endTime - startTime, 1);
                    const elapsedDuration = Math.max(nowTime - startTime, 0);

                    this.progress = Math.max(0, Math.min(100, (elapsedDuration / totalDuration) * 100));
                    this.progressLabel = `${Math.round(this.progress)}%`;
                    this.danger = diff <= 900000;

                    if (diff <= 0) {
                        this.display = 'Selesai';
                        this.compactDisplay = 'Selesai';
                        this.progress = 100;
                        this.progressLabel = '100%';
                        return;
                    }

                    const h = Math.floor(diff / 3600000);
                    const m = Math.floor((diff % 3600000) / 60000);
                    const s = Math.floor((diff % 60000) / 1000);
                    this.display = `${h}j ${m}m ${s}d`;
                    this.compactDisplay = h > 0
                        ? `${h}j ${m}m`
                        : (m > 0 ? `${m}m ${s}d` : `${s}d`);
                };

                update();
                setInterval(update, 1000);
            },
        };
    }
</script>
@endpush
