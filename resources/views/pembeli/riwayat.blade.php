@extends('layouts.app')
@section('title', 'Riwayat Pembelian')

@section('content')
@php
    $tipeLelang = $tipeLelang ?? 'semua';
    $historyStats = $historyStats ?? [];
    $totalRiwayat = (int) ($historyStats['total'] ?? 0);
    $nilaiRiwayat = (float) ($historyStats['nilai'] ?? 0);
    $totalDinilai = (int) ($historyStats['rated'] ?? 0);
    $returnUrl = request()->fullUrl();
    $typeFilterBaseParams = [];
@endphp

<style>
    .buyer-history-hero {
        background:
            radial-gradient(circle at 12% 8%, rgba(16, 185, 129, 0.15), transparent 34%),
            radial-gradient(circle at 88% 0%, rgba(14, 165, 233, 0.13), transparent 36%),
            linear-gradient(145deg, #f8fcff 0%, #effdf7 52%, #f8fcff 100%);
    }

    .buyer-history-surface {
        border: 1px solid rgba(226, 232, 240, 0.95);
        box-shadow: 0 16px 24px -22px rgba(15, 23, 42, 0.6);
    }

    .buyer-history-tile {
        background: rgba(248, 250, 252, 0.9);
        border: 1px solid rgba(226, 232, 240, 0.9);
    }

    .buyer-history-accordion {
        overflow: hidden;
        border: 1px solid rgba(226, 232, 240, 0.95);
        border-radius: 1rem;
        background: rgba(248, 250, 252, 0.85);
    }

    .buyer-history-accordion summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        min-height: 3.25rem;
        padding: 0.875rem 1rem;
        cursor: pointer;
        list-style: none;
        font-size: 0.875rem;
        font-weight: 800;
        color: #1e293b;
    }

    .buyer-history-accordion summary::-webkit-details-marker {
        display: none;
    }
</style>

<section class="buyer-history-hero rounded-3xl border border-emerald-100/70 px-5 py-6 sm:px-8 sm:py-9 mb-8">
    <p class="inline-flex items-center px-3 py-1 rounded-full text-xs font-extrabold tracking-[0.14em] uppercase text-emerald-700 bg-emerald-100/70 border border-emerald-200/70">
        Riwayat Pembeli
    </p>
    <h1 class="mt-3 text-3xl sm:text-4xl font-black tracking-tight text-slate-900">Riwayat Pembelian</h1>
    <p class="mt-2 text-slate-600 max-w-2xl">Semua pesanan yang sudah selesai ada di sini.</p>

    <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3">
        <div class="buyer-history-tile rounded-2xl p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500 font-bold">Total Pesanan Selesai</p>
            <p class="mt-1 text-2xl font-black text-slate-900">{{ number_format($totalRiwayat) }}</p>
        </div>
        <div class="buyer-history-tile rounded-2xl p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500 font-bold">Total Nilai Pembelian</p>
            <p class="mt-1 text-2xl font-black text-emerald-700">{{ formatRupiah($nilaiRiwayat) }}</p>
        </div>
        <div class="buyer-history-tile col-span-2 rounded-2xl p-4 sm:col-span-1">
            <p class="text-xs uppercase tracking-wide text-slate-500 font-bold">Sudah Diberi Rating</p>
            <p class="mt-1 text-2xl font-black text-cyan-700">{{ number_format($totalDinilai) }}</p>
        </div>
    </div>
</section>

@include('components.auction-type-filter', [
    'tipeLelang' => $tipeLelang,
    'allUrl' => route('pembeli.riwayat', $typeFilterBaseParams),
    'naikUrl' => route('pembeli.riwayat', ['tipe_lelang' => 'naik']),
    'turunUrl' => route('pembeli.riwayat', ['tipe_lelang' => 'turun']),
    'marginClass' => 'mb-8',
])

@if($riwayatPembelian->isEmpty())
    <div class="text-center py-20 bg-white rounded-2xl border border-gray-100">
        <h3 class="text-xl font-bold text-gray-700">Belum Ada Riwayat Pembelian</h3>
        <p class="mt-2 mb-6 text-gray-400">Pesanan selesai akan tampil di sini.</p>
        <a href="{{ route('ikans.index') }}" class="inline-flex min-h-[48px] items-center justify-center rounded-xl bg-blue-600 px-6 py-3 text-white font-bold transition hover:bg-blue-700">
            Buka Marketplace
        </a>
    </div>
@else
    <section class="buyer-history-surface bg-white rounded-3xl overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-4 sm:px-5">
            <h2 class="text-lg font-black text-slate-900">Pesanan Selesai</h2>
            <span class="inline-flex px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 text-sm font-bold">{{ $riwayatPembelian->total() }} pesanan</span>
        </div>
        <ul class="divide-y divide-slate-100">
            @foreach($riwayatPembelian as $trx)
                @php
                    $ikan = $trx->ikan;
                @endphp
                <li class="px-4 py-6 sm:px-5">
                    <div class="flex items-start justify-between gap-5 flex-wrap">
                        <div class="min-w-0">
                            <p class="text-base font-black text-slate-900">{{ $ikan?->nama_ikan ?? 'Lot tidak tersedia' }}</p>
                            <p class="mt-1 text-sm text-slate-600">Penjual: {{ $ikan?->user?->name ?? '-' }}</p>
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

                    <details class="buyer-history-accordion mt-4">
                        <summary>
                            <span>Penilaian &amp; review</span>
                            <span class="text-[11px] font-bold text-slate-500">{{ $trx->buyer_rating ? $trx->buyer_rating . ' bintang' : 'Belum diberi rating' }}</span>
                        </summary>
                        <div class="border-t border-slate-100 px-4 py-4">
                            <div class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
                                <div class="buyer-history-tile rounded-xl px-4 py-3">
                                    <p class="text-xs text-slate-500">Rating</p>
                                    <p class="mt-1 font-bold text-slate-900">{{ $trx->buyer_rating ? $trx->buyer_rating . ' bintang' : 'Tidak diisi' }}</p>
                                </div>
                                <div class="buyer-history-tile rounded-xl px-4 py-3 sm:col-span-2">
                                    <p class="text-xs text-slate-500">Review</p>
                                    <p class="mt-1 font-semibold text-slate-700">{{ $trx->buyer_review ?: 'Tidak ada review tertulis.' }}</p>
                                </div>
                            </div>
                        </div>
                    </details>

                    <x-fulfillment-photo-grid :transaksi="$trx" title="Foto bukti transaksi" class="mt-4" />

                    <div class="mt-4 grid grid-cols-1 gap-2.5 sm:flex sm:flex-wrap">
                        @if($ikan)
                            <x-secondary-action-link :href="route('pembeli.aktivitas.penilaian', ['ikan' => $ikan, 'return_url' => $returnUrl])" class="w-full rounded-xl px-4 py-3 text-sm sm:w-auto">
                                Lihat Penilaian
                            </x-secondary-action-link>
                            <x-secondary-action-link :href="route('ikans.show', ['ikan' => $ikan, 'return_url' => $returnUrl])" class="w-full rounded-xl px-4 py-3 text-sm sm:w-auto">
                                Detail Lot
                            </x-secondary-action-link>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
        <div class="border-t border-slate-100 px-4 py-4 sm:px-5">
            {{ $riwayatPembelian->links() }}
        </div>
    </section>
@endif
@endsection
