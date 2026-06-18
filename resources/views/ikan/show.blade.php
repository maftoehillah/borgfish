@extends('layouts.app')
@section('title', $ikan->nama_ikan . ' - Borgfish')

@section('content')
@php
    $isLelangTurun = $ikan->isLelangTurun();
    $requestedReturnUrl = request()->query('return_url', url()->previous());
    $safeReturnUrl = safeInternalReturnUrl($requestedReturnUrl, route('ikans.index'));
    $currentPageUrl = request()->fullUrl();
    $riwayatBid = $isLelangTurun
        ? $ikan->bids->sortByDesc('jumlah_bid')->values()
        : $ikan->bids->sortByDesc('jumlah_bid')->values();
    $bestBid = $riwayatBid->first();
    $reverseThreshold = max(1000, (int) round((float) $ikan->bidMinimal()));
    $reverseInputMin = 1000;
    $buyNowTarget = $ikan->buyNowTarget();
    $sellerProfile = $ikan->user?->sellerProfile;
    $sellerStoreName = $sellerProfile?->store_name ?: $ikan->user?->name;
    $sellerStorePhoto = $sellerProfile?->store_photo_path ? publicStorageUrl($sellerProfile->store_photo_path) : null;
@endphp

<style>
    .lot-detail-hero {
        background:
            radial-gradient(circle at 8% 10%, rgba(34, 211, 238, 0.18), transparent 34%),
            radial-gradient(circle at 92% 0%, rgba(14, 165, 233, 0.16), transparent 36%),
            linear-gradient(150deg, #f8fdff 0%, #eef8ff 52%, #f8fcff 100%);
    }

    .lot-surface {
        border: 1px solid rgba(226, 232, 240, 0.95);
        box-shadow: 0 18px 26px -24px rgba(15, 23, 42, 0.6);
    }

    .lot-info-tile {
        background: rgba(248, 250, 252, 0.9);
        border: 1px solid rgba(226, 232, 240, 0.9);
    }

    .lot-mobile-highlight {
        background: rgba(255, 255, 255, 0.92);
        border: 1px solid rgba(186, 230, 253, 0.72);
        box-shadow: 0 14px 26px -22px rgba(15, 23, 42, 0.45);
    }

    .lot-main-media {
        width: 100%;
        aspect-ratio: 1 / 1;
        object-fit: cover;
        object-position: center;
        display: block;
    }

    .lot-main-media-empty {
        display: flex;
        width: 100%;
        aspect-ratio: 1 / 1;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #f0f9ff 0%, #cffafe 100%);
    }

    @media (min-width: 1280px) {
        .lot-main-media,
        .lot-main-media-empty {
            max-height: 420px;
        }
    }

    .lot-spec-card summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.25rem;
        cursor: pointer;
        list-style: none;
    }

    .lot-spec-card summary::-webkit-details-marker {
        display: none;
    }

    .lot-spec-card summary::after {
        content: '';
        flex-shrink: 0;
        width: 0.7rem;
        height: 0.7rem;
        border-right: 2px solid #0f172a;
        border-bottom: 2px solid #0f172a;
        transform: rotate(45deg);
        transition: transform 0.2s ease;
    }

    .lot-spec-card[open] summary::after {
        transform: rotate(225deg);
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
        background: linear-gradient(135deg, #059669 0%, #0891b2 100%);
        box-shadow: 0 16px 30px -16px rgba(5, 150, 105, 0.9);
        animation: buyer-cta-pulse 1.8s ease-in-out infinite;
        transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
    }

    .buyer-priority-cta-confirm:hover {
        transform: translateY(-1px);
        box-shadow: 0 20px 36px -14px rgba(8, 145, 178, 0.92);
        filter: brightness(1.03);
    }

    .buyer-priority-cta-confirm:focus-visible {
        outline: none;
        box-shadow:
            0 0 0 3px rgba(34, 211, 238, 0.3),
            0 20px 36px -14px rgba(8, 145, 178, 0.92);
    }

    @keyframes buyer-cta-pulse {
        0%,
        100% {
            box-shadow: 0 16px 30px -16px rgba(5, 150, 105, 0.9);
        }
        50% {
            box-shadow:
                0 0 0 5px rgba(45, 212, 191, 0.18),
                0 22px 38px -14px rgba(8, 145, 178, 0.95);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .buyer-priority-cta-pay,
        .buyer-priority-cta-confirm {
            transition: none;
        }

        .buyer-priority-cta-confirm {
            animation: none;
        }

        .lot-spec-card summary::after {
            transition: none;
        }
    }

</style>

<section class="lot-detail-hero rounded-3xl border border-cyan-100/70 px-5 py-5 sm:px-8 sm:py-7 mb-8">
    <x-back-button :href="$safeReturnUrl" label="Kembali" class="mb-4" />

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <div>
                <a href="{{ route('seller.public', [
                    'seller' => $ikan->user,
                    'return_url' => $currentPageUrl,
                ]) }}" class="inline-flex w-full items-center gap-3 rounded-2xl border border-cyan-100 bg-white/90 px-3.5 py-2.5 shadow-sm transition hover:border-cyan-200 hover:bg-cyan-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 sm:w-auto">
                    @if($sellerStorePhoto)
                        <img src="{{ $sellerStorePhoto }}" alt="{{ $sellerStoreName ?: 'Foto toko penjual' }}" class="aspect-square h-10 w-10 shrink-0 rounded-full object-cover border border-white shadow-sm" decoding="async">
                    @else
                        <span class="inline-flex aspect-square h-10 w-10 shrink-0 items-center justify-center rounded-full bg-cyan-100 text-xs font-black text-cyan-800 border border-white shadow-sm">
                            {{ $ikan->user?->initials() ?: 'TK' }}
                        </span>
                    @endif
                    <span class="text-left leading-tight">
                        <span class="block text-[11px] font-extrabold uppercase tracking-[0.13em] text-cyan-700">Toko Penjual</span>
                        <span class="block text-sm font-black text-slate-900">{{ $sellerStoreName ?: 'Profil Toko' }}</span>
                    </span>
                </a>
            </div>
            <h1 class="mt-4 text-2xl sm:text-4xl font-black tracking-tight text-slate-900">{{ $ikan->nama_ikan }}</h1>
            <p class="mt-2 text-sm text-slate-600">Lot dari {{ $ikan->user->name }} dengan pemantauan data bid real-time.</p>
        </div>
        <span id="lot-status-badge" class="px-4 py-2 rounded-full text-sm font-bold {{ $ikan->status === 'aktif' ? 'bg-emerald-100 text-emerald-800' : ($ikan->status === 'menunggu' ? 'bg-amber-100 text-amber-800' : ($ikan->status === 'terbayar' ? 'bg-cyan-100 text-cyan-800' : 'bg-slate-100 text-slate-700')) }}">
            {{ lotStatusLabel($ikan->status) }}
        </span>
    </div>

    <div class="mt-4 flex flex-wrap gap-2">
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold {{ $isLelangTurun ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
            {{ $isLelangTurun ? 'Lelang Turun' : 'Lelang Naik' }}
        </span>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700">{{ $ikan->bids->count() }} bid</span>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-cyan-100 text-cyan-800">{{ $ikan->berat }} kg</span>
        <span class="hidden sm:inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700 capitalize">{{ $ikan->kondisi === 'beku' ? 'Frozen' : $ikan->kondisi }}</span>
    </div>

    <div class="mt-5 grid grid-cols-1 gap-3 sm:hidden">
        <div class="lot-mobile-highlight rounded-2xl px-4 py-3">
            <p class="text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-400">Harga Saat Ini</p>
            <p class="mt-1 text-2xl font-black text-cyan-700">{{ formatRupiah($ikan->harga_tertinggi) }}</p>
            <p class="mt-1 text-xs font-semibold text-slate-500">Cek harga terbaru sebelum bid.</p>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div class="lot-mobile-highlight rounded-2xl px-4 py-3">
                <p class="text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-400">Berat</p>
                <p class="mt-1 text-lg font-black text-slate-900">{{ $ikan->berat }} kg</p>
            </div>
            <div class="lot-mobile-highlight rounded-2xl px-4 py-3">
                <p class="text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-400">Batas Bid</p>
                <p class="mt-1 text-lg font-black text-slate-900">{{ formatRupiah($ikan->bidMinimal()) }}</p>
            </div>
        </div>
    </div>
</section>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="lg:col-span-2 space-y-6">
        <article class="lot-surface bg-white rounded-3xl overflow-hidden">
            <div class="relative">
                @if($ikan->foto)
                    <img src="{{ publicStorageUrl($ikan->foto) }}" alt="{{ $ikan->nama_ikan }}" class="lot-main-media" decoding="async" fetchpriority="high">
                @else
                    <div class="lot-main-media-empty">
                        <span class="text-slate-500 font-semibold">Tidak ada foto</span>
                    </div>
                @endif
                <div class="absolute top-4 left-4 inline-flex px-3 py-1 rounded-full text-xs font-black bg-slate-900/75 text-white">
                    Live Lot
                </div>
            </div>

            @if($ikan->video)
                <x-lot-video-modal
                    :video-url="publicStorageUrl($ikan->video)"
                    :modal-id="'buyer-lot-video-' . $ikan->id"
                    title="Video Lot"
                    description="Video dibuka lewat pop-up"
                />
            @endif
        </article>

        <details class="lot-surface lot-spec-card bg-white rounded-3xl">
            <summary>
                <span class="min-w-0">
                    <span class="block text-base font-black text-slate-900">Spesifikasi Lot</span>
                    <span class="mt-0.5 block text-[11px] font-semibold text-slate-500">Buka untuk melihat spesifikasi lengkap.</span>
                </span>
            </summary>
            <div class="border-t border-slate-100 px-5 pb-5 pt-4 sm:border-t-0 sm:p-6">
                <div class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                <div class="lot-info-tile rounded-xl px-4 py-3">
                    <p class="text-xs text-slate-400">Harga Awal</p>
                    <p class="font-bold text-slate-800">{{ formatRupiah($ikan->harga_awal) }}</p>
                </div>
                <div class="lot-info-tile rounded-xl px-4 py-3">
                    <p class="text-xs text-slate-400">{{ $isLelangTurun ? 'Aturan Bid' : 'Min. Increment' }}</p>
                    <p class="font-bold text-slate-800">{{ $isLelangTurun ? 'Kelipatan Rp 1.000 (min Rp 1.000)' : formatRupiah($ikan->minimal_increment) }}</p>
                </div>
                <div class="lot-info-tile rounded-xl px-4 py-3">
                    <p class="text-xs text-slate-400">Mulai Lelang</p>
                    <p class="font-bold text-slate-800">{{ $ikan->waktu_mulai->format('d M Y H:i') }}</p>
                </div>
                <div class="lot-info-tile rounded-xl px-4 py-3">
                    <p class="text-xs text-slate-400">Akhir Lelang</p>
                    <p id="lot-end-time" class="font-bold text-slate-800">{{ $ikan->waktu_selesai->format('d M Y H:i') }}</p>
                </div>
                <div class="lot-info-tile rounded-xl px-4 py-3">
                    <p class="text-xs text-slate-400">Jenis Kemasan</p>
                    <p class="font-bold text-slate-800 capitalize">{{ $ikan->jenis_kemasan ?: '-' }}</p>
                </div>
                <div class="lot-info-tile rounded-xl px-4 py-3">
                    <p class="text-xs text-slate-400">Beli Sekarang</p>
                    <p id="buy-now-target" class="font-bold text-cyan-700">{{ $buyNowTarget ? formatRupiah($buyNowTarget) : '-' }}</p>
                </div>
                <div class="lot-info-tile rounded-xl px-4 py-3">
                    <p class="text-xs text-slate-400">Estimasi Jumlah Ekor</p>
                    <p class="font-bold text-slate-800">{{ $ikan->estimasi_jumlah_ekor ?: '-' }}</p>
                </div>
                <div class="lot-info-tile rounded-xl px-4 py-3">
                    <p class="text-xs text-slate-400">Tanggal Tangkap</p>
                    <p class="font-bold text-slate-800">{{ $ikan->tanggal_tangkap?->format('d M Y') ?: '-' }}</p>
                </div>
                <div class="lot-info-tile rounded-xl px-4 py-3 sm:col-span-2">
                    <p class="text-xs text-slate-400">Watermark Foto</p>
                    <p class="font-semibold text-slate-700">
                        {{ $ikan->foto_diambil_pada ? $ikan->foto_diambil_pada->format('d M Y H:i') : '-' }}
                    </p>
                </div>
                </div>

                @if($ikan->deskripsi)
                    <div class="mt-5 border-t border-slate-100 pt-5">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Deskripsi</p>
                        <p class="leading-relaxed text-slate-700">{{ $ikan->deskripsi }}</p>
                    </div>
                @endif
            </div>
        </details>

        <details class="lot-surface lot-spec-card bg-white rounded-3xl">
            <summary>
                <span class="min-w-0">
                    <span class="block text-base font-black text-slate-900">Riwayat Bid</span>
                    <span class="mt-0.5 block text-[11px] font-semibold text-slate-500">Buka untuk melihat peringkat dan nominal bid terbaru.</span>
                </span>
            </summary>
            <div class="border-t border-slate-100 px-5 pb-5 pt-4 sm:border-t-0 sm:p-6">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ min($riwayatBid->count(), 15) }} tampil</span>
                </div>
                @if($ikan->bids->isEmpty())
                    <p class="py-6 text-center text-sm text-slate-400">Belum ada bid. Jadilah yang pertama.</p>
                @else
                    <div class="divide-y divide-slate-100">
                        @foreach($riwayatBid->take(15) as $i => $bid)
                            <div class="flex items-start justify-between gap-3 py-3 {{ $i === 0 ? 'bg-amber-50 -mx-3 rounded-xl px-3' : '' }}">
                                <div class="flex min-w-0 items-center gap-3">
                                    @if($i === 0)
                                        <span class="text-xs font-black text-amber-600">TERBAIK</span>
                                    @else
                                        <span class="w-5 text-center text-sm text-slate-300">{{ $i + 1 }}</span>
                                    @endif
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-800">
                                            {{ $ikan->isAktif() ? maskedBidderName($bid->user->name, $bid->user_id) : $bid->user->name }}
                                        </p>
                                        <p class="text-xs text-slate-400">{{ $bid->created_at->diffForHumans() }}</p>
                                    </div>
                                </div>
                                <span class="shrink-0 text-right font-bold {{ $i === 0 ? 'text-base text-amber-700' : 'text-sm text-slate-700' }}">
                                    {{ formatRupiah($bid->jumlah_bid) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </details>
    </div>

    <aside class="space-y-4 lg:sticky lg:top-20 h-fit">
        <article class="rounded-3xl p-6 text-white bg-gradient-to-br from-cyan-700 via-sky-700 to-blue-800 shadow-xl">
            <p class="text-cyan-100 text-sm">{{ $isLelangTurun ? 'Bid Teratas Saat Ini' : 'Harga Tertinggi Saat Ini' }}</p>
            <p id="harga-tertinggi" class="text-4xl font-black mt-1">{{ formatRupiah($ikan->harga_tertinggi) }}</p>
            @if($ikan->isAktif())
                <div class="mt-4 bg-white/10 rounded-xl p-3">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-cyan-100 text-xs">Berakhir dalam</p>
                        <p class="text-cyan-100 text-xs font-bold">Live</p>
                    </div>
                    <p id="countdown-display" class="text-white font-black text-xl">--</p>
                    <div class="mt-2 h-1.5 rounded-full bg-white/20 overflow-hidden">
                        <div id="countdown-progress" class="h-full rounded-full bg-cyan-300" style="width: 0%"></div>
                    </div>
                </div>
                @if($ikan->anti_sniping_enabled)
                    <p class="text-xs text-cyan-100 mt-3">Perpanjangan otomatis aktif: <span id="anti-sniping-usage">{{ $ikan->anti_sniping_extensions_used }}/{{ $ikan->anti_sniping_max_extensions }}</span></p>
                    <p class="text-xs text-cyan-100 mt-1">Bid di detik akhir bisa memperpanjang lelang.</p>
                    <p class="text-xs text-cyan-100 mt-1">Batas akhir maksimal: <span id="anti-sniping-max-end" class="font-semibold">{{ $ikan->waktu_selesai->copy()->addSeconds($ikan->anti_sniping_extend_seconds * max(0, $ikan->anti_sniping_max_extensions - $ikan->anti_sniping_extensions_used))->format('d M Y H:i') }}</span></p>
                @endif
            @endif
        </article>

        @if($ikan->isAktif() && $buyNowTarget)
            <article id="buy-now-card" class="lot-surface bg-white rounded-3xl p-6 border border-cyan-100">
                <h3 class="font-bold text-slate-900 mb-1">Beli Sekarang</h3>
                <p class="text-sm text-slate-500 mb-3">{{ $isLelangTurun ? 'Akhiri lelang sekarang di harga patokan penjual.' : 'Akhiri lelang saat ini juga dengan harga tetap.' }}</p>
            <p class="text-2xl font-black text-cyan-700 mb-4">{{ formatRupiah($buyNowTarget) }}</p>
                @auth
                    @if($canActAsBuyer && ! $isOwnLot)
                        <form
                            action="{{ route('ikans.buy_now', $ikan) }}"
                            method="POST"
                            data-confirm-title="Beli sekarang?"
                            data-confirm-message="Lelang akan langsung ditutup dan invoice pembayaran akan dibuat untuk lot ini."
                            data-confirm-confirm-label="Beli Sekarang"
                        >
                            @csrf
                            <input type="hidden" name="return_url" value="{{ $currentPageUrl }}">
                            <button type="submit" class="w-full bg-cyan-700 hover:bg-cyan-800 text-white font-bold py-3 rounded-xl transition">
                                Beli Sekarang
                            </button>
                        </form>
                    @elseif($isOwnLot && $isSuperAdminBuyerMode)
                        <p class="text-xs text-slate-500">Lot ini dibuat oleh akun admin yang sama, jadi tidak bisa dibeli dari mode pembeli.</p>
                    @else
                        <p class="text-xs text-slate-500">Beli sekarang hanya untuk akun pembeli dan bukan pemilik lot.</p>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="block text-center bg-cyan-700 hover:bg-cyan-800 text-white font-bold py-3 rounded-xl transition">
                        Login untuk Beli Sekarang
                    </a>
                @endauth
            </article>
        @endif

        @auth
            @if($bidSaya !== null)
                <article class="lot-surface bg-white rounded-3xl p-6">
                    <h3 class="font-bold text-slate-900 mb-2">Status Bid Anda</h3>
                    <p class="text-sm text-slate-500 mb-3">Bid terbaik Anda: <strong>{{ formatRupiah($bidSaya) }}</strong></p>

                    @if($isMemimpin)
                        <div class="bg-emerald-100 text-emerald-800 rounded-xl px-4 py-3 text-sm font-bold">
                            Kamu memimpin.
                        </div>
                    @elseif($isKalah)
                        <div class="bg-rose-100 text-rose-800 rounded-xl px-4 py-3 text-sm font-bold">
                            Kamu kalah, silakan bid lagi.
                        </div>
                    @endif
                </article>
            @endif

        @endauth

        @if($ikan->isSelesai() && $ikan->transaksi)
            <article id="winner-card" class="lot-surface bg-white rounded-3xl border-2 {{ $isPemenang ? 'border-amber-300 bg-amber-50/60' : 'border-slate-200' }} p-6">
                <h3 class="font-bold text-slate-900">Pemenang Lelang</h3>
                <p class="font-bold text-lg text-slate-800 mt-2">{{ $ikan->transaksi->pemenang->name }}</p>
                <p class="text-cyan-700 font-black text-xl mt-1">{{ formatRupiah($ikan->transaksi->harga_final) }}</p>
                <p class="text-xs text-slate-500 mt-1">Pembayaran: {{ paymentStatusLabel($ikan->transaksi->payment_status ?? null) }} &bull; Progress: {{ $ikan->transaksi->buyerProgressLabel() }}</p>
                @if($isPemenang)
                    <div class="mt-4 p-3 bg-amber-100 rounded-xl text-center">
                        <p class="text-amber-800 font-bold">Selamat, Anda Menang.</p>
                    </div>
                    @if($ikan->transaksi->isBelumBayar())
                        <a href="{{ route('pembayaran.show', ['transaksi' => $ikan->transaksi, 'return_url' => $currentPageUrl]) }}" class="buyer-priority-cta-pay mt-3 inline-flex w-full items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-extrabold tracking-wide text-white">
                            <span class="inline-flex h-2.5 w-2.5 rounded-full bg-white shadow-[0_0_0_3px_rgba(255,255,255,0.25)]" aria-hidden="true"></span>
                            Bayar Sekarang
                        </a>
                    @elseif($ikan->transaksi->isLunas())
                        <div class="mt-3 bg-emerald-100 rounded-xl py-3 text-center">
                            <p class="text-emerald-700 font-bold">Lunas dibayar</p>
                            <p class="text-xs text-emerald-600">{{ $ikan->transaksi->dibayar_pada?->format('d M Y H:i') }}</p>
                        </div>

                        @if($ikan->transaksi->buyer_pickup_submitted_at && ! $ikan->transaksi->seller_pickup_recorded_at)
                            <div class="mt-3 bg-indigo-50 border border-indigo-100 rounded-xl p-3 text-sm text-indigo-800">
                                <p class="font-bold">Data penjemput sudah tersimpan</p>
                                <p>Sopir: {{ $ikan->transaksi->buyer_pickup_name ?: '-' }}</p>
                                <p class="break-all">Plat: {{ $ikan->transaksi->buyer_pickup_plate_number ?: '-' }}</p>
                                <p>Status: Menunggu validasi penjual</p>
                            </div>
                        @elseif((string) $ikan->transaksi->pickup_status === 'pickup_arrived')
                            <div class="mt-3 bg-cyan-50 border border-cyan-100 rounded-xl p-3 text-sm text-cyan-800">
                                <p class="font-bold">Proses Penjemputan</p>
                                <p>Sopir: {{ $ikan->transaksi->buyer_pickup_name ?: '-' }}</p>
                                <p class="break-all">Plat: {{ $ikan->transaksi->buyer_pickup_plate_number ?: '-' }}</p>
                                <p>Status: {{ pickupStatusLabel($ikan->transaksi->pickup_status) }}</p>
                            </div>
                        @endif

                        <x-fulfillment-photo-grid :transaksi="$ikan->transaksi" title="Foto Packing & Penjemputan" />

                        @if($ikan->transaksi->pickup_status === 'pickup_arrived')
                            <form
                                action="{{ route('pembeli.ikans.diterima', $ikan) }}"
                                method="POST"
                                class="mt-3 space-y-2"
                                data-confirm-title="Konfirmasi transaksi selesai?"
                                data-confirm-message="Pastikan ikan sudah diterima dan kondisi barang sudah sesuai sebelum menyelesaikan transaksi."
                                data-confirm-confirm-label="Konfirmasi Selesai"
                            >
                                @csrf
                                <input type="hidden" name="return_url" value="{{ $currentPageUrl }}">
                                <select name="buyer_rating" class="w-full border border-emerald-200 rounded-xl px-3 py-2 text-sm bg-white">
                                    <option value="">Rating barang (opsional)</option>
                                    @for($rating = 5; $rating >= 1; $rating--)
                                        <option value="{{ $rating }}">{{ $rating }} bintang</option>
                                    @endfor
                                </select>
                                <textarea name="buyer_review" rows="2" class="w-full border border-emerald-200 rounded-xl px-3 py-2 text-sm bg-white" placeholder="Review barang (opsional)"></textarea>
                                <button type="submit" class="buyer-priority-cta-confirm inline-flex w-full items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-extrabold tracking-wide text-white">
                                    <span class="inline-flex h-2.5 w-2.5 rounded-full bg-white shadow-[0_0_0_3px_rgba(255,255,255,0.25)]" aria-hidden="true"></span>
                                    Konfirmasi Selesai
                                </button>
                            </form>
                        @elseif($ikan->transaksi->pickup_status === 'completed')
                            <div class="mt-3 bg-emerald-100 rounded-xl py-3 text-center">
                                <p class="text-emerald-700 font-bold">Transaksi selesai</p>
                            </div>
                        @endif
                    @endif
                @endif
            </article>
        @elseif($tidakAdaPemenang)
            <article class="lot-surface bg-white rounded-3xl p-6">
                <h3 class="font-bold text-slate-900 mb-2">Hasil Lelang</h3>
                <p class="text-sm text-slate-600">Lelang selesai, tetapi tidak ada pemenang karena belum ada bid.</p>
            </article>
        @endif

        @if($ikan->isAktif())
            @auth
                @if($canActAsBuyer && ! $isOwnLot)
                    <article id="mobile-primary-action" class="lot-surface bg-white rounded-3xl p-6">
                        <h3 class="font-bold text-slate-900 mb-1">Pasang Bid</h3>
                        <p class="text-xs text-slate-400 mb-4">
                            {{ $isLelangTurun ? 'Bid maksimal dari harga patokan' : 'Bid minimal' }}:
                            <strong id="bid-threshold" class="text-cyan-700">{{ formatRupiah($ikan->bidMinimal()) }}</strong>
                        </p>
                        <div class="mb-4 rounded-2xl border border-cyan-100 bg-cyan-50/70 px-4 py-3 text-xs text-slate-600">
                            <p>Jika menang, Anda wajib membayar invoice dalam 30 menit melalui payment gateway.</p>
                        </div>
                        <form action="{{ route('bid.store', $ikan) }}" method="POST">
                            @csrf
                            <input type="hidden" name="return_url" value="{{ $currentPageUrl }}">
                            <input
                                id="jumlah-bid-input"
                                type="number"
                                name="jumlah_bid"
                                min="{{ $isLelangTurun ? $reverseInputMin : $ikan->bidMinimal() }}"
                                @if($isLelangTurun) max="{{ $reverseThreshold }}" @endif
                                step="{{ $isLelangTurun ? 1000 : 1000 }}"
                                data-bid-direction="{{ $isLelangTurun ? 'turun' : 'naik' }}"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                placeholder="{{ number_format($ikan->bidMinimal(), 0, ',', '.') }}"
                                required
                                class="w-full border border-slate-200 rounded-xl px-4 py-3 text-slate-800 font-semibold focus:outline-none focus:ring-2 focus:ring-cyan-400 mb-3"
                            >
                            <p class="text-[11px] text-slate-500 mb-3">
                                Nominal bid akan diproses sebagai rupiah bulat (tanpa desimal).
                                @if($isLelangTurun)
                                    Untuk lelang turun, nominal harus kelipatan Rp 1.000 dan di bawah harga patokan.
                                @endif
                            </p>
                            <button type="submit" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold py-3 rounded-xl transition text-lg">
                                Pasang Bid
                            </button>
                        </form>
                    </article>
                @elseif($isOwnLot)
                    <article class="bg-slate-50 rounded-3xl border border-slate-200 p-6 text-center">
                        @if($isSuperAdminBuyerMode)
                            <p class="text-slate-600 text-sm font-semibold">Lot ini dibuat oleh akun admin yang sama.</p>
                            <p class="mt-1 text-slate-500 text-xs">Self-bid tetap diblokir untuk menjaga integritas lelang. Untuk uji bid, gunakan akun pembeli lain atau bid pada lot milik penjual lain.</p>
                        @else
                            <p class="text-slate-500 text-sm">Ini adalah lot yang Anda upload.</p>
                        @endif
                    </article>
                @else
                    <article class="bg-slate-50 rounded-3xl border border-slate-200 p-6 text-center">
                        <p class="text-slate-500 text-sm">Akun penjual tidak bisa melakukan bid.</p>
                    </article>
                @endif
            @else
                <article id="mobile-primary-action" class="lot-surface bg-white rounded-3xl p-6 text-center">
                    <p class="text-slate-600 mb-4 text-sm">Masuk untuk ikut lelang ini.</p>
                    <a href="{{ route('login') }}" class="block bg-slate-900 hover:bg-slate-800 text-white font-bold py-3 rounded-xl transition">
                        Masuk dan Bid
                    </a>
                    <a href="{{ route('register') }}" class="block mt-2 text-cyan-700 hover:underline text-sm">
                        Belum punya akun? Daftar
                    </a>
                </article>
            @endauth
        @endif
    </aside>
</div>

@endsection

@push('scripts')
<script>
    (() => {
        const stateUrl = @js(route('ikans.state', $ikan));
        let initialStatus = @js($ikan->status);
        let currentEndTimeISO = @js($ikan->waktu_selesai?->toISOString());
        const startTimeISO = @js($ikan->waktu_mulai?->toISOString());
        let bidDirection = @js($ikan->isLelangTurun() ? 'turun' : 'naik');
        let serverTimeOffsetMs = 0;

        function syncServerTime(serverTimeISO) {
            if (!serverTimeISO) return;

            const serverMs = new Date(serverTimeISO).getTime();
            if (!Number.isFinite(serverMs)) return;

            serverTimeOffsetMs = serverMs - Date.now();
        }

        function getServerNowMs() {
            return Date.now() + serverTimeOffsetMs;
        }

        function formatLotStatus(status) {
            const labels = {
                aktif: 'Aktif',
                menunggu: 'Menunggu',
                selesai: 'Selesai',
                terbayar: 'Terbayar',
            };

            return labels[status] ?? String(status || '').toUpperCase();
        }

        function renderCountdown() {
            const countdownEl = document.getElementById('countdown-display');
            const progressEl = document.getElementById('countdown-progress');
            if (!countdownEl || !currentEndTimeISO) return;

            const nowMs = getServerNowMs();
            const endMs = new Date(currentEndTimeISO).getTime();
            if (!Number.isFinite(endMs)) {
                countdownEl.textContent = '--';
                return;
            }

            const startMs = startTimeISO ? new Date(startTimeISO).getTime() : nowMs;
            const diff = endMs - nowMs;

            if (diff <= 0) {
                countdownEl.textContent = 'Selesai';
                if (progressEl) {
                    progressEl.style.width = '100%';
                }
                return;
            }

            const h = Math.floor(diff / 3600000);
            const m = Math.floor((diff % 3600000) / 60000);
            const s = Math.floor((diff % 60000) / 1000);
            countdownEl.textContent = `${h}j ${m}m ${s}d`;

            if (progressEl) {
                const safeStartMs = Number.isFinite(startMs) ? startMs : nowMs;
                const total = Math.max(endMs - safeStartMs, 1);
                const elapsed = Math.max(nowMs - safeStartMs, 0);
                const progress = Math.max(0, Math.min(100, (elapsed / total) * 100));
                progressEl.style.width = `${progress.toFixed(1)}%`;
            }
        }

        function formatRupiahClient(value) {
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(Number(value || 0)));
        }

        function normalizeBidInputValue(inputEl) {
            if (!inputEl) return;

            const raw = String(inputEl.value ?? '').trim();
            if (raw === '') {
                inputEl.setCustomValidity('');
                return;
            }

            const parsed = Number(raw.replace(',', '.'));
            if (!Number.isFinite(parsed)) {
                inputEl.value = '';
                inputEl.setCustomValidity('Masukkan angka rupiah tanpa desimal.');
                return;
            }

            const normalized = Math.floor(parsed);
            inputEl.value = String(normalized);
            inputEl.setCustomValidity('');
        }

        function updateStatusBadge(status) {
            const badge = document.getElementById('lot-status-badge');
            if (!badge) return;

            const colorClass = status === 'aktif'
                ? 'bg-emerald-100 text-emerald-800'
                : status === 'menunggu'
                    ? 'bg-amber-100 text-amber-800'
                    : status === 'terbayar'
                        ? 'bg-cyan-100 text-cyan-800'
                        : 'bg-slate-100 text-slate-700';

            badge.className = 'px-4 py-2 rounded-full text-sm font-bold ' + colorClass;
            badge.textContent = formatLotStatus(status);
        }

        async function pollLotState() {
            try {
                const response = await fetch(stateUrl, {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                });

                if (!response.ok) return;

                const data = await response.json();
                syncServerTime(data.server_time_iso);

                const hargaEl = document.getElementById('harga-tertinggi');
                if (hargaEl) {
                    hargaEl.textContent = formatRupiahClient(data.harga_tertinggi);
                }

                if (data.bid_direction) {
                    bidDirection = data.bid_direction;
                }

                const bidThresholdEl = document.getElementById('bid-threshold');
                if (bidThresholdEl) {
                    bidThresholdEl.textContent = formatRupiahClient(data.bid_threshold ?? data.bid_minimal);
                }

                const bidInput = document.getElementById('jumlah-bid-input');
                if (bidInput) {
                    const threshold = Math.round(Number(data.bid_threshold ?? data.bid_minimal ?? 0));
                    if (bidDirection === 'turun') {
                        const reverseMax = Math.max(1000, Math.floor(threshold / 1000) * 1000);
                        bidInput.min = '1000';
                        bidInput.step = '1000';
                        bidInput.max = String(reverseMax);
                    } else {
                        bidInput.min = String(threshold);
                        bidInput.step = '1000';
                        bidInput.removeAttribute('max');
                    }
                    bidInput.placeholder = new Intl.NumberFormat('id-ID').format(Math.round(Number(threshold || 0)));
                    normalizeBidInputValue(bidInput);
                }

                const buyNowEl = document.getElementById('buy-now-target');
                if (buyNowEl) {
                    buyNowEl.textContent = data.buy_now_enabled && data.buy_now_price
                        ? formatRupiahClient(data.buy_now_price)
                        : '-';
                }

                const antiSnipingEl = document.getElementById('anti-sniping-usage');
                if (antiSnipingEl) {
                    antiSnipingEl.textContent = `${data.anti_sniping_extensions_used}/${data.anti_sniping_max_extensions}`;
                }

                const antiSnipingMaxEndEl = document.getElementById('anti-sniping-max-end');
                if (antiSnipingMaxEndEl && data.waktu_selesai_potensial_iso) {
                    antiSnipingMaxEndEl.textContent = new Date(data.waktu_selesai_potensial_iso).toLocaleString('id-ID', {
                        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
                    });
                }

                updateStatusBadge(data.status);

                const endTimeEl = document.getElementById('lot-end-time');
                if (data.waktu_selesai_iso) {
                    currentEndTimeISO = data.waktu_selesai_iso;
                }

                if (endTimeEl && currentEndTimeISO) {
                    const endDate = new Date(currentEndTimeISO);
                    endTimeEl.textContent = endDate.toLocaleString('id-ID', {
                        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
                    });
                }

                renderCountdown();

                if (data.status !== initialStatus) {
                    window.location.reload();
                }
            } catch (e) {
                // no-op for transient polling failures
            }
        }

        syncServerTime(@js(now()->toISOString()));
        pollLotState();
        renderCountdown();

        const bidInputEl = document.getElementById('jumlah-bid-input');
        if (bidInputEl) {
            const normalizeBidInput = () => normalizeBidInputValue(bidInputEl);
            bidInputEl.addEventListener('input', normalizeBidInput);
            bidInputEl.addEventListener('blur', normalizeBidInput);

            if (bidInputEl.form) {
                bidInputEl.form.addEventListener('submit', normalizeBidInput);
            }
        }

        setInterval(pollLotState, 5000);
        setInterval(renderCountdown, 1000);
    })();
</script>
@endpush
