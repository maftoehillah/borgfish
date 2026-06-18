@extends('layouts.app')
@section('title', $ikan->nama_ikan . ' - Detail Lelang')

@section('content')
@php
    $requestedReturnUrl = request()->query('return_url');
    $safeReturnUrl = safeInternalReturnUrl($requestedReturnUrl, route('penjual.ikans.index'));
    $currentPageUrl = request()->fullUrl();
    $logistikReturnUrl = $currentPageUrl . '#aksi-logistik';
    $logistikAction = session('logistik_action');
    $canEdit = $ikan->status !== 'aktif' && now()->lt($ikan->waktu_mulai);
    $canDelete = $ikan->bids->isEmpty();
    $riwayatBid = $ikan->isLelangTurun()
        ? $ikan->bids->sortByDesc('jumlah_bid')->values()
        : $ikan->bids->sortByDesc('jumlah_bid')->values();
    $buyNowTarget = $ikan->buyNowTarget();
    $reservePriceLabel = $ikan->reserve_price !== null
        ? formatRupiah($ikan->reserve_price)
        : 'Tidak diatur';
@endphp

<style>
    .seller-detail-hero {
        background:
            radial-gradient(circle at 90% 0%, rgba(34, 211, 238, 0.22), transparent 34%),
            radial-gradient(circle at 10% 95%, rgba(16, 185, 129, 0.14), transparent 28%),
            linear-gradient(145deg, #f9fdff 0%, #eef7ff 52%, #f8fcff 100%);
    }

    .seller-surface {
        border: 1px solid rgba(226, 232, 240, 0.95);
        box-shadow: 0 18px 28px -24px rgba(15, 23, 42, 0.6);
    }

    .seller-tile {
        background: rgba(248, 250, 252, 0.88);
        border: 1px solid rgba(226, 232, 240, 0.9);
    }

    .seller-main-media {
        width: 100%;
        aspect-ratio: 1 / 1;
        object-fit: cover;
        object-position: center;
        display: block;
        border-radius: 0.75rem;
        border: 1px solid rgba(226, 232, 240, 0.95);
    }

    .seller-main-media-empty {
        display: flex;
        width: 100%;
        aspect-ratio: 1 / 1;
        align-items: center;
        justify-content: center;
        border-radius: 0.75rem;
        border: 1px dashed rgba(226, 232, 240, 0.95);
        background: #f8fafc;
    }

    @media (min-width: 1280px) {
        .seller-main-media,
        .seller-main-media-empty {
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

    @media (prefers-reduced-motion: reduce) {
        .lot-spec-card summary::after {
            transition: none;
        }
    }

</style>

<section class="seller-detail-hero rounded-3xl border border-cyan-100/70 px-6 py-6 sm:px-8 sm:py-7 mb-8">
    <x-back-button :href="$safeReturnUrl" label="Kembali" class="mb-4" />

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl sm:text-4xl font-black tracking-tight text-slate-900">{{ $ikan->nama_ikan }}</h1>
            <p class="mt-2 text-sm text-slate-600">Lihat detail lot, bid, packing, dan penjemputan.</p>
        </div>
        <div class="flex gap-2">
            <span class="px-3 py-1 rounded-full text-xs font-bold capitalize bg-slate-100 text-slate-700">{{ $ikan->kondisi === 'beku' ? 'Frozen' : $ikan->kondisi }}</span>
                <span id="seller-lot-status-badge" class="px-3 py-1 rounded-full text-xs font-bold {{ $ikan->status === 'aktif' ? 'bg-emerald-100 text-emerald-700' : ($ikan->status === 'terbayar' ? 'bg-cyan-100 text-cyan-700' : 'bg-slate-100 text-slate-700') }}">
                {{ lotStatusLabel($ikan->status) }}
            </span>
        </div>
    </div>

    <div class="mt-5 flex flex-wrap gap-2">
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold {{ $ikan->isLelangTurun() ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">{{ $ikan->isLelangTurun() ? 'Lelang Turun' : 'Lelang Naik' }}</span>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700">{{ $ikan->bids->count() }} bid</span>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-cyan-100 text-cyan-800">Harga {{ formatRupiah($ikan->harga_tertinggi) }}</span>
    </div>

</section>

<div class="grid grid-cols-1 xl:grid-cols-[1.45fr,1fr] gap-8">
    <section class="space-y-6">
        <article id="lot-specs" class="seller-surface bg-white rounded-3xl p-6 scroll-mt-28">
            <div class="mb-5">
                <p class="text-xs font-semibold text-slate-400 mb-2">Foto Ikan</p>
                @if($ikan->foto)
                    <img src="{{ publicStorageUrl($ikan->foto) }}" alt="{{ $ikan->nama_ikan }}" class="seller-main-media" decoding="async" fetchpriority="high">
                @else
                    <div class="seller-main-media-empty">
                        <span class="text-sm font-semibold text-slate-400">Foto belum tersedia</span>
                    </div>
                @endif

                @if($ikan->video)
                    <x-lot-video-modal
                        class="mt-3 rounded-2xl border border-slate-200"
                        :video-url="publicStorageUrl($ikan->video)"
                        :modal-id="'seller-lot-video-' . $ikan->id"
                        title="Video Lot"
                        description="Video dibuka lewat pop-up supaya area detail penjual tetap rapi."
                    />
                @endif
            </div>

            <details class="lot-spec-card mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 sm:mt-0 sm:overflow-visible sm:rounded-none sm:border-0 sm:bg-transparent">
                <summary>
                    <span class="min-w-0">
                        <span class="block text-base font-black text-slate-900">Spesifikasi Lot</span>
                        <span class="mt-0.5 block text-[11px] font-semibold text-slate-500">Ringkas di mobile, tetap lengkap saat dibuka.</span>
                    </span>
                </summary>
                <div class="border-t border-slate-100 px-5 pb-5 pt-4 sm:border-t-0 sm:p-0">
                    <div class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                        <div class="seller-tile rounded-xl px-4 py-3"><p class="text-xs text-slate-400">Berat</p><p class="font-bold text-slate-800">{{ $ikan->berat }} kg</p></div>
                        <div class="seller-tile rounded-xl px-4 py-3"><p class="text-xs text-slate-400">Estimasi Ekor</p><p class="font-bold text-slate-800">{{ $ikan->estimasi_jumlah_ekor ?: '-' }}</p></div>
                        <div class="seller-tile rounded-xl px-4 py-3"><p class="text-xs text-slate-400">Harga Awal</p><p class="font-bold text-slate-800">{{ formatRupiah($ikan->harga_awal) }}</p></div>
                        <div class="seller-tile rounded-xl px-4 py-3"><p class="text-xs text-slate-400">Harga Saat Ini</p><p id="seller-harga-tertinggi-inline" class="font-black text-cyan-700">{{ formatRupiah($ikan->harga_tertinggi) }}</p></div>
                        <div class="seller-tile rounded-xl px-4 py-3"><p class="text-xs text-slate-400">{{ $ikan->isLelangTurun() ? 'Aturan Bid' : 'Min. Increment' }}</p><p class="font-bold text-slate-800">{{ $ikan->isLelangTurun() ? 'Kelipatan Rp 1.000 (min Rp 1.000)' : formatRupiah($ikan->minimal_increment) }}</p></div>
                        <div class="seller-tile rounded-xl px-4 py-3"><p class="text-xs text-slate-400">Jenis Kemasan</p><p class="font-bold text-slate-800 capitalize">{{ $ikan->jenis_kemasan ?: '-' }}</p></div>
                        <div class="seller-tile rounded-xl px-4 py-3"><p class="text-xs text-slate-400">Tanggal Tangkap</p><p class="font-bold text-slate-800">{{ $ikan->tanggal_tangkap?->format('d M Y') ?: '-' }}</p></div>
                        <div class="seller-tile rounded-xl px-4 py-3"><p class="text-xs text-slate-400">Metode Tangkap</p><p class="font-bold text-slate-800">{{ $ikan->metode_tangkap ?: '-' }}</p></div>
                        <div class="seller-tile rounded-xl px-4 py-3"><p class="text-xs text-slate-400">Beli Sekarang</p><p class="font-bold text-slate-800">{{ $buyNowTarget ? formatRupiah($buyNowTarget) : '-' }}</p></div>
                        @if($ikan->isLelangTurun())
                            <div class="seller-tile rounded-xl px-4 py-3"><p class="text-xs text-slate-400">Reserve Price</p><p class="font-bold text-slate-800">{{ $reservePriceLabel }}</p></div>
                        @endif
                        <div class="seller-tile rounded-xl px-4 py-3 sm:col-span-2"><p class="text-xs text-slate-400">Pembayaran</p><p class="font-bold text-slate-800">Pemenang membayar lewat payment gateway setelah lelang selesai</p><p class="mt-1 text-xs text-slate-500">Batas bayar {{ app(\App\Services\SystemSettingService::class)->paymentDeadlineMinutes() }} menit.</p></div>
                        <div class="seller-tile rounded-xl px-4 py-3"><p class="text-xs text-slate-400">State Lelang</p><p class="font-bold text-slate-800">{{ $ikan->auction_state ?? '-' }}</p></div>
                        <div class="seller-tile rounded-xl px-4 py-3"><p class="text-xs text-slate-400">Rank Pemenang Aktif</p><p class="font-bold text-slate-800">{{ $ikan->current_winner_rank ?: '-' }}</p></div>
                        <div class="seller-tile rounded-xl px-4 py-3"><p class="text-xs text-slate-400">Status Kontrol</p><p class="font-bold text-slate-800">{{ $ikan->hard_stop_reason ? str_replace('_', ' ', $ikan->hard_stop_reason) : 'Normal' }}</p></div>
                        <div class="seller-tile rounded-xl px-4 py-3">
                            <p class="text-xs text-slate-400">Perpanjangan Otomatis</p>
                            @if($ikan->anti_sniping_enabled)
                                <p class="font-bold text-slate-800">{{ $ikan->anti_sniping_extensions_used }}/{{ $ikan->anti_sniping_max_extensions }}</p>
                                <p class="mt-1 text-[11px] text-slate-500">Bid di detik akhir bisa memperpanjang lelang sampai batas maksimal.</p>
                            @else
                                <p class="font-bold text-slate-800">OFF</p>
                            @endif
                        </div>
                        <div class="seller-tile rounded-xl px-4 py-3"><p class="text-xs text-slate-400">Mulai</p><p id="seller-lot-start-time" class="font-bold text-slate-800">{{ $ikan->waktu_mulai->format('d M Y H:i') }}</p></div>
                        <div class="seller-tile rounded-xl px-4 py-3"><p class="text-xs text-slate-400">Selesai</p><p id="seller-lot-end-time" class="font-bold text-slate-800">{{ $ikan->waktu_selesai->format('d M Y H:i') }}</p></div>
                        @if($ikan->hard_stop_reason)
                            <div class="seller-tile rounded-xl px-4 py-3 sm:col-span-2 border-rose-200 bg-rose-50"><p class="text-xs text-rose-500">Hard Stop</p><p class="font-semibold text-rose-700">{{ $ikan->hard_stop_reason }}</p></div>
                        @endif
                        <div class="seller-tile rounded-xl px-4 py-3 sm:col-span-2"><p class="text-xs text-slate-400">Stamp Foto</p><p class="font-semibold text-slate-700">{{ $ikan->foto_diambil_pada?->format('d M Y H:i') ?: '-' }}</p></div>
                    </div>
                </div>
            </details>

            <div class="mt-6 pt-6 border-t border-slate-100">
                <h3 class="font-bold text-slate-800 mb-3">Aksi Penjual</h3>
                <div class="flex flex-wrap items-center gap-3">
                    @if($canEdit)
                        <a href="{{ route('penjual.ikans.edit', ['ikan' => $ikan, 'return_url' => $safeReturnUrl]) }}" class="px-4 py-2 rounded-lg bg-amber-100 text-amber-700 hover:bg-amber-200 text-sm font-bold transition">Edit Ikan</a>
                    @else
                        <span class="px-4 py-2 rounded-lg bg-slate-100 text-slate-400 text-sm font-bold" title="Ikan tidak bisa diedit setelah lelang mulai">Edit Terkunci</span>
                    @endif

                    @if($canDelete)
                        <form
                            action="{{ route('penjual.ikans.destroy', $ikan) }}"
                            method="POST"
                            data-confirm-title="Hapus ikan?"
                            data-confirm-message="Lot ini akan dihapus dari daftar Anda. Aksi ini tidak bisa dibatalkan."
                            data-confirm-confirm-label="Hapus Ikan"
                            data-confirm-variant="danger"
                        >
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="return_url" value="{{ $safeReturnUrl }}">
                            <button type="submit" class="px-4 py-2 rounded-lg bg-rose-100 text-rose-700 hover:bg-rose-200 text-sm font-bold transition">Hapus Ikan</button>
                        </form>
                    @else
                        <span class="px-4 py-2 rounded-lg bg-slate-100 text-slate-400 text-sm font-bold" title="Ikan yang sudah memiliki bid tidak bisa dihapus">Hapus Terkunci</span>
                    @endif
                </div>
            </div>

            @if($ikan->transaksi)
                <div class="mt-6 pt-6 border-t border-slate-100">
                    <h3 class="font-bold text-slate-800 mb-3">Info Pembayaran</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                        <div class="seller-tile rounded-xl px-4 py-3"><p class="text-xs text-slate-400">Pemenang</p><p class="font-bold text-slate-800">{{ $ikan->transaksi->pemenang->name }}</p></div>
                        <div class="seller-tile rounded-xl px-4 py-3"><p class="text-xs text-slate-400">Harga Final</p><p class="font-bold text-emerald-700">{{ formatRupiah($ikan->transaksi->harga_final) }}</p></div>
                        <div class="seller-tile rounded-xl px-4 py-3"><p class="text-xs text-slate-400">Order ID</p><p class="break-all font-bold uppercase text-xs text-slate-700">{{ $ikan->transaksi->order_code ?: '-' }}</p></div>
                        <div class="seller-tile rounded-xl px-4 py-3"><p class="text-xs text-slate-400">Penjemputan</p><p class="font-bold text-slate-800">{{ pickupStatusLabel($ikan->transaksi->pickup_status) }}</p></div>
                        <div class="seller-tile rounded-xl px-4 py-3"><p class="text-xs text-slate-400">Progress Transaksi</p><p class="inline-flex px-2 py-0.5 rounded text-xs font-bold {{ $ikan->transaksi->buyerProgressBadgeClass() }}">{{ $ikan->transaksi->buyerProgressLabel() }}</p></div>
                        <div class="seller-tile rounded-xl px-4 py-3"><p class="text-xs text-slate-400">Ringkasan Progress</p><p class="text-xs font-semibold text-slate-700">{{ $ikan->transaksi->buyerProgressDescription() }}</p></div>
                        <div class="seller-tile rounded-xl px-4 py-3"><p class="text-xs text-slate-400">Payment ID Terakhir</p><p class="break-all font-semibold text-slate-700">{{ $ikan->transaksi->latestPayment()?->payment_code ?: '-' }}</p></div>
                        <div class="seller-tile rounded-xl px-4 py-3"><p class="text-xs text-slate-400">Status Bayar</p><p class="inline-flex px-2 py-0.5 rounded text-xs font-bold {{ $ikan->transaksi->status === 'lunas' ? 'bg-emerald-100 text-emerald-700' : ($ikan->transaksi->status === 'menunggu_bayar' ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700') }}">{{ transactionStatusLabel($ikan->transaksi->status) }}</p></div>
                        @if($ikan->transaksi->bayar_sebelum)
                            <div class="seller-tile rounded-xl px-4 py-3"><p class="text-xs text-slate-400">Bayar Sebelum</p><p class="font-semibold text-slate-700">{{ $ikan->transaksi->bayar_sebelum->format('d M Y H:i') }}</p></div>
                        @endif
                        @if($ikan->transaksi->dibayar_pada)
                            <div class="seller-tile rounded-xl px-4 py-3 sm:col-span-2"><p class="text-xs text-slate-400">Dibayar</p><p class="font-semibold text-slate-700">{{ $ikan->transaksi->dibayar_pada->format('d M Y H:i') }}</p></div>
                        @endif
                        @if($ikan->transaksi->buyer_confirm_deadline_at)
                            <div class="seller-tile rounded-xl px-4 py-3"><p class="text-xs text-slate-400">Deadline Konfirmasi Buyer</p><p class="font-semibold text-slate-700">{{ $ikan->transaksi->buyer_confirm_deadline_at->format('d M Y H:i') }}</p></div>
                        @endif
                    </div>
                    <x-fulfillment-timeline :transaksi="$ikan->transaksi" class="mt-4" />
                    <x-fulfillment-photo-grid :transaksi="$ikan->transaksi" title="Foto Packing & Penjemputan" />
                </div>
            @elseif($ikan->isSelesai())
                <div class="mt-6 pt-6 border-t border-slate-100">
                    <h3 class="font-bold text-slate-800 mb-2">Hasil Lelang</h3>
                    <p class="text-sm text-slate-600">Lelang selesai, tetapi tidak ada pemenang karena belum ada bid.</p>
                </div>
            @endif
        </article>
    </section>

    <section class="space-y-6">
        <article class="rounded-3xl bg-gradient-to-br from-cyan-700 via-sky-700 to-blue-800 p-6 text-white shadow-xl">
            <p class="text-sm text-cyan-100">{{ $ikan->isLelangTurun() ? 'Bid Teratas Saat Ini' : 'Harga Tertinggi Saat Ini' }}</p>
            <p id="seller-harga-tertinggi" class="mt-1 text-4xl font-black">{{ formatRupiah($ikan->harga_tertinggi) }}</p>

            @if($ikan->isAktif())
                <div class="mt-4 rounded-xl bg-white/10 p-3">
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-xs text-cyan-100">Berakhir dalam</p>
                        <p class="text-xs font-bold text-cyan-100">Live</p>
                    </div>
                    <p id="seller-countdown-display" class="text-xl font-black text-white">--</p>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/20">
                        <div id="seller-countdown-progress" class="h-full rounded-full bg-cyan-300" style="width: 0%"></div>
                    </div>
                </div>

                @if($ikan->anti_sniping_enabled)
                    <p class="mt-3 text-xs text-cyan-100">Perpanjangan otomatis aktif: <span id="seller-anti-sniping-usage">{{ $ikan->anti_sniping_extensions_used }}/{{ $ikan->anti_sniping_max_extensions }}</span></p>
                    <p class="mt-1 text-xs text-cyan-100">Bid di detik akhir bisa memperpanjang lelang.</p>
                    <p class="mt-1 text-xs text-cyan-100">Batas akhir maksimal: <span id="seller-anti-sniping-max-end" class="font-semibold">{{ $ikan->waktu_selesai->copy()->addSeconds($ikan->anti_sniping_extend_seconds * max(0, $ikan->anti_sniping_max_extensions - $ikan->anti_sniping_extensions_used))->format('d M Y H:i') }}</span></p>
                @endif
            @elseif($ikan->status === 'menunggu')
                <div class="mt-4 rounded-xl bg-white/10 p-3">
                    <p class="text-xs text-cyan-100">Akan mulai pada</p>
                    <p class="mt-1 text-lg font-black text-white">{{ $ikan->waktu_mulai->format('d M Y H:i') }}</p>
                </div>
            @else
                <div class="mt-4 rounded-xl bg-white/10 p-3">
                    <p class="text-xs text-cyan-100">Lelang selesai pada</p>
                    <p class="mt-1 text-lg font-black text-white">{{ $ikan->waktu_selesai->format('d M Y H:i') }}</p>
                </div>
            @endif
        </article>

        <details id="bid-list" class="seller-surface lot-spec-card bg-white rounded-3xl scroll-mt-28">
            <summary>
                <span class="min-w-0">
                    <span class="block text-base font-black text-slate-900">Semua Bid</span>
                    <span class="mt-0.5 block text-[11px] font-semibold text-slate-500">Buka saat perlu melihat seluruh urutan bid pada lot ini.</span>
                </span>
            </summary>
            <div class="border-t border-slate-100 px-6 pb-6 pt-4 sm:border-t-0 sm:p-6">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ $ikan->bids->count() }} bid</span>
                </div>
                @if($ikan->bids->isEmpty())
                    <p class="py-10 text-center text-sm text-slate-400">Belum ada bid masuk.</p>
                @else
                    <div class="max-h-96 divide-y divide-slate-100 overflow-y-auto">
                        @foreach($riwayatBid as $i => $bid)
                            <div class="flex items-center justify-between py-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-800">
                                        {{ $ikan->isAktif() ? maskedBidderName($bid->user->name, $bid->user_id) : $bid->user->name }}
                                    </p>
                                    <p class="text-xs text-slate-400">{{ $bid->created_at->format('d M Y H:i:s') }}</p>
                                </div>
                                <span class="text-sm font-bold {{ $i === 0 ? 'text-amber-600' : 'text-slate-600' }}">{{ $i === 0 ? 'TERBAIK ' : '' }}{{ formatRupiah($bid->jumlah_bid) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </details>

        @if($ikan->transaksi && $ikan->transaksi->isLunas())
            <article id="aksi-logistik" class="seller-surface bg-white rounded-3xl p-6 space-y-4 scroll-mt-24">
                <h2 class="font-bold text-slate-900">Aksi Packing & Penjemputan</h2>

                @if($logistikAction === 'packing_saved')
                    <div class="rounded-xl border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm text-cyan-900">
                        <p class="font-extrabold">Packing tersimpan.</p>
                        <p class="mt-1">Lanjut cek data penjemput saat pembeli mengirimkannya.</p>
                    </div>
                @elseif($logistikAction === 'pickup_arrived')
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                        <p class="font-extrabold">Penjemput berhasil divalidasi.</p>
                        <p class="mt-1">Pembeli bisa lanjut review dan konfirmasi selesai.</p>
                    </div>
                @endif

                @php
                    $isPacked = $ikan->transaksi->packed_at !== null;
                @endphp

                @if(! $isPacked)
                    <form action="{{ route('penjual.ikans.packing', $ikan) }}" method="POST" enctype="multipart/form-data" class="bg-cyan-50 border border-cyan-100 rounded-xl p-4 space-y-3">
                        @csrf
                        <input type="hidden" name="return_url" value="{{ $safeReturnUrl }}">
                        <p class="text-sm font-bold text-cyan-800">Konfirmasi Packing</p>
                        <x-image-upload-preview
                            name="packing_proof"
                            label="Bukti Packing (foto)"
                            :required="! $ikan->transaksi->packing_proof"
                            hint="Upload foto packing yang jelas. Format gambar, maksimal 3 MB."
                            label-class="text-xs text-cyan-700 font-semibold"
                            hint-class="mt-1 text-[11px] text-cyan-700"
                            input-class="w-full mt-1 text-sm"
                            :existing-url="$ikan->transaksi->packing_proof ? publicStorageUrl($ikan->transaksi->packing_proof) : null"
                            existing-label="Bukti packing terbaru"
                        />
                        <div>
                            <label class="text-xs text-cyan-700 font-semibold">Lokasi Packing</label>
                            <input type="text" name="packing_location" required value="{{ old('packing_location', $ikan->transaksi->packing_location) }}" class="w-full mt-1 border border-cyan-200 rounded-lg px-3 py-2 text-sm bg-white" placeholder="contoh: Gudang Muara Baru">
                        </div>
                        <div>
                            <label class="text-xs text-cyan-700 font-semibold">Jam Packing</label>
                            <input type="datetime-local" name="packing_recorded_at" value="{{ old('packing_recorded_at', now()->format('Y-m-d\TH:i')) }}" class="w-full mt-1 border border-cyan-200 rounded-lg px-3 py-2 text-sm bg-white">
                        </div>
                        <div>
                            <label class="text-xs text-cyan-700 font-semibold">Deskripsi Opsional</label>
                            <textarea name="packing_description" rows="2" class="w-full mt-1 border border-cyan-200 rounded-lg px-3 py-2 text-sm bg-white" placeholder="Catatan kondisi packing">{{ old('packing_description', $ikan->transaksi->packing_description) }}</textarea>
                        </div>
                        <button type="submit" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-bold text-white hover:bg-cyan-800">
                            Simpan Packing
                        </button>
                    </form>
                @else
                    <div class="rounded-xl border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm text-cyan-900">
                        <p class="font-extrabold">Packing sudah dikonfirmasi.</p>
                        <p class="mt-1">Waktu packing: {{ $ikan->transaksi->packed_at?->format('d M Y H:i') ?? '-' }}</p>
                        @if($ikan->transaksi->packing_proof)
                            <a href="{{ publicStorageUrl($ikan->transaksi->packing_proof) }}" target="_blank" rel="noopener noreferrer" class="inline-flex mt-2 text-xs font-semibold text-cyan-700 hover:underline">
                                Lihat bukti packing
                            </a>
                        @endif
                    </div>

                    @if(! $ikan->transaksi->buyer_pickup_submitted_at)
                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            <p class="font-extrabold">Menunggu data penjemput dari pembeli.</p>
                            <p class="mt-1">Pembeli harus mengisi nama sopir, plat nomor, foto sopir, dan foto kendaraan penjemput sebelum Anda memvalidasi kedatangan.</p>
                        </div>
                    @elseif($ikan->transaksi->pickup_status !== 'pickup_arrived' && $ikan->transaksi->pickup_status !== 'completed')
                        <div class="rounded-xl border border-indigo-100 bg-indigo-50 px-4 py-3 text-sm text-indigo-900">
                            <p class="font-extrabold">Data penjemput pembeli</p>
                            <p class="mt-1">Sopir: {{ $ikan->transaksi->buyer_pickup_name ?: '-' }}</p>
                            <p class="mt-1 break-all">Plat: {{ $ikan->transaksi->buyer_pickup_plate_number ?: '-' }}</p>
                            @if($ikan->transaksi->buyer_pickup_photo)
                                <a href="{{ publicStorageUrl($ikan->transaksi->buyer_pickup_photo) }}" target="_blank" rel="noopener noreferrer" class="inline-flex mt-2 text-xs font-semibold text-indigo-700 hover:underline">Lihat foto sopir dari pembeli</a>
                            @endif
                            @if($ikan->transaksi->buyer_pickup_vehicle_photo)
                                <a href="{{ publicStorageUrl($ikan->transaksi->buyer_pickup_vehicle_photo) }}" target="_blank" rel="noopener noreferrer" class="inline-flex mt-2 ml-0 sm:ml-3 text-xs font-semibold text-indigo-700 hover:underline">Lihat foto kendaraan dari pembeli</a>
                            @endif
                        </div>

                        <form action="{{ route('penjual.ikans.pickup_arrived', $ikan) }}" method="POST" enctype="multipart/form-data" class="bg-emerald-50 border border-emerald-100 rounded-xl p-4 space-y-3">
                            @csrf
                            <input type="hidden" name="return_url" value="{{ $safeReturnUrl }}">
                            <p class="text-sm font-bold text-emerald-800">Penjemput Datang</p>
                            <input type="text" name="seller_pickup_driver_name" required value="{{ old('seller_pickup_driver_name', $ikan->transaksi->buyer_pickup_name) }}" autocomplete="name" class="w-full border border-emerald-200 rounded-lg px-3 py-3 text-base bg-white" placeholder="Nama sopir">
                            <input type="text" name="seller_pickup_plate_number" required value="{{ old('seller_pickup_plate_number', $ikan->transaksi->buyer_pickup_plate_number) }}" inputmode="text" autocapitalize="characters" class="w-full border border-emerald-200 rounded-lg px-3 py-3 text-base bg-white" placeholder="Plat nomor">
                            <x-image-upload-preview
                                name="seller_pickup_driver_photo"
                                label="Foto Sopir"
                                required
                                hint="Upload foto sopir saat penjemput datang. Format gambar, maksimal 3 MB."
                                label-class="text-xs text-emerald-700 font-semibold"
                                hint-class="mt-1 text-[11px] text-emerald-700"
                                input-class="w-full mt-1 min-h-[46px] text-base"
                            />
                            <x-image-upload-preview
                                name="seller_pickup_vehicle_photo"
                                label="Foto Kendaraan"
                                required
                                hint="Upload foto kendaraan saat penjemput datang. Pastikan plat nomor terlihat jika memungkinkan. Format gambar, maksimal 3 MB."
                                label-class="text-xs text-emerald-700 font-semibold"
                                hint-class="mt-1 text-[11px] text-emerald-700"
                                input-class="w-full mt-1 min-h-[46px] text-base"
                            />
                            <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-3 text-[15px] font-bold text-white hover:bg-emerald-700">
                                Validasi Penjemput Datang
                            </button>
                        </form>
                    @else
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            <p class="font-extrabold">Penjemput sudah divalidasi.</p>
                            <p class="mt-1">Status: {{ pickupStatusLabel($ikan->transaksi->pickup_status) }}</p>
                        </div>
                    @endif
                @endif
            </article>
        @endif
    </section>
</div>

@endsection

@push('scripts')
<script>
    (() => {
        const stateUrl = @js(route('ikans.state', $ikan));
        let initialStatus = @js($ikan->status);
        let currentEndTimeISO = @js($ikan->waktu_selesai?->toISOString());
        const startTimeISO = @js($ikan->waktu_mulai?->toISOString());
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

        function formatRupiahClient(value) {
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(Number(value || 0)));
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

        function updateStatusBadge(status) {
            const badge = document.getElementById('seller-lot-status-badge');
            if (!badge) return;

            const colorClass = status === 'aktif'
                ? 'bg-emerald-100 text-emerald-700'
                : status === 'menunggu'
                    ? 'bg-amber-100 text-amber-700'
                    : status === 'terbayar'
                        ? 'bg-cyan-100 text-cyan-700'
                        : 'bg-slate-100 text-slate-700';

            badge.className = 'px-3 py-1 rounded-full text-xs font-bold ' + colorClass;
            badge.textContent = formatLotStatus(status);
        }

        function renderCountdown() {
            const countdownEl = document.getElementById('seller-countdown-display');
            const progressEl = document.getElementById('seller-countdown-progress');
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

        async function pollLotState() {
            try {
                const response = await fetch(stateUrl, {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                });

                if (!response.ok) return;

                const data = await response.json();
                syncServerTime(data.server_time_iso);

                const livePriceEl = document.getElementById('seller-harga-tertinggi');
                if (livePriceEl) {
                    livePriceEl.textContent = formatRupiahClient(data.harga_tertinggi);
                }

                const inlinePriceEl = document.getElementById('seller-harga-tertinggi-inline');
                if (inlinePriceEl) {
                    inlinePriceEl.textContent = formatRupiahClient(data.harga_tertinggi);
                }

                const antiSnipingEl = document.getElementById('seller-anti-sniping-usage');
                if (antiSnipingEl) {
                    antiSnipingEl.textContent = `${data.anti_sniping_extensions_used}/${data.anti_sniping_max_extensions}`;
                }

                const antiSnipingMaxEndEl = document.getElementById('seller-anti-sniping-max-end');
                if (antiSnipingMaxEndEl && data.waktu_selesai_potensial_iso) {
                    antiSnipingMaxEndEl.textContent = new Date(data.waktu_selesai_potensial_iso).toLocaleString('id-ID', {
                        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
                    });
                }

                if (data.waktu_selesai_iso) {
                    currentEndTimeISO = data.waktu_selesai_iso;
                }

                const endTimeEl = document.getElementById('seller-lot-end-time');
                if (endTimeEl && currentEndTimeISO) {
                    endTimeEl.textContent = new Date(currentEndTimeISO).toLocaleString('id-ID', {
                        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
                    });
                }

                const startTimeEl = document.getElementById('seller-lot-start-time');
                if (startTimeEl && startTimeISO) {
                    startTimeEl.textContent = new Date(startTimeISO).toLocaleString('id-ID', {
                        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
                    });
                }

                updateStatusBadge(data.status);
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

        setInterval(pollLotState, 5000);
        setInterval(renderCountdown, 1000);
    })();
</script>
@endpush
