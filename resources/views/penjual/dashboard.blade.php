@extends('layouts.app')
@section('title', 'Dashboard Penjual')

@section('content')
@php
    $sellerDashboardStats = $sellerDashboardStats ?? [];
    $totalSelesai = (int) ($sellerDashboardStats['total_selesai'] ?? 0);
    $nilaiSelesai = (float) ($sellerDashboardStats['nilai_selesai'] ?? 0);
    $ratingAverage = array_key_exists('rating_average', $sellerDashboardStats) && $sellerDashboardStats['rating_average'] !== null
        ? (float) $sellerDashboardStats['rating_average']
        : null;
    $ratingCount = (int) ($sellerDashboardStats['rating_count'] ?? 0);
    $sellerSettlementStats = $sellerSettlementStats ?? [];
    $recentSettlements = $recentSettlements ?? collect();
    $settlementPending = (int) ($sellerSettlementStats['pending'] ?? 0);
    $settlementHeld = (int) ($sellerSettlementStats['held'] ?? 0);
    $settlementReady = (int) ($sellerSettlementStats['ready_to_pay'] ?? 0);
    $settlementPaid = (int) ($sellerSettlementStats['paid'] ?? 0);
    $settlementOutstandingAmount = (float) ($sellerSettlementStats['outstanding_amount'] ?? 0);
    $settlementPaidAmount = (float) ($sellerSettlementStats['paid_amount'] ?? 0);
    $returnUrl = request()->fullUrl();
@endphp

