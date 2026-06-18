@extends('layouts.app')
@section('title', 'Status Pembayaran')

@section('content')
@php
    $requestedReturnUrl = request()->query('return_url');
    $safeReturnUrl = safeInternalReturnUrl(
        $requestedReturnUrl,
        route('ikans.show', ['ikan' => $transaksi->ikan, 'return_url' => route('ikans.index')])
    );

    $latestPayment = $transaksi->latestPayment();
    $latestStatus = (string) ($latestPayment?->status_code ?? $transaksi->payment_status ?? $transaksi->status);

    if ($transaksi->isLunas()) {
        $statusCard = [
            'badge' => 'LUNAS',
            'title' => 'Pembayaran Berhasil',
            'description' => $transaksi->packed_at
                ? 'Pembayaran sudah masuk. Lanjutkan isi data penjemput.'
                : 'Pembayaran sudah masuk. Tunggu lot siap dijemput.',
            'class' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
            'badgeClass' => 'bg-emerald-100 text-emerald-700',
        ];
    } elseif (in_array($latestStatus, ['failed', 'cancelled'], true) || (string) $transaksi->status === 'gagal') {
        $statusCard = [
            'badge' => 'BELUM BERHASIL',
            'title' => 'Pembayaran Belum Berhasil',
            'description' => 'Invoice terakhir belum berhasil. Buka halaman pembayaran untuk mencoba lagi.',
            'class' => 'border-amber-200 bg-amber-50 text-amber-800',
            'badgeClass' => 'bg-amber-100 text-amber-700',
        ];
    } elseif (in_array($latestStatus, ['expired'], true) || (string) $transaksi->status === 'kadaluarsa' || $transaksi->isKadaluarsa()) {
        $statusCard = [
            'badge' => 'KADALUARSA',
            'title' => 'Invoice Kadaluarsa',
            'description' => 'Batas waktu pembayaran sudah habis.',
            'class' => 'border-rose-200 bg-rose-50 text-rose-800',
            'badgeClass' => 'bg-rose-100 text-rose-700',
        ];
    } else {
        $statusCard = [
            'badge' => 'MENUNGGU',
            'title' => 'Menunggu Konfirmasi Pembayaran',
            'description' => 'Jika Anda sudah membayar, cek status lagi beberapa saat nanti.',
            'class' => 'border-cyan-200 bg-cyan-50 text-cyan-800',
            'badgeClass' => 'bg-cyan-100 text-cyan-700',
        ];
    }
@endphp
<style>
    .payment-success-hero {
        background:
            radial-gradient(circle at 12% 14%, rgba(16, 185, 129, 0.16), transparent 34%),
            radial-gradient(circle at 86% 8%, rgba(34, 211, 238, 0.12), transparent 36%),
            linear-gradient(145deg, #f7fcfb 0%, #eefaf7 52%, #f8fcff 100%);
    }

    .payment-success-surface {
        border: 1px solid rgba(209, 250, 229, 0.95);
        box-shadow: 0 16px 24px -22px rgba(6, 78, 59, 0.45);
    }

    @media (max-width: 639px) {
        .payment-success-surface {
            border-radius: 1.5rem;
        }
    }
</style>

<div class="max-w-2xl mx-auto py-6 sm:py-8">
    <section class="payment-success-hero payment-success-surface rounded-3xl px-5 py-7 text-center sm:px-6 sm:py-8 mb-8 {{ $statusCard['class'] }}">
        <x-back-button :href="$safeReturnUrl" label="Kembali" />
        <span class="mt-6 inline-flex items-center rounded-full px-3 py-1.5 text-[11px] font-extrabold {{ $statusCard['badgeClass'] }}">{{ $statusCard['badge'] }}</span>
        <h1 class="mt-3 text-4xl font-black mb-2">{{ $statusCard['title'] }}</h1>
        <p>{{ $statusCard['description'] }}</p>
    </section>

    <div class="payment-success-surface bg-white rounded-3xl p-5 sm:p-6 text-left mb-8">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
            <div class="rounded-xl bg-slate-50 border border-slate-100 px-4 py-3"><p class="text-xs text-slate-400">Ikan</p><p class="font-bold text-slate-800">{{ $transaksi->ikan->nama_ikan }}</p></div>
            <div class="rounded-xl bg-slate-50 border border-slate-100 px-4 py-3"><p class="text-xs text-slate-400">Berat</p><p class="font-semibold text-slate-700">{{ $transaksi->ikan->berat }} kg</p></div>
            <div class="rounded-xl bg-cyan-50 border border-cyan-100 px-4 py-3"><p class="text-xs text-cyan-600">Harga Final</p><p class="font-black text-cyan-700 text-base">{{ formatRupiah($transaksi->harga_final) }}</p></div>
            <div class="rounded-xl bg-slate-50 border border-slate-100 px-4 py-3"><p class="text-xs text-slate-400">Order ID</p><p class="break-all font-bold uppercase text-slate-700">{{ $transaksi->order_code ?: '-' }}</p></div>
            <div class="rounded-xl bg-slate-50 border border-slate-100 px-4 py-3"><p class="text-xs text-slate-400">Progress Transaksi</p><p class="font-bold text-slate-700">{{ $transaksi->buyerProgressLabel() }}</p></div>
            <div class="rounded-xl bg-slate-50 border border-slate-100 px-4 py-3"><p class="text-xs text-slate-400">Status</p><p class="mt-1 inline-flex items-center rounded-full px-3 py-1.5 text-[11px] font-extrabold {{ $statusCard['badgeClass'] }}">{{ $statusCard['badge'] }}</p></div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-3 sm:flex sm:justify-center">
        <x-secondary-action-link :href="route('ikans.show', ['ikan' => $transaksi->ikan, 'return_url' => $safeReturnUrl])" class="px-6 py-3">
            Buka Detail Lot
        </x-secondary-action-link>
        @if($transaksi->isLunas())
            <a href="{{ route('pembeli.aktivitas.detail', ['ikan' => $transaksi->ikan, 'return_url' => $safeReturnUrl]) }}" class="inline-flex min-h-[48px] items-center justify-center rounded-xl bg-emerald-600 px-6 py-3 text-center font-bold text-white transition hover:bg-emerald-700">
                {{ $transaksi->packed_at ? 'Isi Data Penjemput' : 'Lihat Status Packing' }}
            </a>
        @else
            <a href="{{ route('pembayaran.show', ['transaksi' => $transaksi, 'return_url' => $safeReturnUrl]) }}" class="inline-flex min-h-[48px] items-center justify-center rounded-xl bg-slate-900 px-8 py-3 text-center font-bold text-white transition hover:bg-slate-800">
                Cek Status Lagi
            </a>
        @endif
    </div>
</div>
@endsection
