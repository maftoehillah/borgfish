@extends('layouts.app')
@section('title', 'Pembayaran Lelang')

@section('content')
@php
    $requestedReturnUrl = request()->query('return_url');
    $safeReturnUrl = safeInternalReturnUrl(
        $requestedReturnUrl,
        route('ikans.show', ['ikan' => $transaksi->ikan, 'return_url' => route('ikans.index')])
    );

    $latestStatus = (string) ($latestPayment?->status_code ?? $transaksi->payment_status ?? $transaksi->status);
    $paymentDeadlineMinutes = app(\App\Services\SystemSettingService::class)->paymentDeadlineMinutes();
    $canContinueLatestInvoice = $latestPayment
        && $latestPayment->checkout_url
        && $latestPayment->status_code === 'pending'
        && (! $latestPayment->checkout_expires_at || now()->lt($latestPayment->checkout_expires_at));

    $initialPaymentMethod = (string) ($defaultPaymentMethod ?? array_key_first($paymentMethods) ?? '');

    if (! array_key_exists($initialPaymentMethod, $paymentMethods)) {
        $initialPaymentMethod = (string) (array_key_first($paymentMethods) ?? '');
    }

    $initialPaymentMethodName = $paymentMethods[$initialPaymentMethod] ?? 'Pilih metode pembayaran';

    if ($transaksi->isLunas()) {
        $paymentStatusCard = [
            'title' => 'Pembayaran Berhasil',
            'description' => 'Pembayaran sudah masuk. Lanjutkan dari halaman aktivitas.',
            'badge' => 'LUNAS',
            'class' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
            'badgeClass' => 'bg-emerald-100 text-emerald-700',
        ];
    } elseif (in_array((string) $transaksi->status, ['kadaluarsa'], true) || $latestStatus === 'expired') {
        $paymentStatusCard = [
            'title' => 'Invoice Kadaluarsa',
            'description' => 'Batas waktu pembayaran sudah habis.',
            'badge' => 'KADALUARSA',
            'class' => 'border-rose-200 bg-rose-50 text-rose-800',
            'badgeClass' => 'bg-rose-100 text-rose-700',
        ];
    } elseif (in_array($latestStatus, ['failed', 'cancelled'], true) || (string) $transaksi->status === 'gagal') {
        $paymentStatusCard = [
            'title' => 'Pembayaran Belum Berhasil',
            'description' => 'Invoice terakhir belum berhasil. Anda masih bisa membuat invoice baru jika waktunya masih ada.',
            'badge' => 'PERLU DICOBA LAGI',
            'class' => 'border-amber-200 bg-amber-50 text-amber-800',
            'badgeClass' => 'bg-amber-100 text-amber-700',
        ];
    } else {
        $paymentStatusCard = [
            'title' => 'Menunggu Pembayaran',
            'description' => $canContinueLatestInvoice
                ? 'Invoice terakhir masih aktif. Lanjutkan pembayaran atau buat invoice baru.'
                : 'Pilih metode pembayaran sebelum tenggat berakhir.',
            'badge' => 'MENUNGGU',
            'class' => 'border-cyan-200 bg-cyan-50 text-cyan-800',
            'badgeClass' => 'bg-cyan-100 text-cyan-700',
        ];
    }
