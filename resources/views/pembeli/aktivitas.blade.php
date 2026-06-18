@extends('layouts.app')
@section('title', 'Aktivitas Bid Saya')

@section('content')
@php
    $tipeLelang = $tipeLelang ?? 'semua';
    $fokus = $fokus ?? 'semua';
    $returnUrl = request()->fullUrl();
    $buyerStats = $buyerStats ?? [];
    $sections = $sections ?? [];
    $pipelineBayar = $pipelineBayar ?? collect();
    $pipelinePenjemputan = $pipelinePenjemputan ?? collect();
    $pipelineSelesai = $pipelineSelesai ?? collect();

    $totalLotDiikuti = (int) ($buyerStats['total_lot_diikuti'] ?? 0);
    $memimpinAktif = (int) ($buyerStats['memimpin_aktif'] ?? 0);
    $menungguBayarTotal = (int) ($buyerStats['menunggu_bayar'] ?? 0);
    $sudahLunasTotal = (int) ($buyerStats['sudah_lunas'] ?? 0);
    $perluKonfirmasiTerima = (int) ($buyerStats['perlu_konfirmasi_terima'] ?? 0);
    $nilaiBelumBayar = (float) ($buyerStats['nilai_belum_bayar'] ?? 0);

    $focusLabels = [
        'semua' => 'Semua Aktivitas',
        'lot_diikuti' => 'Lot Diikuti',
        'memimpin_aktif' => 'Memimpin Aktif',
        'sudah_lunas' => 'Sudah Lunas',
        'tagihan_berjalan' => 'Tagihan Berjalan',
    ];
    $fokusLabel = $focusLabels[$fokus] ?? $focusLabels['semua'];

    $isAllEmpty = collect($sections)->every(fn ($section) => ($section['items'] ?? collect())->isEmpty());
    $singleSection = count($sections) === 1;

    $baseEtalaseParams = $tipeLelang !== 'semua' ? ['tipe_lelang' => $tipeLelang] : [];
    $baseEtalaseUrl = route('pembeli.aktivitas', $baseEtalaseParams);

    $buildFokusUrl = function (string $mode) use ($baseEtalaseParams): string {
        return route('pembeli.aktivitas', [...$baseEtalaseParams, 'fokus' => $mode]);
    };

    $focusCardUrls = [
        'lot_diikuti' => $fokus === 'lot_diikuti' ? $baseEtalaseUrl : $buildFokusUrl('lot_diikuti'),
        'memimpin_aktif' => $fokus === 'memimpin_aktif' ? $baseEtalaseUrl : $buildFokusUrl('memimpin_aktif'),
        'sudah_lunas' => $fokus === 'sudah_lunas' ? $baseEtalaseUrl : $buildFokusUrl('sudah_lunas'),
        'tagihan_berjalan' => $fokus === 'tagihan_berjalan' ? $baseEtalaseUrl : $buildFokusUrl('tagihan_berjalan'),
    ];

    $typeFilterBaseParams = $fokus !== 'semua' ? ['fokus' => $fokus] : [];

    $buyerNextAction = null;
    $nextPaymentTrx = $pipelineBayar->first(fn ($trx) => (string) $trx->status === 'menunggu_bayar' && $trx->ikan);
    $nextReadyPickupTrx = $pipelineBayar->first(fn ($trx) => $trx->ikan && $trx->status === 'lunas' && $trx->packed_at && ! $trx->buyer_pickup_submitted_at);
    $nextWaitingPackingTrx = $pipelineBayar->first(fn ($trx) => $trx->ikan && $trx->status === 'lunas' && ! $trx->packed_at);
    $nextPickupTrx = $pipelinePenjemputan->first(fn ($trx) => $trx->ikan);
    $nextReviewTrx = $pipelineSelesai->first(fn ($trx) => $trx->ikan);

    if ($nextPaymentTrx) {
        $buyerNextAction = [
            'badge' => 'Prioritas Pembayaran',
            'title' => 'Selesaikan pembayaran lot ' . $nextPaymentTrx->ikan->nama_ikan,
            'description' => $nextPaymentTrx->bayar_sebelum
                ? 'Bayar sebelum ' . $nextPaymentTrx->bayar_sebelum->format('d M Y H:i') . ' agar kemenangan lelang tetap aman.'
                : 'Invoice sudah siap dibuat. Selesaikan pembayaran agar proses penjemputan bisa dilanjutkan.',
            'url' => route('pembayaran.show', ['transaksi' => $nextPaymentTrx, 'return_url' => $returnUrl]),
            'cta' => 'Bayar Sekarang',
            'class' => 'buyer-priority-cta-pay',
        ];
    } elseif ($nextReadyPickupTrx) {
        $buyerNextAction = [
            'badge' => 'Siap Dijemput',
            'title' => 'Isi data penjemput untuk ' . $nextReadyPickupTrx->ikan->nama_ikan,
            'description' => 'Penjual sudah mengonfirmasi packing. Masukkan nama sopir, plat nomor, foto sopir, dan foto kendaraan.',
            'url' => route('pembeli.aktivitas.detail', ['ikan' => $nextReadyPickupTrx->ikan, 'return_url' => $returnUrl]),
            'cta' => 'Isi Data Penjemput',
            'class' => 'buyer-priority-cta-confirm',
        ];
    } elseif ($nextPickupTrx) {
        $isDriverSubmitted = $nextPickupTrx->buyer_pickup_submitted_at !== null;
        $buyerNextAction = [
            'badge' => 'Penjemputan',
            'title' => ($isDriverSubmitted ? 'Pantau penjemputan ' : 'Lengkapi data penjemput ') . $nextPickupTrx->ikan->nama_ikan,
            'description' => $isDriverSubmitted
                ? 'Data penjemput sudah masuk. Pantau validasi sopir dan kendaraan dari penjual.'
                : 'Lengkapi data penjemput agar penjual bisa mencocokkan sopir dan kendaraan saat datang.',
            'url' => route('pembeli.aktivitas.detail', ['ikan' => $nextPickupTrx->ikan, 'return_url' => $returnUrl]),
            'cta' => 'Lihat Penjemputan',
            'class' => 'buyer-priority-cta-confirm',
        ];
    } elseif ($nextReviewTrx) {
        $buyerNextAction = [
            'badge' => 'Konfirmasi Selesai',
            'title' => 'Review dan konfirmasi ' . $nextReviewTrx->ikan->nama_ikan,
            'description' => 'Penjemput sudah divalidasi. Konfirmasi selesai setelah barang diterima dan dicek.',
            'url' => route('pembeli.aktivitas.penilaian', ['ikan' => $nextReviewTrx->ikan, 'return_url' => $returnUrl]),
            'cta' => 'Beri Penilaian',
            'class' => 'buyer-priority-cta-confirm',
        ];
    } elseif ($nextWaitingPackingTrx) {
        $buyerNextAction = [
            'badge' => 'Menunggu Packing',
            'title' => 'Pantau status packing ' . $nextWaitingPackingTrx->ikan->nama_ikan,
            'description' => 'Pembayaran sudah masuk. Data penjemput baru bisa diisi setelah penjual mengonfirmasi packing.',
            'url' => route('pembeli.aktivitas.detail', ['ikan' => $nextWaitingPackingTrx->ikan, 'return_url' => $returnUrl]),
            'cta' => 'Lihat Status Packing',
            'class' => 'buyer-priority-cta-confirm',
        ];
    } elseif ($memimpinAktif > 0) {
        $buyerNextAction = [
            'badge' => 'Sedang Memimpin',
            'title' => 'Pantau lot yang sedang Anda pimpin',
            'description' => 'Pastikan posisi bid tetap aman sampai waktu lelang selesai.',
            'url' => $focusCardUrls['memimpin_aktif'],
            'cta' => 'Lihat Lot Dipimpin',
            'class' => 'buyer-priority-cta-confirm',
        ];
    }
