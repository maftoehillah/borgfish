@extends('layouts.app')
@section('title', 'Detail Aktivitas Bid')

@section('content')
@php
    $requestedReturnUrl = request()->query('return_url');
    $safeReturnUrl = safeInternalReturnUrl($requestedReturnUrl, route('pembeli.aktivitas'));
    $currentPageUrl = request()->fullUrl();
    $labelTipe = $ikan->isLelangTurun() ? 'Lelang Turun' : 'Lelang Naik';
    $labelHargaSaatIni = $ikan->isLelangTurun() ? 'Bid Teratas Saat Ini' : 'Harga Tertinggi Saat Ini';
    $trx = $ikan->transaksi;
    $latestDispute = $latestDispute ?? null;
    $fulfillmentState = $trx?->fulfillment_state;
    $paymentUrl = $trx ? route('pembayaran.show', ['transaksi' => $trx, 'return_url' => $currentPageUrl]) : null;
    $buyerProgressLabel = $trx?->buyerProgressLabel();
    $buyerProgressBadgeClass = $trx?->buyerProgressBadgeClass() ?? 'bg-slate-100 text-slate-600';
    $buyerProgressDescription = $trx?->buyerProgressDescription();
@endphp

<style>
    .buyer-detail-hero {
        background:
            radial-gradient(circle at 8% 12%, rgba(59, 130, 246, 0.15), transparent 34%),
            radial-gradient(circle at 90% 0%, rgba(34, 211, 238, 0.14), transparent 36%),
            linear-gradient(145deg, #f8fcff 0%, #eef6ff 55%, #f8fcff 100%);
    }

    .buyer-surface {
        border: 1px solid rgba(226, 232, 240, 0.95);
        box-shadow: 0 16px 24px -22px rgba(15, 23, 42, 0.6);
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

    @media (prefers-reduced-motion: reduce) {
        .buyer-priority-cta-pay,
        .buyer-priority-cta-confirm {
            transition: none;
        }
    }
</style>

<section class="buyer-detail-hero rounded-3xl border border-blue-100/70 px-6 py-6 sm:px-8 sm:py-7 mb-8">
    <x-back-button :href="$safeReturnUrl" label="Kembali ke Aktivitas" />
    <h1 class="mt-3 text-3xl sm:text-4xl font-black tracking-tight text-slate-900">Detail Aktivitas Bid</h1>
    <p class="mt-2 text-slate-600">Lihat posisi bid dan status transaksi lot ini.</p>
    <div class="mt-4 flex flex-wrap gap-2">
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold {{ $ikan->isLelangTurun() ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">{{ $labelTipe }}</span>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold {{ $isMemimpin ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">{{ $isMemimpin ? 'Memimpin' : 'Belum memimpin' }}</span>
    </div>

</section>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div id="lot-summary" class="lg:col-span-2 buyer-surface bg-white rounded-3xl overflow-hidden scroll-mt-28">
        <div class="px-5 py-4 border-b border-slate-100">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="font-black text-xl text-slate-900">{{ $ikan->nama_ikan }}</h2>
                    <p class="text-xs text-slate-500 mt-1">Penjual: {{ $ikan->user->name }} &bull; {{ $labelTipe }}</p>
                </div>
                <span class="text-xs font-bold px-2.5 py-1 rounded-full {{ $ikan->isLelangTurun() ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">{{ $labelTipe }}</span>
            </div>
        </div>

        <div class="px-5 py-4 grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
            <div class="rounded-xl bg-slate-50 p-3 border border-slate-100">
                <p class="text-xs text-slate-500">Bid terbaik saya</p>
                <p class="font-black text-slate-900 mt-1">{{ $bestBidSaya !== null ? formatRupiah($bestBidSaya) : '-' }}</p>
            </div>
            <div class="rounded-xl bg-cyan-50 p-3 border border-cyan-100">
                <p class="text-xs text-cyan-600">{{ $labelHargaSaatIni }}</p>
                <p class="font-black text-cyan-800 mt-1">{{ $bidTeratas ? formatRupiah($bidTeratas->jumlah_bid) : formatRupiah($ikan->harga_tertinggi) }}</p>
            </div>
            <div class="rounded-xl {{ $isMemimpin ? 'bg-emerald-50 border border-emerald-100' : 'bg-slate-50 border border-slate-100' }} p-3">
                <p class="text-xs {{ $isMemimpin ? 'text-emerald-600' : 'text-slate-500' }}">Posisi saya</p>
                <p class="font-black mt-1 {{ $isMemimpin ? 'text-emerald-700' : 'text-slate-700' }}">{{ $isMemimpin ? 'Memimpin' : 'Belum memimpin' }}</p>
            </div>
        </div>

        <div class="px-5 py-4 border-t border-slate-100">
            @if($trx && in_array($trx->status, ['gagal', 'kadaluarsa'], true))
                <div class="rounded-xl bg-red-50 border border-red-200 p-4 mb-4">
                    <p class="text-sm font-semibold text-red-800">{{ $trx->status === 'kadaluarsa' ? 'Waktu Pembayaran Habis' : 'Pembayaran Gagal' }}</p>
                    <p class="mt-1 text-xs text-red-600">Hubungi admin jika perlu bantuan.</p>
                </div>
            @endif
            <h3 class="font-bold text-slate-900 mb-3">Riwayat Bid Saya</h3>
            <ul class="divide-y divide-slate-100">
                @forelse($bidSaya as $idx => $bid)
                    <li class="py-3 flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">Bid #{{ $idx + 1 }}</p>
                            <p class="text-xs text-slate-500">{{ $bid->created_at->format('d M Y H:i:s') }}</p>
                        </div>
                        <span class="text-sm font-black text-slate-900">{{ formatRupiah($bid->jumlah_bid) }}</span>
                    </li>
                @empty
                    <li class="py-3 text-sm text-slate-500">Belum ada riwayat bid.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="space-y-4">
        <div id="status-card" class="buyer-surface bg-white rounded-3xl p-5 scroll-mt-28">
            <h3 class="font-bold text-slate-900 mb-2">Status Lot</h3>
            <p class="text-xs text-slate-500">Status saat ini</p>
            <p class="mt-1 inline-flex px-3 py-1 rounded-full text-xs font-bold {{
                $ikan->status === 'aktif' ? 'bg-green-100 text-green-700' :
                ($ikan->status === 'menunggu' ? 'bg-yellow-100 text-yellow-700' :
                ($ikan->status === 'terbayar' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600'))
            }}">{{ strtoupper($ikan->status) }}</p>

            <p class="text-xs text-slate-500 mt-3">Status transaksi</p>
            <p class="mt-1 inline-flex px-3 py-1 rounded-full text-xs font-bold {{ $buyerProgressBadgeClass }}">
                {{ $buyerProgressLabel ?? 'Belum Tersedia' }}
            </p>
            @if($buyerProgressDescription)
                <p class="text-xs text-slate-600 mt-2">{{ $buyerProgressDescription }}</p>
            @endif

            @if($trx)
                <div class="mt-3 space-y-1 text-xs text-slate-600">
                    <p>Status penjemputan: <span class="font-semibold">{{ pickupStatusLabel($trx->pickup_status) }}</span></p>
                    @if($trx->isBelumBayar() && $trx->bayar_sebelum)
                        <p>Batas bayar: <span class="font-semibold">{{ $trx->bayar_sebelum->format('d M Y H:i') }}</span></p>
                        <p>{{ humanDeadlineLabel($trx->bayar_sebelum) }}</p>
                    @endif
                    @if($trx->buyer_confirm_deadline_at)
                        <p>Deadline konfirmasi buyer: <span class="font-semibold">{{ $trx->buyer_confirm_deadline_at->format('d M Y H:i') }}</span></p>
                        <p>{{ humanDeadlineLabel($trx->buyer_confirm_deadline_at) }}</p>
                    @endif
                </div>

                <x-fulfillment-timeline :transaksi="$trx" class="mt-3" />

                @if($trx->packed_at || $trx->packing_proof)
                    <details class="mt-3 rounded-xl border border-cyan-100 bg-cyan-50 px-3 py-2">
                        <summary class="cursor-pointer list-none text-xs font-bold text-cyan-800">Detail Packing</summary>
                        @if($trx->packed_at)
                            <p class="mt-1 text-xs text-cyan-900">Dipacking: <span class="font-semibold">{{ $trx->packed_at->format('d M Y H:i') }}</span></p>
                        @endif
                        @if($trx->packing_proof)
                            <a href="{{ publicStorageUrl($trx->packing_proof) }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex items-center text-xs font-bold text-cyan-700 hover:text-cyan-800">
                                Lihat bukti packing
                            </a>
                        @endif
                    </details>
                @endif

                @if($trx->buyer_pickup_submitted_at || $trx->seller_pickup_recorded_at)
                    <details class="mt-3 rounded-xl border border-indigo-100 bg-indigo-50 px-3 py-2">
                        <summary class="cursor-pointer list-none text-xs font-bold text-indigo-800">Detail Penjemputan</summary>
                        <p class="mt-1 text-xs text-indigo-900">Sopir dari pembeli: <span class="font-semibold">{{ $trx->buyer_pickup_name ?: '-' }}</span></p>
                        <p class="mt-1 text-xs text-indigo-900">Plat dari pembeli: <span class="break-all font-semibold">{{ $trx->buyer_pickup_plate_number ?: '-' }}</span></p>
                        <p class="mt-1 text-xs text-indigo-900">Status: <span class="font-semibold">{{ pickupStatusLabel($trx->pickup_status) }}</span></p>
                        @if($trx->seller_pickup_recorded_at)
                            <p class="mt-1 text-xs text-indigo-900">Divalidasi penjual: <span class="font-semibold">{{ $trx->seller_pickup_recorded_at->format('d M Y H:i') }}</span></p>
                        @endif
                    </details>
                @endif

                <x-fulfillment-photo-grid :transaksi="$trx" title="Foto Packing & Penjemputan" />
            @endif

            <p class="text-xs text-slate-500 mt-3">Berakhir</p>
            <p class="text-sm font-semibold text-slate-800">{{ $ikan->waktu_selesai->format('d M Y H:i') }}</p>
        </div>

        <div id="action-card" class="buyer-surface bg-white rounded-3xl p-5 space-y-2 scroll-mt-28">
            <x-secondary-action-link :href="route('ikans.show', ['ikan' => $ikan, 'return_url' => $currentPageUrl])" class="flex w-full">
                Buka Detail Lot
            </x-secondary-action-link>

            @if($isPemenang && $ikan->transaksi && $ikan->transaksi->isBelumBayar())
                <a href="{{ $paymentUrl }}" class="buyer-priority-cta-pay inline-flex w-full items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-extrabold tracking-wide text-white">
                    <span class="inline-flex h-2.5 w-2.5 rounded-full bg-white shadow-[0_0_0_3px_rgba(255,255,255,0.25)]" aria-hidden="true"></span>
                    Lanjut Bayar
                </a>
            @endif

            @if($isPemenang && $trx && $trx->isLunas() && ! $trx->packed_at && ! $trx->buyer_pickup_submitted_at)
                <div class="rounded-xl border border-amber-100 bg-amber-50 p-4">
                    <p class="text-sm font-bold text-amber-800">Menunggu Konfirmasi Packing</p>
                    <p class="mt-1 text-xs text-amber-700">Data penjemput bisa diisi setelah lot siap dijemput.</p>
                </div>
            @elseif($isPemenang && $trx && $trx->isLunas() && $trx->packed_at && ! $trx->buyer_pickup_submitted_at)
                <form id="pickup-form" action="{{ route('pembeli.ikans.pickup', $ikan) }}" method="POST" enctype="multipart/form-data" class="rounded-xl border border-cyan-100 bg-cyan-50 p-4 space-y-3">
                    @csrf
                    <input type="hidden" name="return_url" value="{{ $currentPageUrl }}">
                    <p class="text-sm font-bold text-cyan-800">Isi Data Penjemput</p>
                    <input type="text" name="buyer_pickup_name" required autocomplete="name" class="w-full border border-cyan-200 rounded-lg px-3 py-3 text-base bg-white" placeholder="Nama sopir">
                    <input type="text" name="buyer_pickup_plate_number" required inputmode="text" autocapitalize="characters" class="w-full border border-cyan-200 rounded-lg px-3 py-3 text-base bg-white" placeholder="Plat nomor">
                    <x-image-upload-preview
                        name="buyer_pickup_photo"
                        label="Foto Sopir Penjemput"
                        required
                        hint="Upload foto sopir yang akan datang ke lokasi penjual. Format gambar, maksimal 3 MB."
                        label-class="text-xs text-cyan-700 font-semibold"
                        hint-class="mt-1 text-[11px] text-cyan-700"
                        input-class="w-full mt-1 min-h-[46px] text-base"
                    />
                    <x-image-upload-preview
                        name="buyer_pickup_vehicle_photo"
                        label="Foto Kendaraan Penjemput"
                        required
                        hint="Upload foto kendaraan yang dipakai penjemput, termasuk bagian plat nomor jika memungkinkan. Format gambar, maksimal 3 MB."
                        label-class="text-xs text-cyan-700 font-semibold"
                        hint-class="mt-1 text-[11px] text-cyan-700"
                        input-class="w-full mt-1 min-h-[46px] text-base"
                    />
                    <textarea name="buyer_pickup_notes" rows="2" class="w-full border border-cyan-200 rounded-lg px-3 py-3 text-base bg-white" placeholder="Catatan opsional"></textarea>
                    <button type="submit" class="w-full rounded-xl bg-cyan-700 px-4 py-3 text-[15px] font-bold text-white hover:bg-cyan-800">Simpan Data Penjemput</button>
                </form>
            @elseif($isPemenang && $trx && $trx->pickup_status === 'pickup_arrived')
                <form id="confirm-finish-form" action="{{ route('pembeli.ikans.diterima', $ikan) }}" method="POST" class="rounded-xl border border-emerald-100 bg-emerald-50 p-4 space-y-3">
                    @csrf
                    <input type="hidden" name="return_url" value="{{ $currentPageUrl }}">
                    <p class="text-sm font-bold text-emerald-800">Review dan Konfirmasi Selesai</p>
                    <select name="buyer_rating" class="w-full border border-emerald-200 rounded-lg px-3 py-3 text-base bg-white">
                        <option value="">Rating barang (opsional)</option>
                        @for($rating = 5; $rating >= 1; $rating--)
                            <option value="{{ $rating }}">{{ $rating }} bintang</option>
                        @endfor
                    </select>
                    <textarea name="buyer_review" rows="2" class="w-full border border-emerald-200 rounded-lg px-3 py-3 text-base bg-white" placeholder="Review barang (opsional)"></textarea>
                    <button type="submit" class="w-full rounded-xl bg-emerald-600 px-4 py-3 text-[15px] font-bold text-white hover:bg-emerald-700">Konfirmasi Selesai</button>
                </form>
            @elseif($isPemenang && $trx && $trx->isLunas() && $trx->buyer_pickup_submitted_at)
                <div class="rounded-xl border border-indigo-100 bg-indigo-50 p-4">
                    <p class="text-sm font-bold text-indigo-800">Data penjemput sudah tersimpan</p>
                    <p class="mt-1 text-xs text-indigo-700">Penjual akan mencocokkan data sopir dan kendaraan saat penjemput tiba di lokasi.</p>
                </div>
            @endif

            @if($latestDispute)
                <div class="mt-2 rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs font-bold text-slate-700">Status Sengketa Terbaru</p>
                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ strtoupper((string) $latestDispute->status) }}</p>
                    <p class="text-xs text-slate-600 mt-1">Alasan: {{ str_replace('_', ' ', (string) $latestDispute->complaint_reason) }}</p>
                    @if($latestDispute->resolution_note)
                        <p class="text-xs text-slate-600 mt-1">Catatan admin: {{ $latestDispute->resolution_note }}</p>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>

@endsection
