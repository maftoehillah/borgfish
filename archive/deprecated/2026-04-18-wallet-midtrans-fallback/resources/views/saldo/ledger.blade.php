@extends('layouts.app')
@section('title', 'Riwayat Saldo')

@section('content')
@php
    $entryLabels = [
        'topup' => 'Top Up Saldo',
        'auction_hold_locked' => 'Hold Bid Aktif',
        'auction_hold_released' => 'Hold Dilepas',
        'auction_hold_rebalanced' => 'Hold Disesuaikan',
        'auction_hold_captured' => 'Pembayaran Otomatis',
    ];
    $entryBadges = [
        'topup' => 'bg-emerald-100 text-emerald-700',
        'auction_hold_locked' => 'bg-amber-100 text-amber-700',
        'auction_hold_released' => 'bg-cyan-100 text-cyan-700',
        'auction_hold_rebalanced' => 'bg-slate-100 text-slate-700',
        'auction_hold_captured' => 'bg-indigo-100 text-indigo-700',
    ];
@endphp

<style>
    .wallet-hero {
        background:
            radial-gradient(circle at 10% 12%, rgba(34, 197, 94, 0.14), transparent 33%),
            radial-gradient(circle at 90% 6%, rgba(6, 182, 212, 0.16), transparent 35%),
            linear-gradient(145deg, #f8fcff 0%, #eefbf8 48%, #f9fcff 100%);
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
                    Ledger Wallet
                </p>
                <h1 class="mt-4 text-3xl sm:text-4xl font-black tracking-tight text-slate-900">Riwayat mutasi saldo</h1>
                <p class="mt-2 max-w-2xl text-sm sm:text-base text-slate-600">
                    Semua perubahan saldo buyer tercatat di sini, mulai dari top up, hold bid, pelepasan dana, sampai pembayaran otomatis.
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('saldo.topup') }}" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-black text-white transition hover:bg-slate-800">
                    Top up saldo
                </a>
                <a href="{{ route('ikans.index') }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-black text-slate-700 transition hover:bg-slate-50">
                    Kembali ke marketplace
                </a>
            </div>
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.8fr)_minmax(320px,1fr)]">
        <div class="space-y-6">
            <section class="wallet-surface rounded-3xl bg-white p-6 sm:p-7">
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl bg-cyan-50 px-4 py-4">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Saldo tersedia</p>
                        <p class="mt-2 text-2xl font-black text-cyan-800">{{ formatRupiah($user->saldoTersedia()) }}</p>
                    </div>
                    <div class="rounded-2xl bg-amber-50 px-4 py-4">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Saldo ditahan</p>
                        <p class="mt-2 text-2xl font-black text-amber-700">{{ formatRupiah($user->saldoDitahan()) }}</p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 px-4 py-4">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Total dana</p>
                        <p class="mt-2 text-2xl font-black text-slate-900">{{ formatRupiah($user->totalDana()) }}</p>
                    </div>
                </div>
            </section>

            <section class="wallet-surface rounded-3xl bg-white p-6 sm:p-7">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-xl font-black text-slate-900">Daftar mutasi</h2>
                        <p class="text-sm text-slate-500">Menampilkan 20 mutasi terbaru dengan saldo akhir setelah tiap perubahan.</p>
                    </div>
                    <span class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ $ledgers->total() }} total entri</span>
                </div>

                @if($ledgers->isEmpty())
                    <div class="mt-5 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                        Belum ada mutasi saldo untuk akun ini.
                    </div>
                @else
                    <div class="mt-5 overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead>
                                <tr class="text-left text-xs font-extrabold uppercase tracking-wide text-slate-500">
                                    <th class="pb-3 pr-4">Waktu</th>
                                    <th class="pb-3 pr-4">Jenis</th>
                                    <th class="pb-3 pr-4">Saldo tersedia</th>
                                    <th class="pb-3 pr-4">Saldo ditahan</th>
                                    <th class="pb-3 pr-4">Saldo akhir</th>
                                    <th class="pb-3">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-sm">
                                @foreach($ledgers as $ledger)
                                    <tr class="align-top">
                                        <td class="py-4 pr-4 text-slate-600">
                                            <p class="font-bold text-slate-900">{{ $ledger->created_at?->format('d M Y') }}</p>
                                            <p class="text-xs text-slate-500">{{ $ledger->created_at?->format('H:i') }}</p>
                                        </td>
                                        <td class="py-4 pr-4">
                                            <span class="inline-flex rounded-full px-3 py-1 text-[11px] font-black {{ $entryBadges[$ledger->entry_type] ?? 'bg-slate-100 text-slate-700' }}">
                                                {{ $entryLabels[$ledger->entry_type] ?? ucwords(str_replace('_', ' ', $ledger->entry_type)) }}
                                            </span>
                                        </td>
                                        <td class="py-4 pr-4 font-black {{ (float) $ledger->available_delta >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                            {{ (float) $ledger->available_delta >= 0 ? '+' : '' }}{{ formatRupiah($ledger->available_delta) }}
                                        </td>
                                        <td class="py-4 pr-4 font-black {{ (float) $ledger->held_delta >= 0 ? 'text-amber-700' : 'text-slate-700' }}">
                                            {{ (float) $ledger->held_delta >= 0 ? '+' : '' }}{{ formatRupiah($ledger->held_delta) }}
                                        </td>
                                        <td class="py-4 pr-4 text-slate-700">
                                            <p class="font-black text-slate-900">{{ formatRupiah($ledger->balance_after) }}</p>
                                            <p class="text-xs text-slate-500">Ditahan {{ formatRupiah($ledger->held_after) }}</p>
                                        </td>
                                        <td class="py-4 text-slate-600">
                                            <p class="font-semibold text-slate-800">{{ $ledger->note ?: '-' }}</p>
                                            @if($ledger->reference_type && $ledger->reference_id)
                                                <p class="mt-1 text-xs text-slate-500">
                                                    Ref: {{ $ledger->reference_type }} #{{ $ledger->reference_id }}
                                                </p>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6">
                        {{ $ledgers->onEachSide(1)->links() }}
                    </div>
                @endif
            </section>
        </div>

        <aside class="space-y-6">
            <section class="wallet-surface rounded-3xl bg-white p-6">
                <h2 class="text-lg font-black text-slate-900">Top up terbaru</h2>
                <p class="mt-1 text-sm text-slate-500">Shortcut untuk membuka status pembayaran terakhir.</p>

                @if($recentTopups->isEmpty())
                    <div class="mt-5 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                        Belum ada top up terbaru.
                    </div>
                @else
                    <div class="mt-5 space-y-3">
                        @foreach($recentTopups as $topup)
                            <a href="{{ route('saldo.topup.pay', $topup) }}" class="block rounded-2xl border border-slate-200 px-4 py-4 transition hover:border-cyan-200 hover:bg-cyan-50/50">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-black text-slate-900">{{ formatRupiah($topup->amount) }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $topup->requested_at?->diffForHumans() ?? $topup->created_at?->diffForHumans() }}</p>
                                    </div>
                                    <span class="inline-flex rounded-full px-3 py-1 text-[11px] font-black {{
                                        $topup->status === 'success'
                                            ? 'bg-emerald-100 text-emerald-700'
                                            : ($topup->status === 'pending'
                                                ? 'bg-amber-100 text-amber-700'
                                                : ($topup->status === 'failed'
                                                    ? 'bg-rose-100 text-rose-700'
                                                    : 'bg-slate-100 text-slate-700'))
                                    }}">
                                        {{ $topup->status === 'success'
                                            ? 'Berhasil'
                                            : ($topup->status === 'pending'
                                                ? 'Pending'
                                                : ($topup->status === 'failed' ? 'Gagal' : 'Kadaluarsa')) }}
                                    </span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="wallet-surface rounded-3xl bg-white p-6">
                <h2 class="text-lg font-black text-slate-900">Cara membaca ledger</h2>
                <div class="mt-4 space-y-3 text-sm text-slate-600">
                    <p><span class="font-black text-emerald-700">Saldo tersedia</span> adalah dana yang langsung bisa dipakai untuk bid.</p>
                    <p><span class="font-black text-amber-700">Saldo ditahan</span> adalah dana yang sedang mengamankan posisi bid teratas.</p>
                    <p><span class="font-black text-indigo-700">Pembayaran otomatis</span> berarti dana hold pemenang sudah dikonversi menjadi pembayaran lelang.</p>
                </div>
            </section>
        </aside>
    </div>
</div>
@endsection