@endphp

<style>
    .buyer-hero {
        background:
            radial-gradient(circle at 10% 10%, rgba(59, 130, 246, 0.14), transparent 32%),
            radial-gradient(circle at 85% 95%, rgba(34, 197, 94, 0.12), transparent 28%),
            linear-gradient(145deg, #f8fcff 0%, #f2f7ff 48%, #f9fcff 100%);
    }

    .buyer-metric {
        background: rgba(255, 255, 255, 0.9);
        border: 1px solid rgba(191, 219, 254, 0.7);
        box-shadow: 0 12px 24px -20px rgba(15, 23, 42, 0.55);
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .buyer-metric-link:hover {
        transform: translateY(-2px);
        border-color: rgba(59, 130, 246, 0.45);
        box-shadow: 0 18px 30px -20px rgba(15, 23, 42, 0.58);
    }

    .buyer-metric-active {
        border-color: rgba(2, 132, 199, 0.75);
        box-shadow:
            0 0 0 3px rgba(14, 165, 233, 0.18),
            0 16px 28px -20px rgba(2, 132, 199, 0.72);
    }

    .buyer-priority-cta-pay {
        background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%);
        box-shadow: 0 14px 28px -16px rgba(217, 119, 6, 0.88);
        transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
    }

    .buyer-priority-cta-pay:hover {
        transform: translateY(-1px);
        box-shadow: 0 18px 32px -14px rgba(245, 158, 11, 0.9);
        filter: brightness(1.03);
    }

    .buyer-priority-cta-pay:focus-visible {
        outline: none;
        box-shadow:
            0 0 0 3px rgba(251, 191, 36, 0.28),
            0 18px 32px -14px rgba(245, 158, 11, 0.9);
    }

    .buyer-priority-cta-confirm {
        background: linear-gradient(135deg, #0891b2 0%, #2563eb 100%);
        box-shadow: 0 14px 28px -16px rgba(37, 99, 235, 0.85);
        transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
    }

    .buyer-priority-cta-confirm:hover {
        transform: translateY(-1px);
        box-shadow: 0 18px 32px -14px rgba(8, 145, 178, 0.88);
        filter: brightness(1.02);
    }

    .buyer-priority-cta-confirm:focus-visible {
        outline: none;
        box-shadow:
            0 0 0 3px rgba(34, 211, 238, 0.25),
            0 18px 32px -14px rgba(8, 145, 178, 0.88);
    }

    @media (prefers-reduced-motion: reduce) {
        .buyer-metric,
        .buyer-priority-cta-pay,
        .buyer-priority-cta-confirm {
            transition: none;
        }
    }

    .buyer-chip-row {
        display: flex;
        gap: 0.5rem;
        overflow-x: auto;
        padding-bottom: 0.25rem;
        scrollbar-width: none;
    }

    .buyer-chip-row::-webkit-scrollbar {
        display: none;
    }

    .buyer-chip-row > * {
        flex: 0 0 auto;
        white-space: nowrap;
    }
</style>

<section class="buyer-hero rounded-3xl border border-blue-100/70 px-5 py-6 sm:px-8 sm:py-9 mb-8">
    <p class="hidden sm:inline-flex items-center px-3 py-1 rounded-full text-xs font-extrabold tracking-[0.14em] uppercase text-blue-700 bg-blue-100/70 border border-blue-200/70">
        Aktivitas Pembeli
    </p>
    <h1 class="mt-1 sm:mt-3 text-2xl sm:text-4xl font-black tracking-tight text-slate-900">Aktivitas Bid Saya</h1>
    <p class="mt-2 text-sm sm:text-base text-slate-600 max-w-2xl">Lihat tagihan, lot diikuti, dan penjemputan.</p>

    <div class="buyer-chip-row mt-4">
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">{{ $memimpinAktif }} memimpin</span>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800">{{ $menungguBayarTotal }} menunggu bayar</span>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-cyan-100 text-cyan-800">{{ $perluKonfirmasiTerima }} perlu konfirmasi</span>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <a href="{{ $focusCardUrls['lot_diikuti'] }}" class="buyer-metric buyer-metric-link rounded-2xl p-4 block {{ $fokus === 'lot_diikuti' ? 'buyer-metric-active' : '' }}">
            <p class="text-xs uppercase tracking-wide text-slate-500 font-bold">Lot Diikuti</p>
            <p class="mt-1 text-2xl font-black text-slate-900">{{ number_format($totalLotDiikuti) }}</p>
            <p class="mt-1 text-xs font-semibold text-slate-500">{{ $fokus === 'lot_diikuti' ? 'Fokus aktif. Tap untuk reset.' : 'Aktif dan selesai.' }}</p>
        </a>
        <a href="{{ $focusCardUrls['memimpin_aktif'] }}" class="buyer-metric buyer-metric-link rounded-2xl p-4 block {{ $fokus === 'memimpin_aktif' ? 'buyer-metric-active' : '' }}">
            <p class="text-xs uppercase tracking-wide text-slate-500 font-bold">Memimpin Aktif</p>
            <p class="mt-1 text-2xl font-black text-emerald-700">{{ number_format($memimpinAktif) }}</p>
            <p class="mt-1 text-xs font-semibold text-slate-500">{{ $fokus === 'memimpin_aktif' ? 'Fokus aktif. Tap untuk reset.' : 'Lot unggulan Anda.' }}</p>
        </a>
        <a href="{{ $focusCardUrls['sudah_lunas'] }}" class="buyer-metric buyer-metric-link rounded-2xl p-4 block {{ $fokus === 'sudah_lunas' ? 'buyer-metric-active' : '' }}">
            <p class="text-xs uppercase tracking-wide text-slate-500 font-bold">Sudah Lunas</p>
            <p class="mt-1 text-2xl font-black text-slate-900">{{ number_format($sudahLunasTotal) }}</p>
            <p class="mt-1 text-xs font-semibold text-slate-500">{{ $fokus === 'sudah_lunas' ? 'Fokus aktif. Tap untuk reset.' : 'Packing, jemput, selesai.' }}</p>
        </a>
        <a href="{{ $focusCardUrls['tagihan_berjalan'] }}" class="buyer-metric buyer-metric-link rounded-2xl p-4 block {{ $fokus === 'tagihan_berjalan' ? 'buyer-metric-active' : '' }}">
            <p class="text-xs uppercase tracking-wide text-slate-500 font-bold">Tagihan Berjalan</p>
            <p class="mt-1 text-xl font-black text-amber-700 leading-tight">{{ formatRupiah($nilaiBelumBayar) }}</p>
            <p class="mt-1 text-xs font-semibold text-slate-500">{{ $fokus === 'tagihan_berjalan' ? 'Fokus aktif. Tap untuk reset.' : 'Invoice prioritas.' }}</p>
            </a>
        </div>

    @if($fokus !== 'semua')
        <div class="mt-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-2xl border border-blue-100/90 bg-white/90 px-4 py-3">
            <p class="text-sm text-slate-700">
                Mode fokus aktif:
                <span class="font-extrabold text-slate-900">{{ $fokusLabel }}</span>
            </p>
            <a href="{{ $baseEtalaseUrl }}" class="inline-flex items-center justify-center rounded-xl border border-blue-200 bg-blue-600 hover:bg-blue-700 px-4 py-2 text-xs font-extrabold tracking-wide text-white transition">
                Tampilkan semua
            </a>
        </div>
    @endif
</section>

@if($buyerNextAction)
    <section class="mb-8 rounded-3xl border border-amber-100 bg-white p-5 shadow-[0_16px_28px_-24px_rgba(15,23,42,0.55)]">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-extrabold text-amber-800">
                    {{ $buyerNextAction['badge'] }}
                </span>
                <h2 class="mt-3 text-xl font-black text-slate-900">{{ $buyerNextAction['title'] }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ $buyerNextAction['description'] }}</p>
            </div>
            <a href="{{ $buyerNextAction['url'] }}" class="{{ $buyerNextAction['class'] }} inline-flex w-full items-center justify-center rounded-xl px-5 py-3 text-sm font-extrabold tracking-wide text-white sm:w-auto">
                {{ $buyerNextAction['cta'] }}
            </a>
        </div>
    </section>
@endif

<section class="mb-8 grid grid-cols-1 lg:grid-cols-3 gap-4">
    <article id="buyer-step-bayar" class="bg-white rounded-2xl border border-amber-100 overflow-hidden scroll-mt-28">
        <div class="px-5 py-4 border-b border-amber-100 flex items-center justify-between gap-2">
            <h2 class="font-black text-slate-900">1. Bayar</h2>
            <span class="text-xs font-bold px-2 py-1 rounded-full bg-amber-100 text-amber-800">{{ $pipelineBayar->count() }} trx</span>
        </div>
        @if($pipelineBayar->isEmpty())
            <p class="px-5 py-4 text-sm text-slate-500">Belum ada transaksi pada tahap bayar.</p>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach($pipelineBayar->take(5) as $trx)
                    @php
                        $ikanTrx = $trx->ikan;
                        $isPaid = $trx->status === 'lunas';
                        $isPacked = $trx->packed_at !== null;
                    @endphp
                    <li class="px-5 py-4">
                        <p class="text-sm font-black text-slate-900">{{ $ikanTrx?->nama_ikan ?? '-' }}</p>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold {{ $isPaid ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                {{ $isPaid ? 'Sudah Dibayar' : 'Menunggu Bayar' }}
                            </span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold {{ $isPacked ? 'bg-cyan-100 text-cyan-700' : 'bg-slate-100 text-slate-600' }}">
                                {{ $isPacked ? 'Sudah Packing' : 'Belum Packing' }}
                            </span>
                        </div>
                        @if($trx->packed_at)
                            <p class="mt-2 text-[11px] text-slate-600">Packing: {{ $trx->packed_at->format('d M Y H:i') }}</p>
                        @endif
                        <div class="mt-3">
                            @if(! $isPaid)
                                <a href="{{ route('pembayaran.show', ['transaksi' => $trx, 'return_url' => $returnUrl]) }}" class="buyer-priority-cta-pay inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-[11px] font-extrabold tracking-wide text-white">
                                    Bayar Sekarang
                                </a>
                            @elseif($ikanTrx)
                                <x-secondary-action-link :href="route('pembeli.aktivitas.detail', ['ikan' => $ikanTrx, 'return_url' => $returnUrl])" class="rounded-lg px-3 py-1.5 text-[11px]">
                                    Detail Packing
                                </x-secondary-action-link>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </article>

    <article id="buyer-step-pickup" class="bg-white rounded-2xl border border-cyan-100 overflow-hidden scroll-mt-28">
        <div class="px-5 py-4 border-b border-cyan-100 flex items-center justify-between gap-2">
            <h2 class="font-black text-slate-900">2. Penjemputan</h2>
            <span class="text-xs font-bold px-2 py-1 rounded-full bg-cyan-100 text-cyan-800">{{ $pipelinePenjemputan->count() }} trx</span>
        </div>
        @if($pipelinePenjemputan->isEmpty())
            <p class="px-5 py-4 text-sm text-slate-500">Belum ada transaksi penjemputan.</p>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach($pipelinePenjemputan->take(5) as $trx)
                    @php $ikanTrx = $trx->ikan; @endphp
                    <li class="px-5 py-4">
                        <p class="text-sm font-black text-slate-900">{{ $ikanTrx?->nama_ikan ?? '-' }}</p>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-cyan-100 text-cyan-700">Penjemputan Berjalan</span>
                            @if($trx->buyer_pickup_plate_number)
                                <span class="inline-flex max-w-full items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 text-slate-700 break-all">Plat: {{ $trx->buyer_pickup_plate_number }}</span>
                            @endif
                        </div>
                        <p class="mt-2 text-[11px] text-slate-600">
                            Sopir: {{ $trx->buyer_pickup_name ?: '-' }} - Status: {{ pickupStatusLabel($trx->pickup_status) }}
                        </p>
                        @if($trx->buyer_confirm_deadline_at)
                            <p class="mt-1 text-[11px] text-slate-600">
                                Deadline konfirmasi: {{ $trx->buyer_confirm_deadline_at->format('d M Y H:i') }} - {{ humanDeadlineLabel($trx->buyer_confirm_deadline_at) }}
                            </p>
                        @endif
                        @if($ikanTrx)
                            <x-secondary-action-link :href="route('pembeli.aktivitas.detail', ['ikan' => $ikanTrx, 'return_url' => $returnUrl])" class="mt-3 rounded-lg px-3 py-1.5 text-[11px]">
                                Detail Penjemputan
                            </x-secondary-action-link>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </article>

    <article id="buyer-step-done" class="bg-white rounded-2xl border border-emerald-100 overflow-hidden scroll-mt-28">
        <div class="px-5 py-4 border-b border-emerald-100 flex items-center justify-between gap-2">
            <h2 class="font-black text-slate-900">3. Selesai</h2>
            <span class="text-xs font-bold px-2 py-1 rounded-full bg-emerald-100 text-emerald-800">{{ $pipelineSelesai->count() }} trx</span>
        </div>
        @if($pipelineSelesai->isEmpty())
            <p class="px-5 py-4 text-sm text-slate-500">Belum ada transaksi yang siap diselesaikan.</p>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach($pipelineSelesai->take(5) as $trx)
                    @php
                        $ikanTrx = $trx->ikan;
                        $isBuyerConfirmed = $trx->completed_by_buyer_at !== null || $trx->fulfillment_state === 'SELESAI';
                        $isArrived = $trx->pickup_status === 'pickup_arrived' || $trx->pickup_verified_at !== null || $isBuyerConfirmed;
                    @endphp
                    <li class="px-5 py-4">
                        <p class="text-sm font-black text-slate-900">{{ $ikanTrx?->nama_ikan ?? '-' }}</p>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold {{ $isArrived ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                {{ $isArrived ? 'Siap Dikonfirmasi' : 'Belum Siap' }}
                            </span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-700">
                                Menunggu Konfirmasi Buyer
                            </span>
                        </div>
                        <p class="mt-2 text-[11px] text-slate-600">
                            Penjemput datang: {{ $trx->pickup_verified_at?->format('d M Y H:i') ?? '-' }}
                        </p>
                        <p class="mt-1 text-[11px] text-slate-600">
                            Konfirmasi buyer: {{ $trx->completed_by_buyer_at?->format('d M Y H:i') ?? '-' }}
                        </p>
                        @if($ikanTrx)
                            <x-secondary-action-link :href="route('pembeli.aktivitas.penilaian', ['ikan' => $ikanTrx, 'return_url' => $returnUrl])" class="mt-3 rounded-lg px-3 py-1.5 text-[11px]">
                                Beri Penilaian
                            </x-secondary-action-link>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </article>
</section>

@include('components.auction-type-filter', [
    'tipeLelang' => $tipeLelang,
    'allUrl' => route('pembeli.aktivitas', $typeFilterBaseParams),
    'naikUrl' => route('pembeli.aktivitas', [...$typeFilterBaseParams, 'tipe_lelang' => 'naik']),
    'turunUrl' => route('pembeli.aktivitas', [...$typeFilterBaseParams, 'tipe_lelang' => 'turun']),
    'marginClass' => 'mb-8',
])

@if($isAllEmpty)
    <div class="text-center py-24 bg-white rounded-2xl border border-gray-100">
        <h3 class="text-xl font-bold text-gray-700">Belum Ada Aktivitas</h3>
        <p class="mt-2 mb-6 text-gray-400">Belum ada lot untuk filter ini.</p>
        <a href="{{ route('ikans.index') }}" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-3 rounded-xl transition">
            Mulai Jelajahi Lelang
        </a>
    </div>
@else
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        @foreach($sections as $index => $section)
            @php
                $showExpiredBadge = (bool) ($section['showExpiredBadge'] ?? false);
            @endphp
            <section class="bg-white rounded-3xl border {{ $section['containerClass'] }} overflow-hidden {{ $singleSection || $index >= 2 ? 'xl:col-span-2' : '' }}">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                    <h2 class="text-lg font-black text-gray-900">
                        <span class="sm:hidden">{{ $section['titleShort'] ?? $section['title'] }}</span>
                        <span class="hidden sm:inline">{{ $section['title'] }}</span>
                    </h2>
                    <span class="inline-flex items-center rounded-full {{ $section['badgeClass'] }} font-bold">
                        <span class="sm:hidden text-xs px-2 py-0.5">{{ $section['items']->count() }}</span>
                        <span class="hidden sm:inline text-sm px-3 py-1">{{ $section['items']->count() }} lot</span>
                    </span>
                </div>

                @if($section['items']->isEmpty())
                    <p class="px-5 py-6 text-sm text-gray-400">{{ $section['emptyText'] }}</p>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach($section['items'] as $bid)
                            @php
                                $ikan = $bid->ikan;
                                $isTurun = $ikan->isLelangTurun();
                                $modeLabel = $isTurun ? 'Lelang Turun' : 'Lelang Naik';
                                $modeClass = $isTurun ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700';

                                $bidSaya = (float) ($ikan->my_best_bid ?? 0);
                                $bidSaatIni = (float) ($ikan->best_bid_amount ?? $ikan->harga_tertinggi);
                                $statusBid = (int) ($ikan->best_bidder_id ?? 0) === (int) auth()->id()
                                    ? 'Memimpin'
                                    : 'Belum memimpin';
                                $trx = $ikan->transaksi;
                                $batasBayar = $trx?->bayar_sebelum;
                                $statusLot = strtoupper($ikan->status === 'terbayar' ? 'selesai' : $ikan->status);
                                $fulfillmentState = $trx?->fulfillment_state;
                                $fulfillmentLabel = $trx?->buyerProgressLabel() ?? fulfillmentStateLabel($fulfillmentState);
                                $fulfillmentBadgeClass = $trx?->buyerProgressBadgeClass() ?? fulfillmentStateBadgeClass($fulfillmentState);

                                $shippingStatusLabel = null;
                                $shippingStatusClass = 'bg-slate-100 text-slate-700';
                                if ($trx && $trx->isLunas()) {
                                    $shippingStatusLabel = $trx->buyerProgressLabel();
                                    $shippingStatusClass = $trx->buyerProgressBadgeClass();
                                }

                                $isLewatBayar = $trx
                                    && ($trx->status === 'kadaluarsa'
                                        || ($trx->status === 'menunggu_bayar'
                                            && $trx->bayar_sebelum
                                            && now()->gt($trx->bayar_sebelum)));
                            @endphp

                            <li class="px-4 py-5 sm:px-5">
                                <div class="flex items-start justify-between gap-3 flex-wrap">
                                    <div class="space-y-2">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <p class="font-black text-base text-gray-900">{{ $ikan->nama_ikan }}</p>
                                            <span class="text-xs font-bold px-2.5 py-1 rounded-full {{ $modeClass }}">{{ $modeLabel }}</span>
                                            <span class="text-xs font-bold px-2.5 py-1 rounded-full {{ $statusBid === 'Memimpin' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $statusBid }}</span>
                                        </div>
                                        <p class="text-sm text-gray-600">Penjual: {{ $ikan->user->name }} &bull; Update: {{ $bid->created_at->diffForHumans() }}</p>
                                        <p class="text-sm font-bold text-cyan-700">Harga saat ini: {{ formatRupiah($bidSaatIni) }}</p>
                                    </div>

                                    <a href="{{ route('pembeli.aktivitas.detail', ['ikan' => $ikan, 'return_url' => $returnUrl]) }}" class="inline-flex min-h-[42px] w-full sm:w-auto items-center justify-center px-3.5 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold transition">
                                        Detail Aktivitas
                                    </a>
                                </div>

                                <details class="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-xs font-bold text-slate-700">
                                        <span>Ringkasan transaksi</span>
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-bold {{ $fulfillmentBadgeClass }}">{{ $fulfillmentLabel }}</span>
                                    </summary>
                                    <div class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-3">
                                        <div class="rounded-xl border border-slate-100 bg-white px-3 py-2">
                                            <p class="text-xs text-slate-500">Bid terbaik saya</p>
                                            <p class="mt-1 text-sm font-bold text-slate-900">{{ formatRupiah($bidSaya) }}</p>
                                        </div>
                                        <div class="rounded-xl border border-slate-100 bg-white px-3 py-2">
                                            <p class="text-xs text-slate-500">Harga saat ini</p>
                                            <p class="mt-1 text-sm font-bold text-slate-900">{{ formatRupiah($bidSaatIni) }}</p>
                                        </div>
                                        <div class="rounded-xl border border-slate-100 bg-white px-3 py-2">
                                            <p class="text-xs text-slate-500">Status lot</p>
                                            <p class="mt-1 text-sm font-bold uppercase text-slate-900">{{ $statusLot }}</p>
                                        </div>
                                        <div class="rounded-xl border border-slate-100 bg-white px-3 py-2">
                                            <p class="text-xs text-slate-500">Status transaksi</p>
                                            <p class="mt-1 inline-flex px-2 py-0.5 rounded text-xs font-bold {{ $fulfillmentBadgeClass }}">{{ $fulfillmentLabel }}</p>
                                        </div>
                                    </div>
                                </details>

                                @if($shippingStatusLabel)
                                    <div class="mt-3 inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700">
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-bold {{ $shippingStatusClass }}">{{ $shippingStatusLabel }}</span>
                                        <span>Status penjemputan lot lunas</span>
                                    </div>
                                @endif

                                @if($showExpiredBadge && $isLewatBayar)
                                    <div class="mt-3 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800">
                                        <p class="font-bold">Tagihan melewati tenggat pembayaran.</p>
                                        @if($batasBayar)
                                            <p class="mt-1">Batas bayar: {{ $batasBayar->format('d M Y H:i') }}</p>
                                        @endif
                                    </div>
                                @endif

                                @if($section['showPayAction'] && $trx && $trx->status === 'menunggu_bayar')
                                    <div class="mt-4 text-sm rounded-xl border border-yellow-200 bg-yellow-50 px-3 py-3 text-yellow-900">
                                        <p>Batas bayar: <span class="font-bold">{{ $batasBayar ? $batasBayar->format('d M Y H:i') : '-' }}</span></p>
                                        @if($batasBayar)
                                            <p class="mt-1">
                                                Sisa waktu:
                                                <span
                                                    class="font-bold js-payment-countdown"
                                                    data-deadline="{{ $batasBayar->toIso8601String() }}"
                                                >
                                                    {{ now()->lte($batasBayar)
                                                        ? now()->diffForHumans($batasBayar, true)
                                                        : 'Waktu pembayaran sudah habis' }}
                                                </span>
                                            </p>
                                        @endif
                                    </div>

                                    <div class="mt-3 flex items-center gap-2">
                                        <a href="{{ route('pembayaran.show', ['transaksi' => $trx, 'return_url' => $returnUrl]) }}" class="buyer-priority-cta-pay inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-extrabold tracking-wide text-white">
                                            <span class="inline-flex h-2.5 w-2.5 rounded-full bg-white shadow-[0_0_0_3px_rgba(255,255,255,0.25)]" aria-hidden="true"></span>
                                            Lanjut Bayar
                                        </a>
                                    </div>
                                @endif

                                @if($trx && $trx->fulfillment_state)
                                    <details class="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-700">
                                        <summary class="cursor-pointer list-none text-xs font-bold text-slate-700">Detail fulfillment</summary>
                                        <p class="mt-3 font-bold">Ringkasan: {{ $trx->buyerProgressLabel() }}</p>
                                        <p class="mt-1 text-xs text-slate-600">{{ $trx->buyerProgressDescription() }}</p>
                                        @if($trx->fulfillment_state)
                                            <p class="mt-2 text-xs text-slate-500">State internal: <span class="font-semibold">{{ fulfillmentStateLabel($trx->fulfillment_state) }}</span></p>
                                        @endif
                                        @if($trx->buyer_confirm_deadline_at)
                                            <p class="mt-1 text-xs text-slate-600">Deadline konfirmasi terima: <span class="font-semibold">{{ $trx->buyer_confirm_deadline_at->format('d M Y H:i') }}</span> - {{ humanDeadlineLabel($trx->buyer_confirm_deadline_at) }}</p>
                                        @endif
                                    </details>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endforeach
    </div>
@endif
@endsection

@push('scripts')
<script>
    (function () {
        const countdownNodes = Array.from(document.querySelectorAll('.js-payment-countdown[data-deadline]'));
        if (countdownNodes.length === 0) {
            return;
        }

        const formatRemaining = (ms) => {
            if (ms <= 0) {
                return 'Waktu pembayaran sudah habis';
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