@endphp
<style>
    .payment-hero {
        background:
            radial-gradient(circle at 10% 12%, rgba(59, 130, 246, 0.16), transparent 34%),
            radial-gradient(circle at 88% 4%, rgba(34, 211, 238, 0.14), transparent 36%),
            linear-gradient(145deg, #f8fcff 0%, #eef6ff 52%, #f7fcff 100%);
    }

    .payment-surface {
        border: 1px solid rgba(226, 232, 240, 0.95);
        box-shadow: 0 16px 24px -22px rgba(15, 23, 42, 0.6);
    }

    .payment-lot-summary {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .payment-lot-media-wrap {
        width: 100%;
        flex-shrink: 0;
    }

    .payment-lot-media {
        width: 100%;
        height: 12rem;
        object-fit: cover;
        object-position: center;
        border-radius: 1rem;
        display: block;
    }

    .payment-empty-media {
        display: flex;
        width: 100%;
        min-height: 12rem;
        align-items: center;
        justify-content: center;
        border-radius: 1rem;
        background: #ecfeff;
    }

    .payment-lot-copy {
        min-width: 0;
    }

    .payment-accordion {
        overflow: hidden;
        border: 1px solid rgba(226, 232, 240, 0.95);
        border-radius: 1rem;
        background: rgba(248, 250, 252, 0.88);
    }

    .payment-accordion summary {
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
        color: #0f172a;
    }

    .payment-accordion summary::-webkit-details-marker {
        display: none;
    }

    .payment-method-option {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        min-height: 3.5rem;
        padding: 1rem;
        border: 1px solid rgba(226, 232, 240, 0.95);
        border-radius: 1rem;
        background: rgba(248, 250, 252, 0.82);
        cursor: pointer;
        transition: border-color 0.14s ease, background 0.14s ease;
    }

    .payment-method-option:hover {
        border-color: rgba(34, 211, 238, 0.42);
        background: #fff;
    }

    .payment-method-option input {
        margin-top: 0.125rem;
        flex-shrink: 0;
    }

    .payment-method-trigger {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        width: 100%;
        padding: 1rem;
        border: 1px solid rgba(186, 230, 253, 0.95);
        border-radius: 1rem;
        background:
            radial-gradient(circle at top right, rgba(103, 232, 249, 0.18), transparent 42%),
            linear-gradient(145deg, #f8fdff 0%, #eef9ff 100%);
        box-shadow: 0 14px 24px -22px rgba(8, 47, 73, 0.55);
        text-align: left;
        transition: border-color 0.14s ease, transform 0.14s ease, box-shadow 0.14s ease;
    }

    .payment-method-trigger:hover {
        border-color: rgba(34, 211, 238, 0.55);
        transform: translateY(-1px);
        box-shadow: 0 18px 28px -22px rgba(8, 47, 73, 0.62);
    }

    .payment-method-trigger-copy {
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
    }

    .payment-method-trigger-label {
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: #0891b2;
    }

    .payment-method-trigger-value {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 0.95rem;
        font-weight: 900;
        color: #0f172a;
    }

    .payment-method-trigger-hint {
        font-size: 0.75rem;
        font-weight: 600;
        color: #64748b;
    }

    .payment-method-trigger-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.92);
        border: 1px solid rgba(186, 230, 253, 0.95);
        color: #0f172a;
        flex-shrink: 0;
    }

    .payment-method-sheet {
        position: fixed;
        inset: 0;
        z-index: 90;
        display: flex;
        align-items: flex-end;
        justify-content: center;
        padding: 1rem;
        background: rgba(15, 23, 42, 0.5);
        backdrop-filter: blur(4px);
    }

    .payment-method-sheet[hidden] {
        display: none !important;
    }

    .payment-method-sheet-panel {
        width: min(100%, 32rem);
        max-height: min(80vh, 42rem);
        overflow: hidden;
        border: 1px solid rgba(226, 232, 240, 0.95);
        border-radius: 1.75rem;
        background: #fff;
        box-shadow: 0 28px 40px -24px rgba(15, 23, 42, 0.45);
    }

    .payment-method-sheet-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.75rem 1rem 1rem;
        border-bottom: 1px solid rgba(226, 232, 240, 0.9);
        background:
            radial-gradient(circle at top right, rgba(103, 232, 249, 0.14), transparent 38%),
            linear-gradient(180deg, #f8fcff 0%, #f8fbff 100%);
    }

    .payment-method-sheet-head-copy {
        min-width: 0;
    }

    .payment-method-sheet-handle {
        width: 3rem;
        height: 0.3rem;
        margin: 0 auto 0.9rem;
        border-radius: 999px;
        background: rgba(148, 163, 184, 0.45);
    }

    .payment-method-sheet-title {
        font-size: 1rem;
        font-weight: 900;
        color: #0f172a;
    }

    .payment-method-sheet-subtitle {
        margin-top: 0.2rem;
        font-size: 0.78rem;
        font-weight: 600;
        color: #64748b;
    }

    .payment-method-sheet-close {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.4rem;
        height: 2.4rem;
        border: 1px solid rgba(226, 232, 240, 0.95);
        border-radius: 999px;
        background: #fff;
        color: #334155;
        font-size: 1.2rem;
        line-height: 1;
        flex-shrink: 0;
    }

    .payment-method-sheet-list {
        max-height: min(60vh, 32rem);
        overflow-y: auto;
        padding: 1rem;
        display: grid;
        gap: 0.75rem;
    }

    .payment-method-sheet-option {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
        width: 100%;
        min-height: 4rem;
        padding: 1rem;
        border: 1px solid rgba(226, 232, 240, 0.95);
        border-radius: 1rem;
        background: rgba(248, 250, 252, 0.84);
        text-align: left;
        transition: border-color 0.14s ease, background 0.14s ease, box-shadow 0.14s ease;
    }

    .payment-method-sheet-option:hover {
        border-color: rgba(34, 211, 238, 0.5);
        background: #fff;
    }

    .payment-method-sheet-option.is-active {
        border-color: rgba(8, 145, 178, 0.46);
        background:
            radial-gradient(circle at top right, rgba(103, 232, 249, 0.14), transparent 42%),
            linear-gradient(145deg, #f8fdff 0%, #eef9ff 100%);
        box-shadow: 0 16px 26px -24px rgba(8, 47, 73, 0.62);
    }

    .payment-method-sheet-option-copy {
        min-width: 0;
        display: flex;
        align-items: flex-start;
        gap: 0.85rem;
    }

    .payment-method-sheet-indicator {
        display: inline-flex;
        width: 1.15rem;
        height: 1.15rem;
        margin-top: 0.15rem;
        border: 1.8px solid #94a3b8;
        border-radius: 999px;
        background: #fff;
        flex-shrink: 0;
        transition: border-color 0.14s ease, box-shadow 0.14s ease, background 0.14s ease;
    }

    .payment-method-sheet-option.is-active .payment-method-sheet-indicator {
        border-color: #0891b2;
        background: #0891b2;
        box-shadow: inset 0 0 0 3px #fff;
    }

    .payment-method-sheet-name {
        display: block;
        font-size: 0.94rem;
        font-weight: 900;
        color: #0f172a;
    }

    .payment-method-sheet-note {
        display: block;
        margin-top: 0.2rem;
        font-size: 0.74rem;
        font-weight: 600;
        color: #64748b;
    }

    .payment-method-sheet-status {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 4.5rem;
        padding: 0.38rem 0.7rem;
        border-radius: 999px;
        background: rgba(8, 145, 178, 0.1);
        color: #0e7490;
        font-size: 0.68rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        flex-shrink: 0;
    }

    @media (max-width: 639px) {
        .payment-surface {
            border-radius: 1.5rem;
        }

        .payment-lot-media {
            height: 10rem;
        }

        .payment-empty-media {
            min-height: 10rem;
        }
    }

    @media (min-width: 640px) {
        .payment-lot-summary {
            flex-direction: row;
            align-items: center;
        }

        .payment-lot-media-wrap {
            width: 7rem;
            max-width: 7rem;
        }

        .payment-lot-media {
            height: 7rem;
        }

        .payment-empty-media {
            min-height: 7rem;
        }
    }
</style>

<div class="max-w-5xl mx-auto">
    <section class="payment-hero rounded-3xl border border-blue-100/70 px-4 py-5 sm:px-8 sm:py-7 mb-6">
        <x-back-button :href="$safeReturnUrl" label="Kembali" />
        <h1 class="text-2xl sm:text-4xl font-black tracking-tight text-slate-900">Selesaikan Pembayaran</h1>
        <p class="mt-2 text-sm sm:text-base text-slate-600">Selesaikan pembayaran dalam {{ $paymentDeadlineMinutes }} menit.</p>

    </section>

    @if($transaksi->isLunas())
        <div class="payment-surface bg-green-50 border-green-200 rounded-3xl p-8 text-center">
            <span class="inline-flex rounded-full px-3 py-1 text-xs font-extrabold {{ $paymentStatusCard['badgeClass'] }}">{{ $paymentStatusCard['badge'] }}</span>
            <h2 class="mt-3 text-2xl font-bold text-green-800">{{ $paymentStatusCard['title'] }}</h2>
            <p class="text-green-600 mt-2">
                {{ $paymentStatusCard['description'] }}
            </p>
            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-center">
                <a href="{{ route('pembeli.aktivitas.detail', ['ikan' => $transaksi->ikan, 'return_url' => $safeReturnUrl]) }}" class="inline-flex min-h-[48px] items-center justify-center rounded-xl bg-green-600 px-6 py-3 font-bold text-white">
                    Lihat Status Pesanan
                </a>
                <x-secondary-action-link :href="route('ikans.show', ['ikan' => $transaksi->ikan, 'return_url' => $safeReturnUrl])" class="px-6 py-3 text-slate-700">
                    Detail Lot
                </x-secondary-action-link>
            </div>
        </div>
    @elseif(in_array($transaksi->status, ['gagal', 'kadaluarsa'], true))
        <div class="payment-surface bg-red-50 border-red-200 rounded-3xl p-8 text-center">
            <span class="inline-flex rounded-full px-3 py-1 text-xs font-extrabold {{ $paymentStatusCard['badgeClass'] }}">{{ $paymentStatusCard['badge'] }}</span>
            <h2 class="mt-3 text-2xl font-bold text-red-800">{{ $paymentStatusCard['title'] }}</h2>
            <p class="text-red-600 mt-2">{{ $paymentStatusCard['description'] }}</p>
            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-center">
                <x-secondary-action-link :href="route('pembayaran.show', ['transaksi' => $transaksi, 'return_url' => $safeReturnUrl])" class="px-6 py-3 text-slate-700">
                    Cek Status Lagi
                </x-secondary-action-link>
                <a href="{{ route('pembeli.aktivitas', ['fokus' => 'tagihan_berjalan']) }}" class="inline-flex min-h-[48px] items-center justify-center rounded-xl bg-slate-900 px-6 py-3 font-bold text-white">
                    Buka Aktivitas
                </a>
            </div>
        </div>
    @else
        <div class="grid gap-5 xl:grid-cols-[1.15fr,0.85fr]">
            <div class="space-y-5">
                <div id="payment-status-card" class="payment-surface rounded-3xl p-5 sm:p-6 {{ $paymentStatusCard['class'] }} scroll-mt-28">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <span class="inline-flex items-center rounded-full px-3 py-1.5 text-[11px] font-extrabold {{ $paymentStatusCard['badgeClass'] }}">{{ $paymentStatusCard['badge'] }}</span>
                    <h2 class="mt-3 text-xl font-black">{{ $paymentStatusCard['title'] }}</h2>
                    <p class="mt-1 text-sm opacity-90">{{ $paymentStatusCard['description'] }}</p>
                    @if($latestPayment)
                        <p class="mt-2 break-all text-xs font-semibold opacity-80">Invoice terakhir: {{ $latestPayment->payment_code }} - {{ paymentStatusLabel($latestStatus) }}</p>
                    @endif
                </div>
                <div class="flex flex-col gap-2 sm:min-w-[190px]">
                    @if($canContinueLatestInvoice)
                        <a href="{{ $latestPayment->checkout_url }}" class="inline-flex min-h-[48px] items-center justify-center rounded-xl bg-slate-900 px-4 py-3 text-sm font-extrabold text-white">
                            Lanjutkan Invoice
                        </a>
                    @endif
                    <button
                        type="button"
                        id="btn-refresh-payment"
                        data-refresh-url="{{ route('pembayaran.refresh', $transaksi) }}"
                        class="inline-flex min-h-[48px] items-center justify-center rounded-xl border border-white/70 bg-white/80 px-4 py-3 text-sm font-extrabold text-slate-700"
                    >
                        Sinkronkan Status
                    </button>
                </div>
            </div>
                </div>

                <div class="payment-surface bg-white rounded-3xl p-5 sm:p-6">
            <div class="payment-lot-summary">
                @if($transaksi->ikan->foto)
                    <div class="payment-lot-media-wrap">
                        <img src="{{ publicStorageUrl($transaksi->ikan->foto) }}" class="payment-lot-media" alt="{{ $transaksi->ikan->nama_ikan }}" loading="lazy" decoding="async">
                    </div>
                @else
                    <div class="payment-lot-media-wrap">
                        <div class="payment-empty-media">
                            <span class="text-xs font-semibold text-cyan-700">No Image</span>
                        </div>
                    </div>
                @endif
                <div class="payment-lot-copy">
                    <h2 class="text-xl font-bold text-slate-900">{{ $transaksi->ikan->nama_ikan }}</h2>
                    <p class="text-slate-500 text-sm">{{ $transaksi->ikan->berat }} kg &bull; {{ $transaksi->ikan->kondisi }}</p>
                    <div class="mt-2">
                        <p class="text-xs text-slate-400">Subtotal Lelang</p>
                        <p class="text-base font-bold text-slate-700">{{ formatRupiah($transaksi->harga_final) }}</p>
                        <p class="text-xs text-slate-400 mt-1">Total yang harus dibayar</p>
                        <p class="text-3xl font-black text-cyan-700">{{ formatRupiah($transaksi->totalTagihan()) }}</p>
                    </div>
                </div>
            </div>

            @if($transaksi->bayar_sebelum)
                <div class="mt-4 rounded-xl border border-amber-100 bg-amber-50 p-4" x-data="countdown('{{ $transaksi->bayar_sebelum->toISOString() }}')" x-init="start()">
                    <div class="flex items-center justify-between text-xs text-amber-700 mb-2">
                        <p>Bayar sebelum</p>
                        <p class="font-bold">{{ humanDeadlineLabel($transaksi->bayar_sebelum) }}</p>
                    </div>
                    <p x-text="display" class="font-bold text-amber-700"></p>
                    <div class="mt-2 h-1.5 rounded-full bg-white overflow-hidden">
                        <div class="h-full rounded-full bg-amber-500" :style="`width: ${progress}%`"></div>
                    </div>
                </div>
            @endif

            <details class="payment-accordion mt-4">
                <summary>
                    <span>Detail pesanan &amp; invoice</span>
                    <span class="text-[11px] font-bold text-slate-500">{{ paymentStatusLabel($transaksi->payment_status ?: null) }}</span>
                </summary>
                <div class="border-t border-slate-100 px-4 py-4">
                    <div class="grid grid-cols-1 gap-3 text-xs sm:grid-cols-2">
                        <div class="rounded-xl border border-slate-100 bg-white p-3">
                            <p class="text-slate-400">Order ID</p>
                            <p class="break-all font-bold uppercase text-slate-800">{{ $transaksi->order_code ?: '-' }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-white p-3">
                            <p class="text-slate-400">Status Payment</p>
                            <p class="font-bold text-slate-800">{{ paymentStatusLabel($transaksi->payment_status ?: null) }}</p>
                        </div>
                    </div>
                </div>
            </details>
                </div>
            </div>

            <aside class="h-fit space-y-5 xl:sticky xl:top-24">
                <div id="payment-method-card" class="payment-surface bg-white rounded-3xl p-5 sm:p-6 scroll-mt-28">
                    <h3 class="font-bold text-slate-900 mb-2">Metode Pembayaran</h3>
                    <p class="text-sm text-slate-500 mb-4">Pilih metode yang ingin digunakan.</p>
                    <input type="hidden" id="selected-payment-method" value="{{ $initialPaymentMethod }}">

                    <button
                        type="button"
                        id="payment-method-open"
                        class="payment-method-trigger mb-4 sm:hidden"
                        aria-haspopup="dialog"
                        aria-controls="payment-method-sheet"
                        aria-expanded="false"
                    >
                        <span class="payment-method-trigger-copy">
                            <span class="payment-method-trigger-label">Metode Terpilih</span>
                            <span id="payment-method-mobile-selected" class="payment-method-trigger-value">{{ $initialPaymentMethodName }}</span>
                            <span class="payment-method-trigger-hint">Tap untuk memilih metode tanpa scroll panjang.</span>
                        </span>
                        <span class="payment-method-trigger-icon" aria-hidden="true">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="h-4 w-4">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M7 10l5 5 5-5" />
                            </svg>
                        </span>
                    </button>

                    <div class="hidden grid-cols-1 gap-3 mb-4 sm:grid">
                        @foreach($paymentMethods as $code => $name)
                            <label class="payment-method-option">
                                <input
                                    type="radio"
                                    name="payment_method"
                                    value="{{ $code }}"
                                    data-payment-method-radio
                                    data-method-label="{{ $name }}"
                                    class="h-4 w-4 text-cyan-600 focus:ring-cyan-500"
                                    @checked((string) $code === $initialPaymentMethod)
                                >
                                <span class="flex flex-col gap-1">
                                    <span class="text-sm font-extrabold text-slate-800">{{ $name }}</span>
                                    <span class="text-xs font-semibold text-slate-500">Buat invoice baru dengan kanal pembayaran ini.</span>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <div id="payment-method-sheet" class="payment-method-sheet sm:hidden" hidden aria-hidden="true">
                        <div class="payment-method-sheet-panel" role="dialog" aria-modal="true" aria-labelledby="payment-method-sheet-title" data-payment-method-panel>
                            <div class="payment-method-sheet-head">
                                <div class="payment-method-sheet-head-copy">
                                    <div class="payment-method-sheet-handle" aria-hidden="true"></div>
                                    <h4 id="payment-method-sheet-title" class="payment-method-sheet-title">Pilih Metode Pembayaran</h4>
                                    <p class="payment-method-sheet-subtitle">Kami buat ringkas supaya halaman utama tetap nyaman dilihat.</p>
                                </div>
                                <button type="button" class="payment-method-sheet-close" data-payment-method-close aria-label="Tutup pilihan metode pembayaran">&times;</button>
                            </div>
                            <div class="payment-method-sheet-list">
                                @foreach($paymentMethods as $code => $name)
                                    <button
                                        type="button"
                                        value="{{ $code }}"
                                        data-payment-method-option
                                        data-method-label="{{ $name }}"
                                        class="payment-method-sheet-option {{ (string) $code === $initialPaymentMethod ? 'is-active' : '' }}"
                                        aria-pressed="{{ (string) $code === $initialPaymentMethod ? 'true' : 'false' }}"
                                    >
                                        <span class="payment-method-sheet-option-copy">
                                            <span class="payment-method-sheet-indicator" aria-hidden="true"></span>
                                            <span>
                                                <span class="payment-method-sheet-name">{{ $name }}</span>
                                                <span class="payment-method-sheet-note">Buat invoice baru dengan kanal pembayaran ini.</span>
                                            </span>
                                        </span>
                                        <span class="payment-method-sheet-status">{{ (string) $code === $initialPaymentMethod ? 'Dipilih' : 'Pilih' }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    @if($latestPayment)
                        <details class="mb-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600">
                            <summary class="cursor-pointer list-none font-bold text-slate-800">Invoice terakhir</summary>
                            <p class="mt-2 break-all">Kode: {{ $latestPayment->payment_code }}</p>
                            <p class="mt-1">Status: {{ paymentStatusLabel($latestPayment->status_code ?? $latestPayment->status) }}</p>
                        </details>
                    @endif
                    <button id="btn-bayar" class="w-full min-h-[52px] rounded-xl bg-slate-900 py-4 text-lg font-black text-white transition hover:bg-slate-800">
                        Buat Invoice dan Bayar Sekarang
                    </button>
                    <p class="mt-3 text-center text-xs text-slate-400">Diproses melalui TriPay.</p>
                </div>

                <details id="payment-info-card" class="payment-surface payment-accordion bg-slate-50 scroll-mt-28">
                    <summary>
                        <span>Info Pembayaran</span>
                        <span class="text-[11px] font-bold text-slate-500">Panduan &amp; bantuan</span>
                    </summary>
                    <div class="border-t border-slate-100 px-5 py-5 sm:px-6">
                        <div class="space-y-2 text-sm text-slate-600">
                            <p>Pembayaran diterima oleh Borgfish untuk transaksi ini.</p>
                            <p>Dana penjual diproses setelah transaksi selesai.</p>
                            <p>Jika ada kendala, hubungi admin.</p>
                        </div>
                        <div class="mt-4 grid grid-cols-1 gap-3 text-sm font-bold sm:grid-cols-2">
                            <a href="{{ route('pages.payment_policy') }}" class="inline-flex min-h-[48px] items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-700 hover:border-cyan-300 hover:text-cyan-700">
                                Lihat Kebijakan Pembayaran
                            </a>
                            <a href="{{ route('pages.contact') }}" class="inline-flex min-h-[48px] items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-700 hover:border-cyan-300 hover:text-cyan-700">
                                Hubungi Admin
                            </a>
                        </div>
                    </div>
                </details>
            </aside>
        </div>
    @endif
</div>

<script>
    const showPaymentToast = (message) => {
        const fallbackMessage = message || 'Terjadi kendala pembayaran. Silakan coba lagi.';

        if (window.BorgfishToast?.error) {
            window.BorgfishToast.error(fallbackMessage);
        }
    };

    (function () {
        const paymentMethodInput = document.getElementById('selected-payment-method');
        const paymentMethodOpen = document.getElementById('payment-method-open');
        const paymentMethodSheet = document.getElementById('payment-method-sheet');
        const paymentMethodPanel = paymentMethodSheet?.querySelector('[data-payment-method-panel]');
        const paymentMethodLabel = document.getElementById('payment-method-mobile-selected');
        const paymentMethodRadios = Array.from(document.querySelectorAll('[data-payment-method-radio]'));
        const paymentMethodOptions = Array.from(document.querySelectorAll('[data-payment-method-option]'));
        const paymentMethodCloseButtons = Array.from(document.querySelectorAll('[data-payment-method-close]'));

        if (!paymentMethodInput) {
            return;
        }

        const methodLabels = new Map();

        paymentMethodRadios.forEach((radio) => {
            methodLabels.set(radio.value, radio.dataset.methodLabel || radio.value);
        });

        paymentMethodOptions.forEach((option) => {
            methodLabels.set(option.value, option.dataset.methodLabel || option.value);
        });

        const syncPaymentMethod = (value) => {
            if (!value) {
                return;
            }

            paymentMethodInput.value = value;

            paymentMethodRadios.forEach((radio) => {
                radio.checked = radio.value === value;
            });

            paymentMethodOptions.forEach((option) => {
                const isActive = option.value === value;
                option.classList.toggle('is-active', isActive);
                option.setAttribute('aria-pressed', isActive ? 'true' : 'false');

                const status = option.querySelector('.payment-method-sheet-status');
                if (status) {
                    status.textContent = isActive ? 'Dipilih' : 'Pilih';
                }
            });

            if (paymentMethodLabel) {
                paymentMethodLabel.textContent = methodLabels.get(value) || 'Pilih metode pembayaran';
            }
        };

        const openPaymentMethodSheet = () => {
            if (!paymentMethodSheet) {
                return;
            }

            paymentMethodSheet.hidden = false;
            paymentMethodSheet.setAttribute('aria-hidden', 'false');

            if (paymentMethodOpen) {
                paymentMethodOpen.setAttribute('aria-expanded', 'true');
            }

            document.body.style.overflow = 'hidden';
        };

        const closePaymentMethodSheet = () => {
            if (!paymentMethodSheet) {
                return;
            }

            paymentMethodSheet.hidden = true;
            paymentMethodSheet.setAttribute('aria-hidden', 'true');

            if (paymentMethodOpen) {
                paymentMethodOpen.setAttribute('aria-expanded', 'false');
            }

            document.body.style.overflow = '';
        };

        const initialValue = paymentMethodInput.value
            || paymentMethodRadios.find((radio) => radio.checked)?.value
            || paymentMethodOptions[0]?.value
            || '';

        syncPaymentMethod(initialValue);

        paymentMethodOpen?.addEventListener('click', openPaymentMethodSheet);

        paymentMethodCloseButtons.forEach((button) => {
            button.addEventListener('click', closePaymentMethodSheet);
        });

        paymentMethodSheet?.addEventListener('click', (event) => {
            if (event.target === paymentMethodSheet) {
                closePaymentMethodSheet();
            }
        });

        paymentMethodPanel?.addEventListener('click', (event) => {
            event.stopPropagation();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && paymentMethodSheet && !paymentMethodSheet.hidden) {
                closePaymentMethodSheet();
            }
        });

        paymentMethodRadios.forEach((radio) => {
            radio.addEventListener('change', () => {
                if (radio.checked) {
                    syncPaymentMethod(radio.value);
                }
            });
        });

        paymentMethodOptions.forEach((option) => {
            option.addEventListener('click', () => {
                syncPaymentMethod(option.value);
                closePaymentMethodSheet();
            });
        });
    })();

    document.getElementById('btn-bayar')?.addEventListener('click', async function () {
        const button = this;
        const defaultLabel = 'Buat Invoice dan Bayar Sekarang';
        const selectedMethod = document.getElementById('selected-payment-method')?.value || '';

        if (!selectedMethod) {
            showPaymentToast('Pilih metode pembayaran terlebih dahulu.');
            return;
        }

        button.disabled = true;
        button.textContent = 'Membuat invoice...';

        try {
            const res = await fetch('{{ route('pembayaran.attempt', $transaksi) }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify({ payment_method: selectedMethod }),
            });

            let data;
            try {
                data = await res.json();
            } catch (e) {
                throw new Error('Respons gateway pembayaran tidak valid. Silakan ulangi.');
            }

            if (!res.ok || data.error) {
                throw new Error(data.error || 'Terjadi kendala saat membuat invoice pembayaran.');
            }

            if (!data.checkout_url) {
                throw new Error('URL checkout pembayaran tidak tersedia. Silakan coba lagi.');
            }

            window.location.href = data.checkout_url;
        } catch (error) {
            showPaymentToast(error?.message || 'Terjadi kendala sistem pembayaran. Silakan coba lagi.');
            button.disabled = false;
            button.textContent = defaultLabel;
        }
    });

    document.getElementById('btn-refresh-payment')?.addEventListener('click', async function () {
        const button = this;
        const originalLabel = button.textContent;

        button.disabled = true;
        button.textContent = 'Menyinkronkan...';

        try {
            const res = await fetch(button.dataset.refreshUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    Accept: 'application/json',
                },
            });

            const data = await res.json();

            if (!res.ok || data.error) {
                throw new Error(data.error || 'Sinkronisasi status pembayaran gagal.');
            }

            window.location.reload();
        } catch (error) {
            showPaymentToast(error?.message || 'Sinkronisasi status pembayaran gagal.');
            button.disabled = false;
            button.textContent = originalLabel;
        }
    });

    function countdown(targetDate) {
        return {
            display: '',
            progress: 0,
            progressLabel: '0%',
            start() {
                const now = Date.now();
                const end = new Date(targetDate).getTime();
                const total = Math.max(end - now, 1);

                const update = () => {
                    const diff = new Date(targetDate) - new Date();

                     if (diff <= 0) {
                        this.display = 'Waktu habis';
                        this.progress = 100;
                        this.progressLabel = '100%';
                        return;
                    }

                    const elapsed = Math.max(total - diff, 0);
                    this.progress = Math.max(0, Math.min(100, (elapsed / total) * 100));
                    this.progressLabel = `${Math.round(this.progress)}%`;

                    const h = Math.floor(diff / 3600000);
                    const m = Math.floor((diff % 3600000) / 60000);
                    const s = Math.floor((diff % 60000) / 1000);
                    this.display = `${h}j ${m}m ${s}d`;
                };

                update();
                setInterval(update, 1000);
            },
        };
    }
</script>
@endsection
