@extends('layouts.app')
@section('title', 'Penilaian Transaksi')

@section('content')
@php
    $requestedReturnUrl = request()->query('return_url');
    $safeReturnUrl = safeInternalReturnUrl($requestedReturnUrl, route('pembeli.aktivitas'));
    $currentPageUrl = request()->fullUrl();
    $isReviewed = $transaksi->completed_by_buyer_at !== null || $transaksi->buyer_reviewed_at !== null || $transaksi->pickup_status === 'completed';
@endphp

<style>
    .buyer-review-hero {
        background:
            radial-gradient(circle at 10% 10%, rgba(16, 185, 129, 0.15), transparent 34%),
            radial-gradient(circle at 90% 0%, rgba(14, 165, 233, 0.14), transparent 36%),
            linear-gradient(145deg, #f8fcff 0%, #effdf7 55%, #f8fcff 100%);
    }

    .buyer-review-surface {
        border: 1px solid rgba(226, 232, 240, 0.95);
        box-shadow: 0 16px 24px -22px rgba(15, 23, 42, 0.6);
    }
</style>

<section class="buyer-review-hero rounded-3xl border border-emerald-100/70 px-6 py-6 sm:px-8 sm:py-7 mb-8">
    <x-back-button :href="$safeReturnUrl" label="Kembali ke Aktivitas" />
    <h1 class="mt-3 text-3xl sm:text-4xl font-black tracking-tight text-slate-900">Penilaian Transaksi</h1>
    <p class="mt-2 text-slate-600 max-w-2xl">Beri penilaian setelah penjemputan divalidasi, atau lihat kembali review yang sudah Anda kirim.</p>
</section>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 buyer-review-surface bg-white rounded-3xl p-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-emerald-700">Lot Selesai</p>
                <h2 class="mt-1 text-2xl font-black text-slate-900">{{ $ikan->nama_ikan }}</h2>
                <p class="mt-1 text-sm text-slate-500">Penjual: {{ $ikan->user->name }}</p>
            </div>
            <span class="inline-flex px-3 py-1 rounded-full text-xs font-bold {{ $isReviewed ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                {{ $isReviewed ? 'Sudah Dikonfirmasi' : 'Menunggu Penilaian' }}
            </span>
        </div>

        <div class="mt-5 grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
            <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                <p class="text-xs text-slate-500">Total Transaksi</p>
                <p class="mt-1 font-black text-slate-900">{{ formatRupiah($transaksi->harga_final) }}</p>
            </div>
            <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                <p class="text-xs text-slate-500">Progress Transaksi</p>
                <p class="mt-1 font-black text-slate-900">{{ $transaksi->buyerProgressLabel() }}</p>
            </div>
            <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                <p class="text-xs text-slate-500">Konfirmasi Buyer</p>
                <p class="mt-1 font-black text-slate-900">{{ $transaksi->completed_by_buyer_at?->format('d M Y H:i') ?? '-' }}</p>
            </div>
        </div>

        @if($canSubmitReview)
            <form action="{{ route('pembeli.ikans.diterima', $ikan) }}" method="POST" class="mt-6 rounded-2xl border border-emerald-100 bg-emerald-50 p-5 space-y-3">
                @csrf
                <input type="hidden" name="return_url" value="{{ $currentPageUrl }}">
                <p class="text-sm font-bold text-emerald-800">Review dan Konfirmasi Selesai</p>
                <select name="buyer_rating" class="w-full border border-emerald-200 rounded-lg px-3 py-2 text-sm bg-white">
                    <option value="">Rating barang (opsional)</option>
                    @for($rating = 5; $rating >= 1; $rating--)
                        <option value="{{ $rating }}">{{ $rating }} bintang</option>
                    @endfor
                </select>
                <textarea name="buyer_review" rows="3" class="w-full border border-emerald-200 rounded-lg px-3 py-2 text-sm bg-white" placeholder="Review barang (opsional)"></textarea>
                <button type="submit" class="w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-700">Konfirmasi Selesai</button>
            </form>
        @elseif($isReviewed)
            <div class="mt-6 rounded-2xl border border-emerald-100 bg-emerald-50 p-5">
                <p class="text-sm font-bold text-emerald-800">Penilaian Anda</p>
                <p class="mt-3 text-sm text-slate-700">
                    Rating:
                    <span class="font-black text-slate-900">{{ $transaksi->buyer_rating ? $transaksi->buyer_rating . ' bintang' : 'Tidak diisi' }}</span>
                </p>
                <p class="mt-2 text-sm text-slate-700">Review:</p>
                <p class="mt-1 rounded-xl bg-white border border-emerald-100 px-4 py-3 text-sm text-slate-700">{{ $transaksi->buyer_review ?: 'Tidak ada review tertulis.' }}</p>
            </div>
        @else
            <div class="mt-6 rounded-2xl border border-amber-100 bg-amber-50 p-5">
                <p class="text-sm font-bold text-amber-800">Penilaian belum bisa dikirim.</p>
                <p class="mt-1 text-sm text-amber-700">Penjual perlu menandai penjemput datang terlebih dahulu sebelum transaksi bisa dikonfirmasi selesai.</p>
            </div>
        @endif
    </div>

    <aside class="buyer-review-surface bg-white rounded-3xl p-5 h-fit">
        <h3 class="font-bold text-slate-900 mb-3">Ringkasan Penjemputan</h3>
        <div class="space-y-2 text-sm text-slate-700">
            <p>Sopir: <span class="font-semibold">{{ $transaksi->buyer_pickup_name ?: '-' }}</span></p>
            <p>Plat: <span class="break-all font-semibold">{{ $transaksi->buyer_pickup_plate_number ?: '-' }}</span></p>
            <p>Penjemput datang: <span class="font-semibold">{{ $transaksi->pickup_verified_at?->format('d M Y H:i') ?? '-' }}</span></p>
        </div>
        <x-fulfillment-photo-grid :transaksi="$transaksi" title="Foto Packing & Penjemputan" />
        <x-secondary-action-link :href="route('ikans.show', ['ikan' => $ikan, 'return_url' => $currentPageUrl])" class="mt-5 flex w-full text-sm">
            Buka Detail Lot
        </x-secondary-action-link>
    </aside>
</div>
@endsection
