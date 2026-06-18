@extends('layouts.app')
@section('title', 'Top Up Saldo')

@section('content')
@php
    $statusLabels = [
        'pending' => 'Menunggu Pembayaran',
        'success' => 'Berhasil',
        'failed' => 'Gagal',
        'expired' => 'Kadaluarsa',
    ];
    $statusClasses = [
        'pending' => 'bg-amber-100 text-amber-700',
        'success' => 'bg-emerald-100 text-emerald-700',
        'failed' => 'bg-rose-100 text-rose-700',
        'expired' => 'bg-slate-200 text-slate-700',
    ];
    $quickAmounts = [50_000, 100_000, 250_000, 500_000];
@endphp

<style>
    .wallet-hero {
        background:
            radial-gradient(circle at 8% 14%, rgba(34, 211, 238, 0.16), transparent 33%),
            radial-gradient(circle at 92% 8%, rgba(20, 184, 166, 0.14), transparent 35%),
            linear-gradient(145deg, #f7fcff 0%, #eefbfb 48%, #f8fcff 100%);
    }

    .wallet-surface {
        border: 1px solid rgba(226, 232, 240, 0.95);
        box-shadow: 0 18px 30px -26px rgba(15, 23, 42, 0.65);
    }
</style>

<div class="max-w-6xl mx-auto">
    <section class="wallet-hero rounded-3xl border border-cyan-100/80 px-6 py-6 sm:px-8 sm:py-8 mb-6">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="inline-flex items-center gap-2 rounded-full bg-white/85 px-4 py-2 text-xs font-extrabold uppercase tracking-[0.28em] text-cyan-700 shadow-sm">
                    Wallet Borgfish
                </p>
                <h1 class="mt-4 text-3xl sm:text-4xl font-black tracking-tight text-slate-900">Top up saldo buyer</h1>
                <p class="mt-2 max-w-2xl text-sm sm:text-base text-slate-600">
                    Isi saldo untuk ikut lelang tanpa ribet. Dana masuk ke saldo tersedia setelah Midtrans mengonfirmasi pembayaran.
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 w-full lg:max-w-2xl">
                <div class="wallet-surface rounded-2xl bg-white/90 px-4 py-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Saldo tersedia</p>
                    <p class="mt-2 text-2xl font-black text-cyan-800">{{ formatRupiah($user->saldoTersedia()) }}</p>
                </div>
                <div class="wallet-surface rounded-2xl bg-white/90 px-4 py-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Saldo ditahan</p>
                    <p class="mt-2 text-2xl font-black text-amber-700">{{ formatRupiah($user->saldoDitahan()) }}</p>
                </div>
                <div class="wallet-surface rounded-2xl bg-white/90 px-4 py-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Total dana</p>
                    <p class="mt-2 text-2xl font-black text-slate-900">{{ formatRupiah($user->totalDana()) }}</p>
                </div>
            </div>
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.8fr)_minmax(320px,1fr)]">
        <div class="space-y-6">
            <section class="wallet-surface rounded-3xl bg-white p-6 sm:p-7">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-xl font-black text-slate-900">Buat permintaan top up</h2>
                        <p class="text-sm text-slate-500">Minimal top up {{ formatRupiah($minimumTopupAmount) }}. Setelah itu lanjut pilih metode pembayaran di Midtrans.</p>
                    </div>
                    <a href="{{ route('saldo.ledger') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                        Lihat mutasi saldo
                    </a>
                </div>

                <form method="POST" action="{{ route('saldo.topup.store') }}" class="mt-6 space-y-5">
                    @csrf

                    <div>
                        <label for="amount" class="block text-sm font-bold text-slate-700">Nominal top up</label>
                        <div class="mt-2 relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-sm font-bold text-slate-400">Rp</span>
                            <input
                                type="number"
                                min="{{ $minimumTopupAmount }}"
                                step="1000"
                                name="amount"
                                id="amount"
                                value="{{ old('amount', $minimumTopupAmount) }}"
                                inputmode="numeric"
                                class="w-full rounded-2xl border border-slate-200 bg-slate-50 py-4 pl-12 pr-4 text-lg font-black text-slate-900 shadow-sm focus:border-cyan-400 focus:bg-white focus:outline-none focus:ring-4 focus:ring-cyan-100"
                                required
                                placeholder="Minimal {{ number_format($minimumTopupAmount, 0, ',', '.') }}"
                            >
                        </div>
                        <p class="mt-2 text-xs text-slate-500">Nominal akan masuk ke saldo tersedia setelah pembayaran sukses.</p>
                    </div>

                    <div>
                        <p class="text-sm font-bold text-slate-700">Pilih nominal cepat</p>
                        <div class="mt-3 flex flex-wrap gap-3">
                            @foreach($quickAmounts as $quickAmount)
                                <button
                                    type="button"
                                    class="quick-topup inline-flex items-center rounded-xl border border-cyan-200 bg-cyan-50 px-4 py-2 text-sm font-bold text-cyan-700 transition hover:bg-cyan-100"
                                    data-amount="{{ $quickAmount }}"
                                >
                                    {{ formatRupiah($quickAmount) }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="rounded-2xl border border-cyan-100 bg-cyan-50/70 p-4 text-sm text-cyan-800">
                        <p class="font-bold">Yang perlu kamu tahu</p>
                        <ul class="mt-2 space-y-1 text-cyan-900/80">
                            <li>- Top up diproses melalui Midtrans dengan metode transfer bank, e-wallet, QRIS, dan gerai retail.</li>
                            <li>- Saldo ini dipakai untuk bid dan akan otomatis ditahan saat kamu memimpin lelang.</li>
                            <li>- Jika kalah lelang, dana yang ditahan akan kembali ke saldo tersedia secara otomatis.</li>
                        </ul>
                    </div>

                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-slate-900 px-5 py-4 text-base font-black text-white transition hover:bg-slate-800">
                        Lanjutkan ke pembayaran
                    </button>
                </form>
            </section>
        </div>

        <div class="space-y-6">
            <section class="wallet-surface rounded-3xl bg-white p-6">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-black text-slate-900">Top up terbaru</h2>
                        <p class="text-sm text-slate-500">Pantau status pembayaran terakhir kamu.</p>
                    </div>
                    <span class="text-xs font-bold uppercase tracking-wide text-slate-400">5 terakhir</span>
                </div>

                @if($recentTopups->isEmpty())
                    <div class="mt-5 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                        Belum ada riwayat top up. Buat permintaan pertamamu dari form di halaman ini.
                    </div>
                @else
                    <div class="mt-5 space-y-3">
                        @foreach($recentTopups as $topup)
                            <a href="{{ route('saldo.topup.pay', $topup) }}" class="block rounded-2xl border border-slate-200 px-4 py-4 transition hover:border-cyan-200 hover:bg-cyan-50/50">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-black text-slate-900">{{ formatRupiah($topup->amount) }}</p>
                                        <p class="mt-1 text-xs text-slate-500">Dibuat {{ $topup->requested_at?->diffForHumans() ?? $topup->created_at?->diffForHumans() }}</p>
                                    </div>
                                    <span class="inline-flex rounded-full px-3 py-1 text-[11px] font-bold {{ $statusClasses[$topup->status] ?? 'bg-slate-100 text-slate-600' }}">
                                        {{ $statusLabels[$topup->status] ?? ucfirst($topup->status) }}
                                    </span>
                                </div>
                                <p class="mt-3 text-xs text-slate-500">
                                    {{ $topup->midtrans_order_id ? 'Order ID: ' . $topup->midtrans_order_id : 'Belum membuat sesi pembayaran' }}
                                </p>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="wallet-surface rounded-3xl bg-white p-6">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-black text-slate-900">Mutasi terbaru</h2>
                        <p class="text-sm text-slate-500">Ringkasan saldo yang paling baru.</p>
                    </div>
                    <a href="{{ route('saldo.ledger') }}" class="text-sm font-bold text-cyan-700 transition hover:text-cyan-800">
                        Buka ledger
                    </a>
                </div>

                @if($recentLedgers->isEmpty())
                    <div class="mt-5 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                        Belum ada mutasi. Saat top up, bid, atau pelepasan hold terjadi, riwayatnya akan muncul di sini.
                    </div>
                @else
                    <div class="mt-5 space-y-3">
                        @foreach($recentLedgers as $ledger)
                            <div class="rounded-2xl border border-slate-200 px-4 py-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-black text-slate-900">{{ $ledger->note ?: ucfirst(str_replace('_', ' ', $ledger->entry_type)) }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $ledger->created_at?->diffForHumans() }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-black {{ (float) $ledger->available_delta >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                            {{ (float) $ledger->available_delta >= 0 ? '+' : '' }}{{ formatRupiah($ledger->available_delta) }}
                                        </p>
                                        <p class="text-[11px] text-slate-500">Ditahan {{ (float) $ledger->held_delta >= 0 ? '+' : '' }}{{ formatRupiah($ledger->held_delta) }}</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('.quick-topup').forEach(function (button) {
        button.addEventListener('click', function () {
            const amountInput = document.getElementById('amount');

            if (!amountInput) {
                return;
            }

            amountInput.value = this.dataset.amount || '';
            amountInput.focus();
        });
    });
</script>
@endpush