<style>
    .seller-dashboard-hero {
        background:
            radial-gradient(circle at 12% 8%, rgba(14, 165, 233, 0.15), transparent 34%),
            radial-gradient(circle at 88% 0%, rgba(16, 185, 129, 0.14), transparent 36%),
            linear-gradient(145deg, #f8fcff 0%, #eff8ff 52%, #f8fcff 100%);
    }

    .seller-dashboard-surface {
        border: 1px solid rgba(226, 232, 240, 0.95);
        box-shadow: 0 16px 24px -22px rgba(15, 23, 42, 0.6);
    }

    .seller-dashboard-tile {
        background: rgba(248, 250, 252, 0.9);
        border: 1px solid rgba(226, 232, 240, 0.9);
    }

    .seller-dashboard-photo {
        width: 100%;
        max-height: 20rem;
        object-fit: cover;
        object-position: center;
    }

    @media (max-width: 639px) {
        .seller-dashboard-surface {
            border-radius: 1.5rem;
        }

        .seller-dashboard-photo {
            max-height: 15rem;
        }
    }
</style>

<section class="seller-dashboard-hero rounded-3xl border border-cyan-100/70 px-6 py-7 sm:px-8 sm:py-9 mb-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="inline-flex items-center px-3 py-1 rounded-full text-xs font-extrabold tracking-[0.14em] uppercase text-cyan-700 bg-cyan-100/70 border border-cyan-200/70">
                Dashboard Penjual
            </p>
            <h1 class="mt-3 text-3xl sm:text-4xl font-black tracking-tight text-slate-900">{{ $sellerProfile?->store_name ?: 'Profil Toko' }}</h1>
            <p class="mt-2 text-slate-600 max-w-2xl">Lihat profil toko, penjualan selesai, dan settlement dana.</p>
        </div>
        <div class="hidden sm:flex flex-wrap gap-3">
            <a href="{{ route('penjual.ikans.index') }}" class="inline-flex min-h-[48px] items-center justify-center rounded-xl bg-slate-900 px-5 py-3 text-sm font-extrabold tracking-wide text-white transition hover:bg-slate-800">
                Aktivitas Lot
            </a>
            <a href="{{ route('penjual.ikans.create', ['return_url' => $returnUrl]) }}" class="inline-flex min-h-[48px] items-center justify-center rounded-xl bg-cyan-700 px-5 py-3 text-sm font-extrabold tracking-wide text-white transition hover:bg-cyan-800">
                Upload Ikan
            </a>
        </div>
    </div>

    {{-- Tombol Mobile --}}
    <div class="mt-5 grid grid-cols-1 gap-3 sm:hidden">
        <a href="{{ route('penjual.ikans.index') }}" class="inline-flex min-h-[48px] items-center justify-center rounded-xl bg-slate-900 px-4 py-3 text-sm font-extrabold tracking-wide text-white transition hover:bg-slate-800">
            Aktivitas
        </a>
        <a href="{{ route('penjual.ikans.create', ['return_url' => $returnUrl]) }}" class="inline-flex min-h-[48px] items-center justify-center rounded-xl bg-cyan-700 px-4 py-3 text-sm font-extrabold tracking-wide text-white transition hover:bg-cyan-800">
            Upload
        </a>
    </div>

    {{-- GRID STATISTIK - Diperbaiki agar stacking di mobile (1 kolom) dan berjejer di desktop (3 kolom) --}}
    <div class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="seller-dashboard-tile rounded-2xl p-5">
            <p class="text-[10px] uppercase tracking-widest text-slate-500 font-extrabold">Penjualan Selesai</p>
            <p class="mt-2 text-2xl font-black text-slate-900">{{ number_format($totalSelesai) }}</p>
        </div>
        <div class="seller-dashboard-tile rounded-2xl p-5">
            <p class="text-[10px] uppercase tracking-widest text-slate-500 font-extrabold">Nilai Penjualan Selesai</p>
            <p class="mt-2 text-2xl font-black text-emerald-700">{{ formatRupiah($nilaiSelesai) }}</p>
        </div>
        <div class="seller-dashboard-tile rounded-2xl p-5">
            <p class="text-[10px] uppercase tracking-widest text-slate-500 font-extrabold">Rating</p>
            <p class="mt-2 text-2xl font-black text-amber-600">
                {{ $ratingAverage !== null ? number_format($ratingAverage, 1) : '-' }}
            </p>
            <p class="mt-1 text-xs font-semibold text-slate-500">
                {{ $ratingCount > 0 ? 'Rata-rata dari ' . number_format($ratingCount) . ' penilaian pembeli' : 'Belum ada penilaian pembeli' }}
            </p>
        </div>
    </div>
</section>

{{-- Bagian Identitas Toko dan selanjutnya tetap sama seperti kode asli kamu --}}
<section class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <article class="seller-dashboard-surface bg-white rounded-3xl overflow-hidden lg:col-span-1">
        @if($sellerProfile?->store_photo_path)
            <img src="{{ publicStorageUrl($sellerProfile->store_photo_path) }}" alt="{{ $sellerProfile->store_name ?: 'Foto toko' }}" class="seller-dashboard-photo" loading="lazy" decoding="async">
        @else
            <div class="flex min-h-[15rem] items-center justify-center bg-slate-50 sm:min-h-[18rem]">
                <span class="text-sm font-semibold text-slate-400">Foto toko belum tersedia</span>
            </div>
        @endif
    </article>

    <article class="seller-dashboard-surface bg-white rounded-3xl p-6 lg:col-span-2">
        <h2 class="text-lg font-black text-slate-900">Identitas Toko</h2>
        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
            <div class="seller-dashboard-tile rounded-xl px-4 py-3">
                <p class="text-xs text-slate-500">Nama Penjual</p>
                <p class="mt-1 font-bold text-slate-900">{{ $seller->name }}</p>
            </div>
            <div class="seller-dashboard-tile rounded-xl px-4 py-3">
                <p class="text-xs text-slate-500">Nomor WhatsApp</p>
                <p class="mt-1 break-all font-bold text-slate-900">{{ $seller->whatsapp_number ?: '-' }}</p>
            </div>
            <div class="seller-dashboard-tile rounded-xl px-4 py-3">
                <p class="text-xs text-slate-500">Nama Toko</p>
                <p class="mt-1 font-bold text-slate-900">{{ $sellerProfile?->store_name ?: '-' }}</p>
            </div>
            <div class="seller-dashboard-tile rounded-xl px-4 py-3">
                <p class="text-xs text-slate-500">Titik GPS</p>
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
            <div class="seller-dashboard-tile rounded-xl px-4 py-3 sm:col-span-2">
                <p class="text-xs text-slate-500">Alamat Lengkap</p>
                <p class="mt-1 break-words font-semibold text-slate-700">{{ $sellerProfile?->full_address ?: '-' }}</p>
            </div>
        </div>
    </article>
</section>

<section class="seller-dashboard-surface bg-white rounded-3xl overflow-hidden mb-8">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3 flex-wrap">
        <div>
            <h2 class="text-lg font-black text-slate-900">Settlement Dana</h2>
            <p class="mt-1 text-sm text-slate-500">Status pencairan dana hasil penjualan.</p>
        </div>
        <span class="inline-flex px-3 py-1 rounded-full bg-sky-100 text-sky-700 text-sm font-bold">{{ $recentSettlements->count() }} settlement terbaru</span>
    </div>

    <div class="px-5 py-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
            <div class="seller-dashboard-tile rounded-2xl p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500 font-bold">Perlu Review</p>
                <p class="mt-1 text-2xl font-black text-amber-700">{{ number_format($settlementPending) }}</p>
            </div>
            <div class="seller-dashboard-tile rounded-2xl p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500 font-bold">Dana Ditahan</p>
                <p class="mt-1 text-2xl font-black text-rose-700">{{ number_format($settlementHeld) }}</p>
            </div>
            <div class="seller-dashboard-tile rounded-2xl p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500 font-bold">Siap Dibayar</p>
                <p class="mt-1 text-2xl font-black text-sky-700">{{ number_format($settlementReady) }}</p>
            </div>
            <div class="seller-dashboard-tile rounded-2xl p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500 font-bold">Sudah Dibayar</p>
                <p class="mt-1 text-2xl font-black text-emerald-700">{{ number_format($settlementPaid) }}</p>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
            <div class="seller-dashboard-tile rounded-xl px-4 py-3">
                <p class="text-xs text-slate-500">Dana Dalam Antrean</p>
                <p class="mt-1 text-lg font-black text-slate-900">{{ formatRupiah($settlementOutstandingAmount) }}</p>
                <p class="mt-1 text-xs text-slate-500">Termasuk settlement review, ditahan, dan siap dibayar.</p>
            </div>
            <div class="seller-dashboard-tile rounded-xl px-4 py-3">
                <p class="text-xs text-slate-500">Total Dana Cair</p>
                <p class="mt-1 text-lg font-black text-emerald-700">{{ formatRupiah($settlementPaidAmount) }}</p>
                <p class="mt-1 text-xs text-slate-500">Akumulasi settlement yang sudah ditandai dibayar.</p>
            </div>
        </div>
    </div>

    @if($recentSettlements->isEmpty())
        <div class="px-5 pb-6">
            <div class="seller-dashboard-tile rounded-2xl px-4 py-6 text-center">
                <h3 class="text-base font-black text-slate-800">Belum Ada Settlement</h3>
                <p class="mt-2 text-sm text-slate-500">Settlement akan tampil di sini setelah transaksi selesai dan masuk alur pencairan dana.</p>
            </div>
        </div>
    @else
        <ul class="divide-y divide-slate-100">
            @foreach($recentSettlements as $settlement)
                @php
                    $settlementTransaksi = $settlement->transaksi;
                    $settlementIkan = $settlementTransaksi?->ikan;
                    $statusNote = $settlement->hold_reason ?: $settlement->admin_note;
                    $statusDateLabel = match ($settlement->status) {
                        'paid' => $settlement->paid_at?->format('d M Y H:i'),
                        'ready_to_pay' => $settlement->ready_to_pay_at?->format('d M Y H:i'),
                        'held' => $settlement->held_at?->format('d M Y H:i'),
                        default => $settlement->created_at?->format('d M Y H:i'),
                    };
                @endphp
                <li class="px-5 py-5">
                    <div class="flex items-start justify-between gap-4 flex-wrap">
                        <div class="min-w-0">
                            <p class="break-words text-base font-black text-slate-900">{{ $settlementIkan?->nama_ikan ?? 'Lot tidak tersedia' }}</p>
                            <p class="mt-1 break-all text-sm text-slate-600">Order: {{ $settlementTransaksi?->order_code ?: '-' }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <span class="inline-flex items-center rounded-full px-3 py-1.5 text-[11px] font-extrabold {{ sellerSettlementStatusBadgeClass($settlement->status) }}">{{ sellerSettlementStatusLabel($settlement->status) }}</span>
                                @if($settlementTransaksi)
                                    <span class="inline-flex items-center rounded-full px-3 py-1.5 text-[11px] font-extrabold {{ $settlementTransaksi->buyerProgressBadgeClass() }}">{{ $settlementTransaksi->buyerProgressLabel() }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="w-full sm:w-auto sm:min-w-[210px] text-left sm:text-right">
                            <p class="text-xs text-slate-500">Nominal Settlement</p>
                            <p class="text-lg font-black text-emerald-700">{{ formatRupiah($settlement->amount) }}</p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ match ($settlement->status) {
                                    'paid' => 'Dibayar',
                                    'ready_to_pay' => 'Siap diproses',
                                    'held' => 'Ditahan sejak',
                                    default => 'Dibuat',
                                } }}: {{ $statusDateLabel ?: '-' }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                        <div class="seller-dashboard-tile rounded-xl px-4 py-3">
                            <p class="text-xs text-slate-500">Bank Tujuan</p>
                            <p class="mt-1 font-bold text-slate-900">{{ $settlement->bank_name ?: '-' }}</p>
                            <p class="mt-1 break-all text-xs text-slate-500">{{ $settlement->bank_account_number ?: '-' }}</p>
                        </div>
                        <div class="seller-dashboard-tile rounded-xl px-4 py-3">
                            <p class="text-xs text-slate-500">Nama Rekening</p>
                            <p class="mt-1 font-bold text-slate-900">{{ $settlement->bank_account_name ?: '-' }}</p>
                        </div>
                        <div class="seller-dashboard-tile rounded-xl px-4 py-3">
                            <p class="text-xs text-slate-500">Referensi Transfer</p>
                            <p class="mt-1 break-all font-bold text-slate-900">{{ $settlement->transfer_reference ?: '-' }}</p>
                        </div>
                    </div>

                    @if($statusNote)
                        <div class="mt-4 seller-dashboard-tile rounded-xl px-4 py-3">
                            <p class="text-xs text-slate-500">{{ $settlement->hold_reason ? 'Alasan Ditahan' : 'Catatan Admin' }}</p>
                            <p class="mt-1 font-semibold text-slate-700">{{ $statusNote }}</p>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</section>

@if($completedSales->isEmpty())
    <div class="text-center py-20 bg-white rounded-2xl border border-gray-100">
        <h3 class="text-xl font-bold text-gray-700">Belum Ada Penjualan Selesai</h3>
        <p class="text-gray-400 mt-2 mb-6">Lot yang sudah dikonfirmasi selesai oleh pembeli akan tampil di dashboard ini.</p>
        <a href="{{ route('penjual.ikans.index') }}" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-3 rounded-xl transition">
            Buka Aktivitas Lot
        </a>
    </div>
@else
    <section class="seller-dashboard-surface bg-white rounded-3xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3 flex-wrap">
            <h2 class="text-lg font-black text-slate-900">History Penjualan Selesai</h2>
            <span class="inline-flex px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 text-sm font-bold">{{ $completedSales->total() }} penjualan</span>
        </div>
        <ul class="divide-y divide-slate-100">
            @foreach($completedSales as $trx)
                @php
                    $ikan = $trx->ikan;
                @endphp
                <li class="px-5 py-5">
                    <div class="flex items-start justify-between gap-4 flex-wrap">
                        <div>
                            <p class="text-base font-black text-slate-900">{{ $ikan?->nama_ikan ?? 'Lot tidak tersedia' }}</p>
                            <p class="mt-1 text-sm text-slate-600">Pembeli: {{ $trx->pemenang?->name ?? '-' }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <span class="inline-flex items-center rounded-full px-3 py-1.5 text-[11px] font-extrabold {{ $ikan?->isLelangTurun() ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">{{ $ikan?->isLelangTurun() ? 'Lelang Turun' : 'Lelang Naik' }}</span>
                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1.5 text-[11px] font-extrabold text-emerald-700">Selesai</span>
                                <span class="inline-flex items-center rounded-full px-3 py-1.5 text-[11px] font-extrabold {{ $trx->buyerProgressBadgeClass() }}">{{ $trx->buyerProgressLabel() }}</span>
                            </div>
                        </div>
                        <div class="w-full sm:w-auto sm:min-w-[190px] text-left sm:text-right">
                            <p class="text-xs text-slate-500">Harga Final</p>
                            <p class="text-lg font-black text-emerald-700">{{ formatRupiah($trx->harga_final) }}</p>
                            <p class="mt-1 text-xs text-slate-500">Selesai: {{ $trx->completed_by_buyer_at?->format('d M Y H:i') ?? $trx->updated_at?->format('d M Y H:i') }}</p>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                        <div class="seller-dashboard-tile rounded-xl px-4 py-3">
                            <p class="text-xs text-slate-500">Rating Pembeli</p>
                            <p class="mt-1 font-bold text-slate-900">{{ $trx->buyer_rating ? $trx->buyer_rating . ' bintang' : 'Tidak diisi' }}</p>
                        </div>
                        <div class="seller-dashboard-tile rounded-xl px-4 py-3 sm:col-span-2">
                            <p class="text-xs text-slate-500">Review Pembeli</p>
                            <p class="mt-1 font-semibold text-slate-700">{{ $trx->buyer_review ?: 'Tidak ada review tertulis.' }}</p>
                        </div>
                    </div>

                    <x-fulfillment-photo-grid :transaksi="$trx" title="Foto bukti transaksi" class="mt-4" />

                    @if($ikan)
                        <div class="mt-4">
                            <x-secondary-action-link :href="route('penjual.ikans.show', ['ikan' => $ikan, 'return_url' => $returnUrl])" class="w-full sm:w-auto px-4 py-3 text-sm">
                                Detail Lot
                            </x-secondary-action-link>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
        <div class="px-5 py-4 border-t border-slate-100">
            {{ $completedSales->links() }}
        </div>
    </section>
@endif

@endsection
