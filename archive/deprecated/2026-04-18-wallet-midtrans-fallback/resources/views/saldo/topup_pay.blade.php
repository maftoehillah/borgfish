@extends('layouts.app')
@section('title', 'Pembayaran Top Up Saldo')

@section('content')
@php
    $statusLabel = match ($topup->status) {
        'success' => 'Berhasil',
        'failed' => 'Gagal',
        'expired' => 'Kadaluarsa',
        default => 'Menunggu Pembayaran',
    };
    $statusClass = match ($topup->status) {
        'success' => 'bg-emerald-100 text-emerald-700',
        'failed' => 'bg-rose-100 text-rose-700',
        'expired' => 'bg-slate-200 text-slate-700',
        default => 'bg-amber-100 text-amber-700',
    };
    $refreshUrl = route('saldo.topup.pay', ['topup' => $topup, 'refresh' => 1]);
    $cleanPayUrl = route('saldo.topup.pay', $topup);
@endphp

<style>
    .wallet-hero {
        background:
            radial-gradient(circle at 10% 10%, rgba(14, 165, 233, 0.16), transparent 34%),
            radial-gradient(circle at 88% 6%, rgba(45, 212, 191, 0.14), transparent 36%),
            linear-gradient(145deg, #f7fcff 0%, #eef9ff 50%, #f7fcff 100%);
    }

    .wallet-surface {
        border: 1px solid rgba(226, 232, 240, 0.95);
        box-shadow: 0 18px 30px -26px rgba(15, 23, 42, 0.65);
    }
</style>

<div class="max-w-5xl mx-auto">
    <section class="wallet-hero rounded-3xl border border-cyan-100/80 px-6 py-6 sm:px-8 sm:py-8 mb-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <a href="{{ route('saldo.topup') }}" class="inline-flex items-center gap-2 rounded-xl border border-cyan-200 bg-white/90 px-4 py-2 text-sm font-extrabold text-cyan-700 shadow-sm transition hover:bg-cyan-50">
                    Kembali ke top up
                </a>
                <h1 class="mt-4 text-3xl sm:text-4xl font-black tracking-tight text-slate-900">Pembayaran top up saldo</h1>
                <p class="mt-2 max-w-2xl text-sm sm:text-base text-slate-600">
                    Selesaikan pembayaran agar saldo buyer langsung siap dipakai untuk mengikuti lelang.
                </p>
            </div>

            <div class="wallet-surface rounded-2xl bg-white/90 px-4 py-4 sm:min-w-[220px]">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Status top up</p>
                <span class="mt-2 inline-flex rounded-full px-3 py-1 text-xs font-black {{ $statusClass }}">
                    {{ $statusLabel }}
                </span>
                <p class="mt-3 text-xs text-slate-500">Request #{{ $topup->id }}</p>
            </div>
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(320px,1fr)]">
        <section class="wallet-surface rounded-3xl bg-white p-6 sm:p-7">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-bold uppercase tracking-wide text-slate-400">Nominal top up</p>
                    <p class="mt-2 text-4xl font-black text-slate-900">{{ formatRupiah($topup->amount) }}</p>
                </div>

                @if($topup->payment_method)
                    <div class="rounded-2xl bg-slate-100 px-4 py-3 text-sm font-bold text-slate-700">
                        Metode: {{ strtoupper(str_replace('_', ' ', $topup->payment_method)) }}
                    </div>
                @endif
            </div>

            @if(request()->boolean('refresh') && $topup->isPending())
                <div class="mt-5 rounded-2xl border border-blue-100 bg-blue-50 px-4 py-4 text-sm text-blue-800">
                    Kami sedang sinkronkan status pembayaran terbaru. Halaman ini akan refresh sekali otomatis dalam beberapa detik.
                </div>
            @endif

            @if($topup->isSuccess())
                <div class="mt-6 rounded-3xl border border-emerald-200 bg-emerald-50 p-6">
                    <h2 class="text-2xl font-black text-emerald-800">Saldo berhasil ditambahkan</h2>
                    <p class="mt-2 text-sm text-emerald-700">
                        Top up sudah dikonfirmasi dan nominal masuk ke saldo tersedia buyer.
                    </p>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl bg-white/80 px-4 py-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Dibayar pada</p>
                            <p class="mt-2 text-sm font-black text-slate-900">{{ $topup->paid_at?->format('d M Y H:i') ?? '-' }}</p>
                        </div>
                        <div class="rounded-2xl bg-white/80 px-4 py-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Order ID</p>
                            <p class="mt-2 text-sm font-black text-slate-900 break-all">{{ $topup->midtrans_order_id ?? '-' }}</p>
                        </div>
                    </div>
                    <div class="mt-5 flex flex-wrap gap-3">
                        <a href="{{ route('saldo.ledger') }}" class="inline-flex items-center justify-center rounded-2xl bg-emerald-600 px-5 py-3 text-sm font-black text-white transition hover:bg-emerald-700">
                            Lihat riwayat ledger
                        </a>
                        <a href="{{ route('saldo.topup') }}" class="inline-flex items-center justify-center rounded-2xl border border-emerald-200 bg-white px-5 py-3 text-sm font-black text-emerald-700 transition hover:bg-emerald-50">
                            Top up lagi
                        </a>
                    </div>
                </div>
            @elseif($topup->isFailed() || $topup->isExpired())
                <div class="mt-6 rounded-3xl border border-rose-200 bg-rose-50 p-6">
                    <h2 class="text-2xl font-black text-rose-800">
                        {{ $topup->isExpired() ? 'Pembayaran top up sudah kadaluarsa' : 'Pembayaran top up belum berhasil' }}
                    </h2>
                    <p class="mt-2 text-sm text-rose-700">
                        Supaya saldo tetap aman dan rapi, buat permintaan top up baru untuk mencoba kembali.
                    </p>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl bg-white/80 px-4 py-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Request dibuat</p>
                            <p class="mt-2 text-sm font-black text-slate-900">{{ $topup->requested_at?->format('d M Y H:i') ?? $topup->created_at?->format('d M Y H:i') }}</p>
                        </div>
                        <div class="rounded-2xl bg-white/80 px-4 py-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Batas estimasi</p>
                            <p class="mt-2 text-sm font-black text-slate-900">{{ $topup->expired_at?->format('d M Y H:i') ?? '-' }}</p>
                        </div>
                    </div>
                    <div class="mt-5">
                        <a href="{{ route('saldo.topup') }}" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-black text-white transition hover:bg-slate-800">
                            Buat top up baru
                        </a>
                    </div>
                </div>
            @else
                <div class="mt-6 space-y-4">
                    <div class="rounded-2xl border border-amber-100 bg-amber-50 px-4 py-4 text-sm text-amber-800">
                        Pilih metode pembayaran di Midtrans. Setelah pembayaran diterima, saldo tersedia akan bertambah otomatis tanpa perlu konfirmasi manual.
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Request dibuat</p>
                            <p class="mt-2 text-sm font-black text-slate-900">{{ $topup->requested_at?->format('d M Y H:i') ?? $topup->created_at?->format('d M Y H:i') }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Batas estimasi</p>
                            <p class="mt-2 text-sm font-black text-slate-900">{{ $topup->expired_at?->format('d M Y H:i') ?? now()->addHours(24)->format('d M Y H:i') }}</p>
                        </div>
                    </div>

                    <button id="btn-topup-pay" class="inline-flex w-full items-center justify-center rounded-2xl bg-slate-900 px-5 py-4 text-base font-black text-white transition hover:bg-slate-800">
                        Pilih metode dan bayar sekarang
                    </button>

                    <p class="text-center text-xs text-slate-400">Pembayaran aman diproses melalui Midtrans.</p>
                </div>
            @endif
        </section>

        <aside class="space-y-6">
            <section class="wallet-surface rounded-3xl bg-white p-6">
                <h2 class="text-lg font-black text-slate-900">Ringkasan wallet</h2>
                <div class="mt-4 space-y-3">
                    <div class="rounded-2xl bg-cyan-50 px-4 py-4">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Saldo tersedia</p>
                        <p class="mt-2 text-2xl font-black text-cyan-800">{{ formatRupiah($topup->user->saldoTersedia()) }}</p>
                    </div>
                    <div class="rounded-2xl bg-amber-50 px-4 py-4">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Saldo ditahan</p>
                        <p class="mt-2 text-2xl font-black text-amber-700">{{ formatRupiah($topup->user->saldoDitahan()) }}</p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 px-4 py-4">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Total dana</p>
                        <p class="mt-2 text-2xl font-black text-slate-900">{{ formatRupiah($topup->user->totalDana()) }}</p>
                    </div>
                </div>
            </section>

            <section class="wallet-surface rounded-3xl bg-white p-6">
                <h2 class="text-lg font-black text-slate-900">Detail request</h2>
                <dl class="mt-4 space-y-4 text-sm">
                    <div>
                        <dt class="font-bold text-slate-500">Request ID</dt>
                        <dd class="mt-1 font-black text-slate-900">#{{ $topup->id }}</dd>
                    </div>
                    <div>
                        <dt class="font-bold text-slate-500">Order ID Midtrans</dt>
                        <dd class="mt-1 break-all font-black text-slate-900">{{ $topup->midtrans_order_id ?? 'Belum dibuat' }}</dd>
                    </div>
                    <div>
                        <dt class="font-bold text-slate-500">Status</dt>
                        <dd class="mt-1">
                            <span class="inline-flex rounded-full px-3 py-1 text-[11px] font-black {{ $statusClass }}">
                                {{ $statusLabel }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="font-bold text-slate-500">Mutasi setelah sukses</dt>
                        <dd class="mt-1 text-slate-700">Saldo tersedia bertambah, lalu riwayat ledger otomatis tercatat.</dd>
                    </div>
                </dl>
            </section>
        </aside>
    </div>
</div>

@if($topup->isPending() && $paymentSession)
    <script src="{{ $paymentSession['script_url'] }}" data-client-key="{{ $paymentSession['client_key'] }}"></script>
    <script>
        (function () {
            const button = document.getElementById('btn-topup-pay');
            const defaultLabel = 'Pilih metode dan bayar sekarang';

            if (@json(request()->boolean('refresh'))) {
                window.history.replaceState({}, '', @json($cleanPayUrl));
                window.setTimeout(function () {
                    window.location.reload();
                }, 4000);
            }

            if (!button) {
                return;
            }

            button.addEventListener('click', function () {
                if (!window.snap || typeof window.snap.pay !== 'function') {
                    alert('Layanan pembayaran sedang tidak tersedia. Silakan coba lagi beberapa saat lagi.');
                    return;
                }

                button.disabled = true;
                button.textContent = 'Membuka Midtrans...';

                window.snap.pay(@json($paymentSession['snap_token']), {
                    onSuccess: function () {
                        window.location = @json($refreshUrl);
                    },
                    onPending: function () {
                        window.location = @json($refreshUrl);
                    },
                    onError: function () {
                        alert('Pembayaran gagal. Silakan coba lagi.');
                        button.disabled = false;
                        button.textContent = defaultLabel;
                    },
                    onClose: function () {
                        button.disabled = false;
                        button.textContent = defaultLabel;
                    }
                });
            });
        })();
    </script>
@endif
@endsection
