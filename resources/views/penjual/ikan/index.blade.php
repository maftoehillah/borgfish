@extends('layouts.app')
@section('title', 'Aktivitas Lot')

@section('content')
@php
    $tipeLelang = $tipeLelang ?? 'semua';
    $fokus = $fokus ?? 'semua';
    $returnUrl = request()->fullUrl();
    $sellerStats = $sellerStats ?? [];
    $aksiPrioritas = $aksiPrioritas ?? [];

    $totalLot = (int) ($sellerStats['total_lot'] ?? 0);
    $aktifTotal = (int) ($sellerStats['aktif'] ?? 0);
    $menungguTotal = (int) ($sellerStats['menunggu'] ?? 0);
    $menungguBayarTotal = (int) ($sellerStats['menunggu_bayar'] ?? 0);
    $perluJemputTotal = (int) ($sellerStats['perlu_jemput'] ?? 0);
    $jemputTerlambatTotal = (int) ($sellerStats['jemput_terlambat'] ?? 0);
    $pipelineSiapkanTotal = (int) ($sellerStats['pipeline_siapkan_total'] ?? 0);
    $pipelinePenjemputanTotal = (int) ($sellerStats['pipeline_penjemputan_total'] ?? 0);
    $pipelineSelesaiTotal = (int) ($sellerStats['pipeline_selesai_total'] ?? 0);
    $aksiPerluJemput = $aksiPrioritas['perlu_jemput'] ?? collect();
    $aksiPenjemputan = $aksiPrioritas['penjemputan'] ?? collect();
    $aksiSelesai = $aksiPrioritas['selesai'] ?? collect();

    $baseFilterParams = $tipeLelang !== 'semua'
        ? ['tipe_lelang' => $tipeLelang]
        : [];

    $baseEtalaseUrl = route('penjual.ikans.index', $baseFilterParams);

    $typeFilterBaseParams = $fokus !== 'semua'
        ? ['fokus' => $fokus]
        : [];

    $urlTotalLot = $fokus === 'total_lot'
        ? $baseEtalaseUrl
        : route('penjual.ikans.index', [...$baseFilterParams, 'fokus' => 'total_lot']);
    $urlMenungguTayang = $fokus === 'menunggu_tayang'
        ? $baseEtalaseUrl
        : route('penjual.ikans.index', [...$baseFilterParams, 'fokus' => 'menunggu_tayang']);
    $urlPerluJemput = $fokus === 'perlu_jemput'
        ? $baseEtalaseUrl
        : route('penjual.ikans.index', [...$baseFilterParams, 'fokus' => 'perlu_jemput']) . '#prioritas-perlu-jemput';
    $urlLotAktif = $fokus === 'lot_aktif'
        ? $baseEtalaseUrl
        : route('penjual.ikans.index', [...$baseFilterParams, 'fokus' => 'lot_aktif']);

    $fokusLabel = match ($fokus) {
        'total_lot' => 'Etalase Semua Lot',
        'menunggu_tayang' => 'Etalase Menunggu Tayang',
        'perlu_jemput' => 'Lot Perlu Jemput',
        'lot_aktif' => 'Etalase Lot Aktif',
        default => null,
    };

    $isAllEmpty = $belumBayar->isEmpty() && $sudahBayar->isEmpty() && $aktivitasLainnya->isEmpty();

    $sections = [
        [
            'title' => 'Belum Dibayar Pembeli',
            'titleShort' => 'Belum Bayar',
            'items' => $belumBayar,
            'badgeClass' => 'bg-amber-100 text-amber-700',
            'containerClass' => 'border-amber-200/80 bg-amber-50/30',
            'emptyText' => 'Belum ada lot menunggu pembayaran pada filter ini.',
        ],
        [
            'title' => 'Sudah Dibayar',
            'titleShort' => 'Lunas',
            'items' => $sudahBayar,
            'badgeClass' => 'bg-emerald-100 text-emerald-700',
            'containerClass' => 'border-emerald-200/80 bg-emerald-50/30',
            'emptyText' => 'Belum ada lot dengan pembayaran lunas pada filter ini.',
        ],
        [
            'title' => 'Aktivitas Lelang Lainnya',
            'titleShort' => 'Lainnya',
            'items' => $aktivitasLainnya,
            'badgeClass' => 'bg-slate-100 text-slate-700',
            'containerClass' => 'border-slate-200/80 bg-slate-50/40',
            'emptyText' => 'Belum ada lot pada kelompok aktivitas lainnya untuk filter ini.',
        ],
    ];

    $sellerNextAction = null;
    $nextPackingLot = $aksiPerluJemput->first();
    $nextPickupLot = $aksiPenjemputan->first();
    $nextDoneLot = $aksiSelesai->first();

    if ($nextPackingLot) {
        $sellerNextAction = [
            'badge' => 'Perlu Packing',
            'title' => 'Konfirmasi packing untuk ' . $nextPackingLot->nama_ikan,
            'description' => 'Pembeli sudah membayar. Upload foto packing, lokasi, waktu, dan catatan jika diperlukan.',
            'url' => route('penjual.ikans.show', ['ikan' => $nextPackingLot, 'return_url' => $returnUrl]),
            'cta' => 'Konfirmasi Packing',
            'class' => 'seller-priority-cta',
        ];
    } elseif ($nextPickupLot) {
        $trxNextPickup = $nextPickupLot->transaksi;
        $isPickupSubmitted = $trxNextPickup?->buyer_pickup_submitted_at !== null || (string) $trxNextPickup?->pickup_status === 'awaiting_pickup';
        $sellerNextAction = [
            'badge' => 'Validasi Jemput',
            'title' => ($isPickupSubmitted ? 'Validasi penjemput untuk ' : 'Pantau data penjemput ') . $nextPickupLot->nama_ikan,
            'description' => $isPickupSubmitted
                ? 'Cocokkan nama sopir, plat nomor, foto sopir, dan foto kendaraan saat penjemput datang.'
                : 'Packing sudah dikonfirmasi. Tunggu pembeli mengisi data sopir dan kendaraan penjemput.',
            'url' => route('penjual.ikans.show', ['ikan' => $nextPickupLot, 'return_url' => $returnUrl]),
            'cta' => $isPickupSubmitted ? 'Validasi Penjemput' : 'Lihat Detail',
            'class' => 'seller-priority-cta',
        ];
    } elseif ($nextDoneLot) {
        $sellerNextAction = [
            'badge' => 'Menunggu Buyer',
            'title' => 'Pantau konfirmasi selesai untuk ' . $nextDoneLot->nama_ikan,
            'description' => 'Penjemput sudah divalidasi. Transaksi menunggu pembeli memberi review dan konfirmasi selesai.',
            'url' => route('penjual.ikans.show', ['ikan' => $nextDoneLot, 'return_url' => $returnUrl]),
            'cta' => 'Lihat Detail',
            'class' => 'seller-priority-cta',
        ];
    }
@endphp

<style>
    .seller-hero {
        background:
            radial-gradient(circle at 10% 10%, rgba(59, 130, 246, 0.14), transparent 32%),
            radial-gradient(circle at 85% 95%, rgba(34, 197, 94, 0.12), transparent 28%),
            linear-gradient(145deg, #f8fcff 0%, #f2f7ff 48%, #f9fcff 100%);
    }

    .seller-metric {
        background: rgba(255, 255, 255, 0.9);
        border: 1px solid rgba(186, 230, 253, 0.68);
        box-shadow: 0 12px 24px -20px rgba(15, 23, 42, 0.6);
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .seller-metric-link:hover {
        transform: translateY(-2px);
        border-color: rgba(6, 182, 212, 0.45);
        box-shadow: 0 18px 30px -20px rgba(15, 23, 42, 0.58);
    }

    .seller-metric-active {
        border-color: rgba(124, 58, 237, 0.55);
        box-shadow:
            0 0 0 3px rgba(124, 58, 237, 0.12),
            0 16px 28px -20px rgba(15, 23, 42, 0.72);
    }

    .seller-priority-cta {
        background: linear-gradient(135deg, #0891b2 0%, #2563eb 100%);
        box-shadow: 0 14px 28px -16px rgba(37, 99, 235, 0.85);
        transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
    }

    .seller-priority-cta:hover {
        transform: translateY(-1px);
        box-shadow: 0 18px 32px -14px rgba(8, 145, 178, 0.88);
        filter: brightness(1.02);
    }

    .seller-priority-cta:focus-visible {
        outline: none;
        box-shadow:
            0 0 0 3px rgba(34, 211, 238, 0.25),
            0 18px 32px -14px rgba(8, 145, 178, 0.88);
    }

    .seller-priority-cta-warning {
        background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%);
        box-shadow: 0 14px 28px -16px rgba(217, 119, 6, 0.88);
        transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
    }

    .seller-priority-cta-warning:hover {
        transform: translateY(-1px);
        box-shadow: 0 18px 32px -14px rgba(245, 158, 11, 0.9);
        filter: brightness(1.03);
    }

    .seller-priority-cta-warning:focus-visible {
        outline: none;
        box-shadow:
            0 0 0 3px rgba(251, 191, 36, 0.28),
            0 18px 32px -14px rgba(245, 158, 11, 0.9);
    }

    .seller-priority-cta-urgent {
        background: linear-gradient(135deg, #dc2626 0%, #f97316 100%);
        box-shadow: 0 16px 30px -16px rgba(220, 38, 38, 0.9);
        animation: seller-cta-pulse 1.8s ease-in-out infinite;
        transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
    }

    .seller-priority-cta-urgent:hover {
        transform: translateY(-1px);
        box-shadow: 0 20px 36px -14px rgba(234, 88, 12, 0.92);
        filter: brightness(1.03);
    }

    .seller-priority-cta-urgent:focus-visible {
        outline: none;
        box-shadow:
            0 0 0 3px rgba(251, 146, 60, 0.3),
            0 20px 36px -14px rgba(234, 88, 12, 0.92);
    }

    .seller-priority-cta-danger {
        background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
        box-shadow: 0 14px 28px -16px rgba(220, 38, 38, 0.9);
        transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
    }

    .seller-priority-cta-danger:hover {
        transform: translateY(-1px);
        box-shadow: 0 18px 32px -14px rgba(185, 28, 28, 0.94);
        filter: brightness(1.02);
    }

    .seller-priority-cta-danger:focus-visible {
        outline: none;
        box-shadow:
            0 0 0 3px rgba(248, 113, 113, 0.3),
            0 18px 32px -14px rgba(185, 28, 28, 0.94);
    }

    @keyframes seller-cta-pulse {
        0%,
        100% {
            box-shadow: 0 16px 30px -16px rgba(220, 38, 38, 0.9);
        }
        50% {
            box-shadow:
                0 0 0 5px rgba(251, 113, 133, 0.18),
                0 22px 38px -14px rgba(234, 88, 12, 0.95);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .seller-metric,
        .seller-priority-cta,
        .seller-priority-cta-warning,
        .seller-priority-cta-urgent,
        .seller-priority-cta-danger {
            transition: none;
        }

        .seller-priority-cta-urgent {
            animation: none;
        }
    }

    .seller-chip-row {
        display: flex;
        gap: 0.5rem;
        overflow-x: auto;
        padding-bottom: 0.25rem;
        scrollbar-width: none;
    }

    .seller-chip-row::-webkit-scrollbar {
        display: none;
    }

    .seller-chip-row > * {
        flex: 0 0 auto;
        white-space: nowrap;
    }
</style>

<section class="seller-hero rounded-3xl border border-blue-100/70 px-6 py-7 sm:px-8 sm:py-9 mb-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="inline-flex items-center px-3 py-1 rounded-full text-xs font-extrabold tracking-[0.14em] uppercase text-blue-700 bg-blue-100/70 border border-blue-200/70">
                Aktivitas Lelang
            </p>
            <h1 class="mt-3 text-3xl sm:text-4xl font-black tracking-tight text-slate-900">Aktivitas Lot</h1>
            <p class="mt-2 text-slate-600 max-w-2xl">Lihat lot aktif, pembayaran, packing, dan penjemputan yang masih berjalan.</p>
            <div class="seller-chip-row mt-4">
                <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1.5 text-[11px] font-extrabold text-emerald-800">{{ $aktifTotal }} lot aktif</span>
                <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1.5 text-[11px] font-extrabold text-amber-800">{{ $menungguBayarTotal }} menunggu bayar</span>
                <span class="inline-flex items-center rounded-full bg-cyan-100 px-3 py-1.5 text-[11px] font-extrabold text-cyan-800">{{ $perluJemputTotal }} perlu jemput</span>
                @if($jemputTerlambatTotal > 0)
                    <span class="inline-flex items-center rounded-full bg-rose-100 px-3 py-1.5 text-[11px] font-extrabold text-rose-700">{{ $jemputTerlambatTotal }} terlambat diproses</span>
                @endif
                
            </div>
        </div>
        <div class="flex w-full flex-wrap gap-3 sm:w-auto">
            <a href="{{ route('penjual.ikans.create', ['return_url' => $returnUrl]) }}" class="seller-priority-cta inline-flex min-h-[48px] w-full items-center justify-center gap-2 rounded-xl px-6 py-3 text-sm font-extrabold tracking-wide text-white sm:w-auto">
                <span class="inline-flex h-2.5 w-2.5 rounded-full bg-white shadow-[0_0_0_3px_rgba(255,255,255,0.25)]" aria-hidden="true"></span>
                Upload Ikan Baru
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M3 10a1 1 0 0 1 1-1h9.59l-2.3-2.29a1 1 0 1 1 1.42-1.42l4 4a1 1 0 0 1 0 1.42l-4 4a1 1 0 0 1-1.42-1.42L13.59 11H4a1 1 0 0 1-1-1Z" clip-rule="evenodd" />
                </svg>
            </a>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <a href="{{ $urlTotalLot }}" class="seller-metric seller-metric-link block rounded-2xl p-4 {{ $fokus === 'total_lot' ? 'seller-metric-active' : '' }}">
            <p class="text-xs uppercase tracking-wide text-slate-500 font-bold">Total Lot</p>
            <p class="mt-1 text-2xl font-black text-slate-900">{{ number_format($totalLot) }}</p>
            <p class="mt-1 text-xs font-semibold text-slate-500">{{ $fokus === 'total_lot' ? 'Mode fokus aktif. Tap untuk kembali ke seluruh aktivitas.' : 'Lihat semua lot yang sedang Anda kelola.' }}</p>
        </a>
        <a href="{{ $urlMenungguTayang }}" class="seller-metric seller-metric-link block rounded-2xl p-4 {{ $fokus === 'menunggu_tayang' ? 'seller-metric-active' : '' }}">
            <p class="text-xs uppercase tracking-wide text-slate-500 font-bold">Menunggu Tayang</p>
            <p class="mt-1 text-2xl font-black text-slate-900">{{ number_format($menungguTotal) }}</p>
            <p class="mt-1 text-xs font-semibold text-slate-500">{{ $fokus === 'menunggu_tayang' ? 'Mode fokus aktif. Tap untuk lihat semua lagi.' : 'Cek lot yang belum live dan masih bisa disiapkan.' }}</p>
        </a>
        <a href="{{ $urlPerluJemput }}" class="seller-metric seller-metric-link block rounded-2xl p-4 {{ $fokus === 'perlu_jemput' ? 'seller-metric-active' : '' }}">
            <p class="text-xs uppercase tracking-wide text-slate-500 font-bold">Perlu Penjemputan</p>
            <p class="mt-1 text-2xl font-black text-cyan-700">{{ number_format($perluJemputTotal) }}</p>
            <p class="mt-1 text-xs font-semibold text-slate-500">{{ $fokus === 'perlu_jemput' ? 'Mode fokus aktif. Tap untuk kembali ke semua aktivitas.' : 'Prioritas untuk packing, validasi sopir, dan kendaraan penjemput.' }}</p>
        </a>
        <a href="{{ $urlLotAktif }}" class="seller-metric seller-metric-link block rounded-2xl p-4 {{ $fokus === 'lot_aktif' ? 'seller-metric-active' : '' }}">
            <p class="text-xs uppercase tracking-wide text-slate-500 font-bold">Lot Aktif</p>
            <p class="mt-1 text-2xl font-black text-emerald-700">{{ number_format($aktifTotal) }}</p>
            <p class="mt-1 text-xs font-semibold text-slate-500">{{ $fokus === 'lot_aktif' ? 'Mode fokus aktif. Tap untuk kembali ke seluruh daftar.' : 'Pantau lot yang sedang live dan butuh perhatian cepat.' }}</p>
        </a>
    </div>

    @if($fokusLabel)
        <div class="mt-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-2xl border border-blue-100/90 bg-white/90 px-4 py-3">
            <p class="text-sm text-slate-700">
                Mode fokus aktif:
                <span class="font-extrabold text-slate-900">{{ $fokusLabel }}</span>
            </p>
            <a href="{{ route('penjual.ikans.index', $baseFilterParams) }}" class="inline-flex min-h-[48px] items-center justify-center rounded-xl border border-blue-200 bg-blue-600 px-4 py-3 text-sm font-extrabold tracking-wide text-white transition hover:bg-blue-700">
                Tampilkan semua
            </a>
        </div>
    @endif
</section>

@if($sellerNextAction)
    <section class="mb-8 rounded-3xl border border-cyan-100 bg-white p-5 shadow-[0_16px_28px_-24px_rgba(15,23,42,0.55)]">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <span class="inline-flex items-center rounded-full bg-cyan-100 px-3 py-1.5 text-[11px] font-extrabold text-cyan-800">
                    {{ $sellerNextAction['badge'] }}
                </span>
                <h2 class="mt-3 text-xl font-black text-slate-900">{{ $sellerNextAction['title'] }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ $sellerNextAction['description'] }}</p>
            </div>
            <a href="{{ $sellerNextAction['url'] }}" class="{{ $sellerNextAction['class'] }} inline-flex min-h-[48px] w-full items-center justify-center rounded-xl px-5 py-3 text-sm font-extrabold tracking-wide text-white sm:w-auto">
                {{ $sellerNextAction['cta'] }}
            </a>
        </div>
    </section>
@endif

<section class="mb-8 grid grid-cols-1 lg:grid-cols-3 gap-4">
    <article id="prioritas-perlu-jemput" class="bg-white rounded-2xl border border-cyan-100 overflow-hidden scroll-mt-24">
        <div class="px-5 py-4 border-b border-cyan-100 flex items-center justify-between gap-2">
            <div>
                <h2 class="font-black text-slate-900">Siapkan Packing</h2>
                <p class="text-[11px] text-cyan-700 font-semibold mt-1">konfirmasi packing sebelum penjemputan</p>
            </div>
            <span class="inline-flex items-center justify-center shrink-0 whitespace-nowrap rounded-full bg-cyan-100 px-3 py-1.5 text-[11px] font-extrabold text-cyan-800">{{ $aksiPerluJemput->count() }} lot</span>
        </div>
        @if($aksiPerluJemput->isEmpty())
            <p class="px-5 py-4 text-sm text-slate-500">Belum ada lot yang perlu packing.</p>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach($aksiPerluJemput as $ikanAksi)
                    @php
                        $trxPacking = $ikanAksi->transaksi;
                    @endphp
                    <li class="px-5 py-4">
                        <p class="text-sm font-black text-slate-900">{{ $ikanAksi->nama_ikan }}</p>
                        <p class="mt-1 text-[11px] text-slate-600">Dibayar: {{ $trxPacking?->dibayar_pada?->format('d M Y H:i') ?? '-' }}</p>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-700">
                                Menunggu Konfirmasi Packing
                            </span>
                        </div>
                        <a href="{{ route('penjual.ikans.show', ['ikan' => $ikanAksi, 'return_url' => $returnUrl]) }}" class="mt-3 seller-priority-cta inline-flex min-h-[48px] w-full items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-extrabold tracking-wide text-white sm:w-auto">
                            <span class="inline-flex h-2 w-2 rounded-full bg-white shadow-[0_0_0_3px_rgba(255,255,255,0.22)]" aria-hidden="true"></span>
                            Konfirmasi Packing
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </article>

    <article id="seller-step-pickup" class="bg-white rounded-2xl border border-indigo-100 overflow-hidden scroll-mt-28">
        <div class="px-5 py-4 border-b border-indigo-100 flex items-center justify-between gap-2">
            <div>
                <h2 class="font-black text-slate-900">Penjemputan</h2>
                <p class="text-[11px] text-indigo-700 font-semibold mt-1">Validasi penjemput saat datang</p>
            </div>
            <span class="inline-flex items-center justify-center shrink-0 whitespace-nowrap rounded-full bg-indigo-100 px-3 py-1.5 text-[11px] font-extrabold text-indigo-800">{{ $aksiPenjemputan->count() }} lot</span>
        </div>
        @if($aksiPenjemputan->isEmpty())
            <p class="px-5 py-4 text-sm text-slate-500">Belum ada lot yang perlu validasi penjemputan.</p>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach($aksiPenjemputan as $ikanAksi)
                    @php $trxPenjemputan = $ikanAksi->transaksi; @endphp
                    <li class="px-5 py-4">
                        <p class="text-sm font-black text-slate-900">{{ $ikanAksi->nama_ikan }}</p>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-cyan-100 text-cyan-700">
                                Siap Validasi Penjemput
                            </span>
                        </div>
                        <p class="mt-1 text-[11px] text-slate-600">Cocokkan nama sopir, plat nomor, foto sopir, dan foto kendaraan pada detail lot.</p>
                        <a href="{{ route('penjual.ikans.show', ['ikan' => $ikanAksi, 'return_url' => $returnUrl]) }}" class="mt-3 seller-priority-cta inline-flex min-h-[48px] w-full items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-extrabold tracking-wide text-white sm:w-auto">
                            <span class="inline-flex h-2 w-2 rounded-full bg-white shadow-[0_0_0_3px_rgba(255,255,255,0.22)]" aria-hidden="true"></span>
                            Validasi Penjemput
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </article>

    <article id="seller-step-done" class="bg-white rounded-2xl border border-emerald-100 overflow-hidden scroll-mt-28">
        <div class="px-5 py-4 border-b border-emerald-100 flex items-center justify-between gap-2">
            <div>
                <h2 class="font-black text-slate-900">Selesai</h2>
                <p class="text-[11px] text-emerald-700 font-semibold mt-1">{{ $pipelineSelesaiTotal }} menunggu konfirmasi buyer</p>
            </div>
            <span class="inline-flex items-center justify-center shrink-0 whitespace-nowrap rounded-full bg-emerald-100 px-3 py-1.5 text-[11px] font-extrabold text-emerald-800">{{ $aksiSelesai->count() }} lot</span>
        </div>
        @if($aksiSelesai->isEmpty())
            <p class="px-5 py-4 text-sm text-slate-500">Belum ada lot selesai.</p>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach($aksiSelesai as $ikanAksi)
                    @php
                        $trxSelesai = $ikanAksi->transaksi;
                        $isArrived = (string) $trxSelesai?->pickup_status === 'pickup_arrived';
                    @endphp
                    <li class="px-5 py-4">
                        <p class="text-sm font-black text-slate-900">{{ $ikanAksi->nama_ikan }}</p>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold {{ $isArrived ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                {{ $isArrived ? 'Siap Dikonfirmasi' : 'Belum Siap' }}
                            </span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-700">
                                Menunggu Konfirmasi Buyer
                            </span>
                        </div>
                        <p class="mt-1 text-[11px] text-slate-600">Penjemput datang: {{ $trxSelesai?->pickup_verified_at?->format('d M Y H:i') ?? '-' }}</p>
                        <p class="mt-1 text-[11px] text-slate-600">Konfirmasi buyer: {{ $trxSelesai?->completed_by_buyer_at?->format('d M Y H:i') ?? '-' }}</p>
                        <x-secondary-action-link :href="route('penjual.ikans.show', ['ikan' => $ikanAksi, 'return_url' => $returnUrl])" class="mt-3 w-full rounded-xl px-4 py-3 text-sm sm:w-auto">
                            Lihat Detail
                        </x-secondary-action-link>
                    </li>
                @endforeach
            </ul>
        @endif
    </article>
</section>

@include('components.auction-type-filter', [
    'tipeLelang' => $tipeLelang,
    'allUrl' => route('penjual.ikans.index', $typeFilterBaseParams),
    'naikUrl' => route('penjual.ikans.index', ['tipe_lelang' => 'naik', ...$typeFilterBaseParams]),
    'turunUrl' => route('penjual.ikans.index', ['tipe_lelang' => 'turun', ...$typeFilterBaseParams]),
    'marginClass' => 'mb-8',
])

@if($isAllEmpty)
    <div class="text-center py-20 bg-white rounded-2xl border border-gray-100">
        <h3 class="text-xl font-bold text-gray-700">Belum Ada Lot</h3>
        <p class="mt-2 mb-6 text-gray-400">Belum ada lot untuk filter ini.</p>
        <a href="{{ route('penjual.ikans.create', ['return_url' => $returnUrl]) }}" class="seller-priority-cta inline-flex items-center justify-center gap-2 rounded-xl px-6 py-3 text-sm font-extrabold tracking-wide text-white">
            <span class="inline-flex h-2.5 w-2.5 rounded-full bg-white shadow-[0_0_0_3px_rgba(255,255,255,0.25)]" aria-hidden="true"></span>
            Upload Ikan Sekarang
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M3 10a1 1 0 0 1 1-1h9.59l-2.3-2.29a1 1 0 1 1 1.42-1.42l4 4a1 1 0 0 1 0 1.42l-4 4a1 1 0 0 1-1.42-1.42L13.59 11H4a1 1 0 0 1-1-1Z" clip-rule="evenodd" />
            </svg>
        </a>
    </div>
@else
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        @foreach($sections as $index => $section)
            <section class="bg-white rounded-3xl border {{ $section['containerClass'] }} overflow-hidden {{ $index >= 2 ? 'xl:col-span-2' : '' }}">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3 flex-wrap">
                    <h2 class="text-lg font-black text-gray-900">
                        <span class="sm:hidden">{{ $section['titleShort'] ?? $section['title'] }}</span>
                        <span class="hidden sm:inline">{{ $section['title'] }}</span>
                    </h2>
                    <span class="inline-flex items-center rounded-full {{ $section['badgeClass'] }} font-bold">
                        <span class="sm:hidden text-xs px-2 py-0.5">{{ $section['items']->total() }}</span>
                        <span class="hidden sm:inline text-sm px-3 py-1">{{ $section['items']->total() }} lot</span>
                    </span>
                </div>

                @if($section['items']->isEmpty())
                    <div class="px-5 py-6 text-sm text-gray-400">
                        {{ $section['emptyText'] }}
                    </div>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach($section['items'] as $ikan)
                            @php
                                $canEdit = $ikan->status !== 'aktif' && now()->lt($ikan->waktu_mulai);
                                $canDelete = (int) ($ikan->bids_count ?? 0) === 0;
                                $trx = $ikan->transaksi;
                                $buyNowTarget = $ikan->buyNowTarget();
                                $fulfillmentState = $trx?->fulfillment_state;
                                $hasAdvancedLotInfo = $ikan->isLelangTurun() || filled($ikan->hard_stop_reason);
                            @endphp

                            <li class="px-4 py-6 sm:px-5">
                                <div class="flex items-start justify-between gap-5 flex-wrap">
                                    <div class="space-y-3">
                                        <p class="text-base font-black text-slate-900">{{ $ikan->nama_ikan }}</p>
                                        <p class="text-sm text-slate-600">
                                            {{ $ikan->berat }} kg &bull; {{ $ikan->kondisi === 'beku' ? 'Frozen' : $ikan->kondisi }}
                                            @if($ikan->jenis_kemasan)
                                                &bull; {{ ucfirst($ikan->jenis_kemasan) }}
                                            @endif
                                        </p>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-flex items-center rounded-full px-3 py-1.5 text-[11px] font-extrabold {{ $ikan->isLelangTurun() ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">{{ $ikan->isLelangTurun() ? 'Lelang Turun' : 'Lelang Naik' }}</span>
                                            <span class="inline-flex items-center rounded-full px-3 py-1.5 text-[11px] font-extrabold {{ $ikan->status === 'aktif' ? 'bg-green-100 text-green-700' : ($ikan->status === 'menunggu' ? 'bg-yellow-100 text-yellow-700' : ($ikan->status === 'terbayar' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600')) }}">{{ lotStatusLabel($ikan->status) }}</span>
                                            @if($fulfillmentState)
                                                <span class="inline-flex items-center rounded-full px-3 py-1.5 text-[11px] font-extrabold {{ $trx->buyerProgressBadgeClass() }}">{{ $trx->buyerProgressLabel() }}</span>
                                            @endif
                                            <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1.5 text-[11px] font-extrabold text-slate-700">{{ (int) ($ikan->bids_count ?? 0) }} bid</span>
                                        </div>
                                        @if($hasAdvancedLotInfo)
                                            <details class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-700">
                                                <summary class="cursor-pointer list-none font-bold">Info lanjutan lot</summary>
                                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                                    @if($ikan->isLelangTurun())
                                                        @if($ikan->reserve_price !== null)
                                                            <span class="inline-flex items-center rounded-full bg-cyan-100 px-3 py-1.5 text-[11px] font-extrabold text-cyan-700">Reserve Aktif</span>
                                                        @else
                                                            <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1.5 text-[11px] font-extrabold text-slate-600">Tanpa Reserve</span>
                                                        @endif
                                                    @endif
                                                    @if($ikan->hard_stop_reason)
                                                        <span class="inline-flex items-center rounded-full bg-orange-100 px-3 py-1.5 text-[11px] font-extrabold text-orange-700">{{ str_replace('_', ' ', $ikan->hard_stop_reason) }}</span>
                                                        <span class="inline-flex items-center rounded-full bg-rose-100 px-3 py-1.5 text-[11px] font-extrabold text-rose-700">Hard Stop</span>
                                                    @endif
                                                </div>
                                            </details>
                                        @endif
                                    </div>

                                    <div class="w-full sm:w-auto sm:min-w-[180px] text-left sm:text-right">
                                        <p class="text-xs text-slate-500">Harga Saat Ini</p>
                                        <p class="text-lg font-black text-cyan-700">{{ formatRupiah($ikan->harga_tertinggi) }}</p>
                                        <p class="text-sm text-slate-500 mt-1">Selesai: {{ $ikan->waktu_selesai->format('d M Y H:i') }}</p>
                                        @if($buyNowTarget)
                                            <p class="text-sm font-semibold text-cyan-700 mt-1">Beli Sekarang: {{ formatRupiah($buyNowTarget) }}</p>
                                        @endif
                                        @if($ikan->isLelangTurun())
                                            <p class="text-sm text-slate-500 mt-1">Reserve: {{ $ikan->reserve_price !== null ? formatRupiah($ikan->reserve_price) : 'Tidak diatur' }}</p>
                                        @endif
                                    </div>
                                </div>

                                @if($trx && $trx->status === 'menunggu_bayar' && $trx->bayar_sebelum)
                                    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-900">
                                        <p>Bayar sebelum: <span class="font-bold">{{ $trx->bayar_sebelum->format('d M Y H:i') }}</span></p>
                                        <p class="mt-1">
                                            Sisa waktu bayar:
                                            <span
                                                class="font-bold js-seller-payment-countdown"
                                                data-deadline="{{ $trx->bayar_sebelum->toIso8601String() }}"
                                            >
                                                {{ now()->lte($trx->bayar_sebelum) ? now()->diffForHumans($trx->bayar_sebelum, true) : 'Waktu pembayaran habis' }}
                                            </span>
                                        </p>
                                    </div>
                                @elseif($trx && $trx->status === 'lunas' && $trx->dibayar_pada)
                                    <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm text-emerald-800">
                                        Dibayar pada {{ $trx->dibayar_pada->format('d M Y H:i') }}.
                                        <p class="mt-1 text-xs font-semibold text-emerald-700">Status penjemputan: {{ pickupStatusLabel($trx->pickup_status) }}.</p>
                                    </div>
                                @endif

                                @if($trx && $trx->fulfillment_state)
                                    <details class="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-700">
                                        <summary class="cursor-pointer list-none font-bold">{{ $trx->buyerProgressLabel() }}</summary>
                                        <p class="mt-2">{{ $trx->buyerProgressDescription() }}</p>
                                    </details>
                                @endif

                                <div class="mt-5 grid grid-cols-1 gap-2.5 sm:flex sm:flex-wrap sm:items-center">
                                    <a href="{{ route('penjual.ikans.show', ['ikan' => $ikan, 'return_url' => $returnUrl]) }}" class="seller-priority-cta inline-flex min-h-[48px] w-full items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-extrabold tracking-wide text-white sm:w-auto">
                                        <span class="inline-flex h-2 w-2 rounded-full bg-white shadow-[0_0_0_3px_rgba(255,255,255,0.25)]" aria-hidden="true"></span>
                                        Detail
                                    </a>
                                    <a href="{{ route('penjual.ikans.create', ['duplikat_dari' => $ikan->id, 'return_url' => $returnUrl]) }}" class="inline-flex min-h-[48px] w-full items-center justify-center gap-2 rounded-xl border border-cyan-200 bg-white px-4 py-3 text-sm font-extrabold tracking-wide text-cyan-700 transition hover:bg-cyan-50 sm:w-auto">
                                        <span class="inline-flex h-2 w-2 rounded-full bg-cyan-500 shadow-[0_0_0_3px_rgba(34,211,238,0.22)]" aria-hidden="true"></span>
                                        Upload Ulang
                                    </a>
                                    @if($canEdit)
                                        <a href="{{ route('penjual.ikans.edit', ['ikan' => $ikan, 'return_url' => $returnUrl]) }}" class="seller-priority-cta-warning inline-flex min-h-[48px] w-full items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-extrabold tracking-wide text-white sm:w-auto">
                                            <span class="inline-flex h-2 w-2 rounded-full bg-white shadow-[0_0_0_3px_rgba(255,255,255,0.25)]" aria-hidden="true"></span>
                                            Edit
                                        </a>
                                    @else
                                        <span class="inline-flex min-h-[48px] w-full items-center justify-center rounded-xl bg-slate-100 px-4 py-3 text-sm font-semibold text-slate-400 cursor-not-allowed sm:w-auto" title="Ikan tidak bisa diedit setelah lelang mulai">Edit Terkunci</span>
                                    @endif

                                    @if($canDelete)
                                        <form
                                            action="{{ route('penjual.ikans.destroy', $ikan) }}"
                                            method="POST"
                                            class="w-full sm:w-auto"
                                            data-confirm-title="Hapus ikan?"
                                            data-confirm-message="Lot ini akan dihapus dari daftar Anda. Aksi ini tidak bisa dibatalkan."
                                            data-confirm-confirm-label="Hapus"
                                            data-confirm-variant="danger"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="return_url" value="{{ $returnUrl }}">
                                            <button type="submit" class="seller-priority-cta-danger inline-flex min-h-[48px] w-full items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-extrabold tracking-wide text-white">
                                                <span class="inline-flex h-2 w-2 rounded-full bg-white shadow-[0_0_0_3px_rgba(255,255,255,0.25)]" aria-hidden="true"></span>
                                                Hapus
                                            </button>
                                        </form>
                                    @else
                                        <span class="inline-flex min-h-[48px] w-full items-center justify-center rounded-xl bg-slate-100 px-4 py-3 text-sm font-semibold text-slate-400 cursor-not-allowed sm:w-auto" title="Ikan yang sudah memiliki bid tidak bisa dihapus">Hapus Terkunci</span>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <div class="mt-4">{{ $section['items']->links() }}</div>
                @endif
            </section>
        @endforeach
    </div>
@endif

@endsection

@push('scripts')
<script>
    (function () {
        const countdownNodes = Array.from(document.querySelectorAll('.js-seller-payment-countdown[data-deadline]'));
        if (countdownNodes.length === 0) {
            return;
        }

        const formatRemaining = (ms) => {
            if (ms <= 0) {
                return 'Waktu pembayaran habis';
            }

            const totalSeconds = Math.floor(ms / 1000);
            const hari = Math.floor(totalSeconds / 86400);
            const jam = Math.floor((totalSeconds % 86400) / 3600);
            const menit = Math.floor((totalSeconds % 3600) / 60);
            const detik = totalSeconds % 60;

            const parts = [];
            if (hari > 0) parts.push(`${hari}h`);
            if (jam > 0 || hari > 0) parts.push(`${jam}j`);
            parts.push(`${menit}m`);
            parts.push(`${detik}d`);

            return parts.join(' ');
        };

        const updateCountdowns = () => {
            const now = Date.now();

            countdownNodes.forEach((node) => {
                const deadline = Date.parse(node.dataset.deadline || '');
                if (Number.isNaN(deadline)) {
                    node.textContent = '-';
                    return;
                }

                const remaining = deadline - now;
                node.textContent = formatRemaining(remaining);

                if (remaining <= 0) {
                    node.classList.add('text-red-700');
                }
            });
        };

        updateCountdowns();
        setInterval(updateCountdowns, 1000);
    })();
</script>
@endpush
