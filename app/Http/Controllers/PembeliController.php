<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\OptimizesStoredImages;
use App\Models\Bid;
use App\Models\Ikan;
use App\Models\Transaksi;
use App\Services\NotificationOutboxService;
use App\Services\TransaksiFulfillmentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class PembeliController extends Controller
{
    use OptimizesStoredImages;

    public function __construct(private TransaksiFulfillmentService $fulfillment) {}

    public function aktivitas(Request $request)
    {
        $tipeLelang = $request->query('tipe_lelang', 'semua');
        if (! in_array($tipeLelang, ['semua', 'naik', 'turun'], true)) {
            $tipeLelang = 'semua';
        }

        $fokus = $request->query('fokus', 'semua');
        if (! in_array($fokus, ['semua', 'lot_diikuti', 'memimpin_aktif', 'sudah_lunas', 'tagihan_berjalan'], true)) {
            $fokus = 'semua';
        }

        $userId = (int) auth()->id();
        $isCompletedPurchase = fn (?Transaksi $trx): bool => $this->isCompletedPurchase($trx);

        $latestBidIdsPerLot = Bid::query()
            ->selectRaw('MAX(id) as id')
            ->where('user_id', $userId)
            ->groupBy('ikan_id');

        $bidsQuery = Bid::query()
            ->whereIn('id', $latestBidIdsPerLot)
            ->with([
                'ikan' => function ($query) use ($userId): void {
                    $query->select('ikans.*')
                        ->with(['user', 'transaksi'])
                        ->withCount('bids')
                        ->addSelect([
                            'best_bidder_id' => Bid::query()
                                ->select('user_id')
                                ->whereColumn('ikan_id', 'ikans.id')
                                ->orderByDesc('jumlah_bid')
                                ->orderByDesc('id')
                                ->limit(1),
                            'best_bid_amount' => Bid::query()
                                ->select('jumlah_bid')
                                ->whereColumn('ikan_id', 'ikans.id')
                                ->orderByDesc('jumlah_bid')
                                ->orderByDesc('id')
                                ->limit(1),
                            'my_best_bid' => Bid::query()
                                ->selectRaw('MAX(jumlah_bid)')
                                ->whereColumn('ikan_id', 'ikans.id')
                                ->where('user_id', $userId),
                        ]);
                },
            ]);

        if ($tipeLelang !== 'semua') {
            $bidsQuery->whereHas('ikan', fn ($query) => $query->where('tipe_lelang', $tipeLelang));
        }

        $bids = $bidsQuery
            ->orderByDesc('created_at')
            ->get();

        $riwayat = $bids->values();
        $riwayatValid = $riwayat->filter(fn ($bid) => $bid->ikan)->values();

        $isBelumBayar = function ($bid) use ($userId): bool {
            $trx = $bid->ikan->transaksi;

            return $trx
                && (int) $trx->pemenang_id === $userId
                && $trx->status === 'menunggu_bayar';
        };

        $isSudahBayar = function ($bid) use ($userId): bool {
            $trx = $bid->ikan->transaksi;

            return $trx
                && (int) $trx->pemenang_id === $userId
                && $trx->status === 'lunas'
                && ! $this->isCompletedPurchase($trx);
        };

        $belumBayar = $riwayatValid->filter($isBelumBayar)->values();
        $sudahBayar = $riwayatValid->filter($isSudahBayar)->values();
        $aktivitasLainnya = $riwayatValid
            ->reject(fn ($bid) => $isCompletedPurchase($bid->ikan?->transaksi) || $isBelumBayar($bid) || $isSudahBayar($bid))
            ->values();

        $sortByPaymentDeadlinePriority = function (Bid $bid): int {
            return $bid->ikan?->transaksi?->bayar_sebelum?->timestamp ?? PHP_INT_MAX;
        };

        $isLewatBayar = function (?Transaksi $trx): bool {
            if (! $trx) {
                return false;
            }

            if ($trx->status === 'kadaluarsa') {
                return true;
            }

            return $trx->status === 'menunggu_bayar'
                && $trx->bayar_sebelum !== null
                && now()->gt($trx->bayar_sebelum);
        };

        $lotDiikutiAktif = $riwayatValid
            ->filter(fn (Bid $bid): bool => (string) ($bid->ikan?->status ?? '') === 'aktif')
            ->sortBy(fn (Bid $bid): int => $bid->ikan?->waktu_selesai?->timestamp ?? PHP_INT_MAX)
            ->values();

        $lotDiikutiSelesai = $riwayatValid
            ->filter(fn (Bid $bid): bool => (string) ($bid->ikan?->status ?? '') !== 'aktif' && ! $isCompletedPurchase($bid->ikan?->transaksi))
            ->sortByDesc(fn (Bid $bid): int => $bid->ikan?->waktu_selesai?->timestamp ?? 0)
            ->values();

        $memimpinAktifItems = $riwayatValid
            ->filter(function (Bid $bid) use ($userId): bool {
                $ikan = $bid->ikan;
                if (! $ikan || $ikan->status !== 'aktif') {
                    return false;
                }

                return (int) ($ikan->best_bidder_id ?? 0) === $userId;
            })
            ->sortBy(fn (Bid $bid): int => $bid->ikan?->waktu_selesai?->timestamp ?? PHP_INT_MAX)
            ->values();

        $memimpinSelesaiItems = $riwayatValid
            ->filter(function (Bid $bid) use ($userId): bool {
                $ikan = $bid->ikan;
                if (! $ikan || $ikan->status === 'aktif') {
                    return false;
                }

                return (int) ($ikan->best_bidder_id ?? 0) === $userId
                    && ! $this->isCompletedPurchase($ikan->transaksi);
            })
            ->sortByDesc(fn (Bid $bid): int => $bid->ikan?->waktu_selesai?->timestamp ?? 0)
            ->values();

        $sudahLunasItems = $sudahBayar
            ->sort(function (Bid $a, Bid $b): int {
                $stageRank = function (?Transaksi $trx): int {
                    if (! $trx) {
                        return 99;
                    }

                    if (in_array((string) $trx->pickup_status, ['awaiting_pickup', 'pickup_arrived'], true)) {
                        return 1;
                    }

                    return 0;
                };

                $trxA = $a->ikan?->transaksi;
                $trxB = $b->ikan?->transaksi;

                $rankA = $stageRank($trxA);
                $rankB = $stageRank($trxB);

                if ($rankA !== $rankB) {
                    return $rankA <=> $rankB;
                }

                $timeA = $trxA?->dibayar_pada?->timestamp ?? ($trxA?->created_at?->timestamp ?? 0);
                $timeB = $trxB?->dibayar_pada?->timestamp ?? ($trxB?->created_at?->timestamp ?? 0);

                return $timeB <=> $timeA;
            })
            ->values();

        $tagihanHarusBayarItems = $riwayatValid
            ->filter(function (Bid $bid) use ($userId, $isLewatBayar): bool {
                $trx = $bid->ikan?->transaksi;

                return $trx
                    && (int) $trx->pemenang_id === $userId
                    && $trx->status === 'menunggu_bayar'
                    && ! $isLewatBayar($trx);
            })
            ->sortBy($sortByPaymentDeadlinePriority)
            ->values();

        $tagihanLewatBayarItems = $riwayatValid
            ->filter(function (Bid $bid) use ($userId, $isLewatBayar): bool {
                $trx = $bid->ikan?->transaksi;

                return $trx
                    && (int) $trx->pemenang_id === $userId
                    && $isLewatBayar($trx);
            })
            ->sortBy($sortByPaymentDeadlinePriority)
            ->values();

        $memimpinAktif = $riwayatValid
            ->filter(function (Bid $bid) use ($userId): bool {
                $ikan = $bid->ikan;
                if (! $ikan || $ikan->status !== 'aktif') {
                    return false;
                }

                return (int) ($ikan->best_bidder_id ?? 0) === $userId;
            })
            ->count();

        $perluKonfirmasiTerima = $riwayatValid
            ->filter(function (Bid $bid) use ($userId): bool {
                $trx = $bid->ikan?->transaksi;

                return $trx
                    && (int) $trx->pemenang_id === $userId
                    && $trx->status === 'lunas'
                    && (string) $trx->pickup_status === 'pickup_arrived';
            })
            ->values();

        $buyerStats = [
            'total_lot_diikuti' => $riwayatValid->count(),
            'memimpin_aktif' => $memimpinAktif,
            'menunggu_bayar' => $belumBayar->count(),
            'sudah_lunas' => $sudahBayar->count(),
            'perlu_konfirmasi_terima' => $perluKonfirmasiTerima->count(),
            'nilai_belum_bayar' => (float) $belumBayar->sum(fn (Bid $bid) => (float) ($bid->ikan?->transaksi?->harga_final ?? 0)),
        ];

        $aksiPrioritas = [
            'bayar_segera' => $belumBayar
                ->sortBy(fn (Bid $bid) => $bid->ikan?->transaksi?->bayar_sebelum?->timestamp ?? PHP_INT_MAX)
                ->take(5)
                ->values(),
            'konfirmasi_terima' => $perluKonfirmasiTerima
                ->sortBy(fn (Bid $bid) => $bid->ikan?->transaksi?->seller_pickup_recorded_at?->timestamp ?? PHP_INT_MAX)
                ->take(5)
                ->values(),
        ];

        $transaksiSayaQuery = Transaksi::query()
            ->where('pemenang_id', $userId)
            ->with(['ikan.user']);

        if ($tipeLelang !== 'semua') {
            $transaksiSayaQuery->whereHas('ikan', fn ($query) => $query->where('tipe_lelang', $tipeLelang));
        }

        $transaksiSaya = $transaksiSayaQuery
            ->latest('created_at')
            ->get()
            ->filter(fn (Transaksi $trx) => $trx->ikan !== null)
            ->values();

        $isPipelineSelesai = function (Transaksi $trx): bool {
            return ! $this->isCompletedPurchase($trx)
                && (string) $trx->pickup_status === 'pickup_arrived';
        };

        $isPipelinePenjemputan = function (Transaksi $trx) use ($isPipelineSelesai): bool {
            if ($this->isCompletedPurchase($trx) || $isPipelineSelesai($trx)) {
                return false;
            }

            return (string) $trx->pickup_status === 'awaiting_pickup';
        };

        $pipelineBayar = $transaksiSaya
            ->filter(function (Transaksi $trx) use ($isPipelinePenjemputan, $isPipelineSelesai): bool {
                if ($this->isCompletedPurchase($trx) || $isPipelineSelesai($trx) || $isPipelinePenjemputan($trx)) {
                    return false;
                }

                return in_array((string) $trx->status, ['menunggu_bayar', 'lunas'], true);
            })
            ->values();

        $pipelinePenjemputan = $transaksiSaya
            ->filter($isPipelinePenjemputan)
            ->values();

        $pipelineSelesai = $transaksiSaya
            ->filter($isPipelineSelesai)
            ->values();

        $defaultSections = [
            [
                'title' => 'Belum Bayar',
                'titleShort' => 'Belum Bayar',
                'items' => $belumBayar,
                'showPayAction' => true,
                'showExpiredBadge' => false,
                'containerClass' => 'border-amber-200/80 bg-amber-50/30',
                'badgeClass' => 'bg-amber-100 text-amber-700',
                'emptyText' => 'Belum ada lot menunggu pembayaran pada filter ini.',
            ],
            [
                'title' => 'Sudah Bayar',
                'titleShort' => 'Sudah Bayar',
                'items' => $sudahBayar,
                'showPayAction' => false,
                'showExpiredBadge' => false,
                'containerClass' => 'border-emerald-200/80 bg-emerald-50/30',
                'badgeClass' => 'bg-emerald-100 text-emerald-700',
                'emptyText' => 'Belum ada lot yang sudah dibayar pada filter ini.',
            ],
            [
                'title' => 'Aktivitas Bid Lainnya',
                'titleShort' => 'Lainnya',
                'items' => $aktivitasLainnya,
                'showPayAction' => false,
                'showExpiredBadge' => false,
                'containerClass' => 'border-slate-200/80 bg-slate-50/40',
                'badgeClass' => 'bg-slate-100 text-slate-700',
                'emptyText' => 'Belum ada aktivitas lain pada filter ini.',
            ],
        ];

        $sections = match ($fokus) {
            'lot_diikuti' => [
                [
                    'title' => 'Lot Diikuti - Aktif',
                    'titleShort' => 'Aktif',
                    'items' => $lotDiikutiAktif,
                    'showPayAction' => false,
                    'showExpiredBadge' => false,
                    'containerClass' => 'border-emerald-200/80 bg-emerald-50/30',
                    'badgeClass' => 'bg-emerald-100 text-emerald-700',
                    'emptyText' => 'Belum ada lot aktif yang sedang Anda ikuti.',
                ],
                [
                    'title' => 'Lot Diikuti - Selesai',
                    'titleShort' => 'Selesai',
                    'items' => $lotDiikutiSelesai,
                    'showPayAction' => false,
                    'showExpiredBadge' => false,
                    'containerClass' => 'border-slate-200/80 bg-slate-50/40',
                    'badgeClass' => 'bg-slate-100 text-slate-700',
                    'emptyText' => 'Belum ada lot selesai dalam riwayat Anda.',
                ],
            ],
            'memimpin_aktif' => [
                [
                    'title' => 'Memimpin - Lot Aktif',
                    'titleShort' => 'Aktif',
                    'items' => $memimpinAktifItems,
                    'showPayAction' => false,
                    'showExpiredBadge' => false,
                    'containerClass' => 'border-emerald-200/80 bg-emerald-50/30',
                    'badgeClass' => 'bg-emerald-100 text-emerald-700',
                    'emptyText' => 'Saat ini belum ada lot aktif yang Anda pimpin.',
                ],
                [
                    'title' => 'Memimpin - Lot Selesai',
                    'titleShort' => 'Selesai',
                    'items' => $memimpinSelesaiItems,
                    'showPayAction' => false,
                    'showExpiredBadge' => false,
                    'containerClass' => 'border-cyan-200/80 bg-cyan-50/30',
                    'badgeClass' => 'bg-cyan-100 text-cyan-700',
                    'emptyText' => 'Belum ada lot selesai yang pernah Anda pimpin.',
                ],
            ],
            'sudah_lunas' => [
                [
                    'title' => 'Lot Sudah Lunas',
                    'titleShort' => 'Lunas',
                    'items' => $sudahLunasItems,
                    'showPayAction' => false,
                    'showExpiredBadge' => false,
                    'containerClass' => 'border-emerald-200/80 bg-emerald-50/30',
                    'badgeClass' => 'bg-emerald-100 text-emerald-700',
                    'emptyText' => 'Belum ada lot yang sudah Anda lunasi.',
                ],
            ],
            'tagihan_berjalan' => [
                [
                    'title' => 'Harus Dibayar (Prioritas)',
                    'titleShort' => 'Prioritas',
                    'items' => $tagihanHarusBayarItems,
                    'showPayAction' => true,
                    'showExpiredBadge' => false,
                    'containerClass' => 'border-amber-200/80 bg-amber-50/30',
                    'badgeClass' => 'bg-amber-100 text-amber-700',
                    'emptyText' => 'Tidak ada tagihan aktif yang perlu dibayar saat ini.',
                ],
                [
                    'title' => 'Lewat Tenggat Bayar',
                    'titleShort' => 'Lewat Tenggat',
                    'items' => $tagihanLewatBayarItems,
                    'showPayAction' => false,
                    'showExpiredBadge' => true,
                    'containerClass' => 'border-rose-200/80 bg-rose-50/30',
                    'badgeClass' => 'bg-rose-100 text-rose-700',
                    'emptyText' => 'Belum ada lot yang melewati tenggat pembayaran.',
                ],
            ],
            default => $defaultSections,
        };

        return view('pembeli.aktivitas', [
            'riwayat' => $riwayat,
            'belumBayar' => $belumBayar,
            'sudahBayar' => $sudahBayar,
            'aktivitasLainnya' => $aktivitasLainnya,
            'fokus' => $fokus,
            'sections' => $sections,
            'tipeLelang' => $tipeLelang,
            'buyerStats' => $buyerStats,
            'aksiPrioritas' => $aksiPrioritas,
            'pipelineBayar' => $pipelineBayar,
            'pipelinePenjemputan' => $pipelinePenjemputan,
            'pipelineSelesai' => $pipelineSelesai,
        ]);
    }

    public function riwayatPembelian(Request $request)
    {
        $tipeLelang = $request->query('tipe_lelang', 'semua');
        if (! in_array($tipeLelang, ['semua', 'naik', 'turun'], true)) {
            $tipeLelang = 'semua';
        }

        $riwayatPembelian = Transaksi::query()
            ->where('pemenang_id', (int) auth()->id())
            ->where(function ($query): void {
                $this->applyCompletedPurchaseScope($query);
            })
            ->with(['ikan.user'])
            ->when($tipeLelang !== 'semua', fn ($query) => $query->whereHas('ikan', fn ($ikanQuery) => $ikanQuery->where('tipe_lelang', $tipeLelang)))
            ->orderByRaw('COALESCE(completed_by_buyer_at, buyer_reviewed_at, pickup_verified_at, updated_at) DESC')
            ->paginate(10)
            ->withQueryString();

        $summaryQuery = Transaksi::query()
            ->where('pemenang_id', (int) auth()->id())
            ->where(function ($query): void {
                $this->applyCompletedPurchaseScope($query);
            })
            ->when($tipeLelang !== 'semua', fn ($query) => $query->whereHas('ikan', fn ($ikanQuery) => $ikanQuery->where('tipe_lelang', $tipeLelang)));

        $historyStats = [
            'total' => (clone $summaryQuery)->count(),
            'nilai' => (float) (clone $summaryQuery)->sum('harga_final'),
            'rated' => (clone $summaryQuery)->whereNotNull('buyer_rating')->count(),
        ];

        return view('pembeli.riwayat', [
            'riwayatPembelian' => $riwayatPembelian,
            'historyStats' => $historyStats,
            'tipeLelang' => $tipeLelang,
        ]);
    }

    public function aktivitasDetail(Ikan $ikan)
    {
        $userId = (int) auth()->id();

        $punyaAktivitas = Bid::query()
            ->where('ikan_id', $ikan->id)
            ->where('user_id', $userId)
            ->exists();

        if (! $punyaAktivitas) {
            abort(403, 'Anda tidak memiliki aktivitas bid pada lot ini.');
        }

        $ikan->load([
            'user',
            'transaksi.pemenang',
            'transaksi.disputes',
            'bids' => fn ($query) => $query->with('user')->latest('created_at'),
        ]);

        $bidSaya = $ikan->bids
            ->where('user_id', $userId)
            ->sortByDesc('created_at')
            ->values();

        $bestBidSaya = $ikan->isLelangTurun()
            ? $bidSaya->max('jumlah_bid')
            : $bidSaya->max('jumlah_bid');

        $bidTeratas = $ikan->isLelangTurun()
            ? $ikan->bids->sortByDesc('jumlah_bid')->first()
            : $ikan->bids->sortByDesc('jumlah_bid')->first();

        $isMemimpin = $bidTeratas?->user_id === $userId;
        $isPemenang = $ikan->transaksi && (int) $ikan->transaksi->pemenang_id === $userId;

        return view('pembeli.aktivitas-detail', [
            'ikan' => $ikan,
            'bidSaya' => $bidSaya,
            'bestBidSaya' => $bestBidSaya,
            'bidTeratas' => $bidTeratas,
            'isMemimpin' => $isMemimpin,
            'isPemenang' => $isPemenang,
            'latestDispute' => $ikan->transaksi?->disputes?->first(),
        ]);
    }

    public function penilaian(Ikan $ikan)
    {
        $userId = (int) auth()->id();

        $ikan->load([
            'user',
            'transaksi.pemenang',
        ]);

        $transaksi = $ikan->transaksi;

        if (! $transaksi || (int) $transaksi->pemenang_id !== $userId) {
            abort(403, 'Halaman penilaian hanya tersedia untuk pemenang lot ini.');
        }

        $canSubmitReview = $transaksi->isLunas()
            && (string) $transaksi->pickup_status === 'pickup_arrived'
            && $transaksi->completed_by_buyer_at === null;

        return view('pembeli.penilaian', [
            'ikan' => $ikan,
            'transaksi' => $transaksi,
            'canSubmitReview' => $canSubmitReview,
        ]);
    }

    public function submitPickup(Request $request, Ikan $ikan)
    {
        $returnUrl = $this->safeReturnUrl(
            $request->input('return_url'),
            route('pembeli.aktivitas.detail', $ikan)
        );

        $transaksi = $ikan->transaksi;

        if (! $transaksi || (int) $transaksi->pemenang_id !== (int) auth()->id()) {
            abort(403);
        }

        if (! $transaksi->isLunas()) {
            return redirect()->to($returnUrl)->with('error', 'Data penjemput hanya bisa diisi setelah pembayaran sukses.');
        }

        if (! $transaksi->packed_at) {
            return redirect()->to($returnUrl)->with('error', 'Data penjemput baru bisa diisi setelah penjual mengonfirmasi packing.');
        }

        $validator = Validator::make($request->all(), [
            'buyer_pickup_name' => ['required', 'string', 'max:150'],
            'buyer_pickup_plate_number' => ['required', 'string', 'max:32'],
            'buyer_pickup_photo' => ['required', 'image', 'max:3072'],
            'buyer_pickup_vehicle_photo' => ['required', 'image', 'max:3072'],
            'buyer_pickup_notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return redirect()->to($returnUrl)
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        try {
            DB::transaction(function () use ($request, $transaksi, $validated): void {
                $trx = Transaksi::query()->lockForUpdate()->findOrFail($transaksi->id);

                $driverPhotoPath = $trx->buyer_pickup_photo;
                if ($request->hasFile('buyer_pickup_photo')) {
                    if ($driverPhotoPath) {
                        Storage::disk('public')->delete($driverPhotoPath);
                    }

                    $driverPhotoPath = $this->storePublicUpload($request->file('buyer_pickup_photo'), 'pickup-proof', 'buyer_pickup_photo');
                }

                $vehiclePhotoPath = $trx->buyer_pickup_vehicle_photo;
                if ($request->hasFile('buyer_pickup_vehicle_photo')) {
                    if ($vehiclePhotoPath) {
                        Storage::disk('public')->delete($vehiclePhotoPath);
                    }

                    $vehiclePhotoPath = $this->storePublicUpload($request->file('buyer_pickup_vehicle_photo'), 'pickup-proof', 'buyer_pickup_vehicle_photo');
                }

                $trx->markBuyerPickupSubmitted(
                    (string) $validated['buyer_pickup_name'],
                    strtoupper((string) $validated['buyer_pickup_plate_number']),
                    $driverPhotoPath,
                    $vehiclePhotoPath,
                    $validated['buyer_pickup_notes'] ?? null
                );
                $trx->save();
                $trx->ikan?->increment('state_version');
            }, 3);

            app(NotificationOutboxService::class)->queue(
                (int) ($ikan->user_id ?? 0),
                'penjemputan',
                'Data penjemput pembeli masuk',
                'Pembeli sudah mengisi nama sopir, plat nomor, foto sopir, dan foto kendaraan penjemput.',
                ['ikan_id' => $ikan->id, 'transaksi_id' => $transaksi->id],
                'pickup-submitted:' . $transaksi->id
            );
            app(NotificationOutboxService::class)->processPending(50);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return redirect()->to($returnUrl)->with('error', 'Data penjemput gagal disimpan. Silakan coba lagi.');
        }

        return redirect()->to($returnUrl)->with('sukses', 'Data penjemput berhasil disimpan.');
    }

    public function konfirmasiDiterima(Request $request, Ikan $ikan)
    {
        $returnUrl = $this->safeReturnUrl(
            request()->input('return_url'),
            route('ikans.show', $ikan)
        );

        $transaksi = $ikan->transaksi;

        if (! $transaksi || (int) $transaksi->pemenang_id !== (int) auth()->id()) {
            abort(403);
        }

        if (! $transaksi->isLunas()) {
            return redirect()->to($returnUrl)->with('error', 'Transaksi belum dibayar.');
        }

        if (in_array($transaksi->fulfillment_state, ['DISENGKETAKAN', 'GAGAL'], true)) {
            return redirect()->to($returnUrl)->with('error', 'Transaksi sedang bermasalah, konfirmasi diterima dinonaktifkan sementara.');
        }

        if (! in_array((string) $transaksi->pickup_status, ['pickup_arrived', 'completed'], true)) {
            return redirect()->to($returnUrl)->with('error', 'Penjual belum menandai penjemput datang.');
        }

        $validated = $request->validate([
            'buyer_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'buyer_review' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($transaksi, $validated): void {
            $trx = Transaksi::query()->lockForUpdate()->findOrFail($transaksi->id);

            if (! $trx->isLunas()) {
                return;
            }

            $trx->markDiterima(
                isset($validated['buyer_rating']) ? (int) $validated['buyer_rating'] : null,
                $validated['buyer_review'] ?? null
            );
            $trx->save();

            if ($trx->ikan) {
                $trx->ikan->increment('state_version');
            }
        }, 3);

        $this->fulfillment->markCompletedByBuyer($transaksi->fresh(), (int) auth()->id());
        app(NotificationOutboxService::class)->processPending(50);

        return redirect()->to($returnUrl)->with('sukses', 'Konfirmasi selesai berhasil dikirim.');
    }

    public function ajukanKomplain(Request $request, Ikan $ikan)
    {
        $returnUrl = $this->safeReturnUrl(
            $request->input('return_url'),
            route('pembeli.aktivitas.detail', $ikan)
        );

        $transaksi = $ikan->transaksi;

        if (! $transaksi || (int) $transaksi->pemenang_id !== (int) auth()->id()) {
            abort(403);
        }

        if (! in_array((string) $transaksi->fulfillment_state, ['DIBAYAR', 'DIPROSES_PENJUAL', 'DIKIRIM'], true)) {
            return redirect()->to($returnUrl)->with('error', 'Komplain hanya bisa diajukan pada transaksi yang sedang berjalan.');
        }

        $validator = Validator::make($request->all(), [
            'complaint_reason' => 'required|string|in:barang_tidak_sesuai,penjemput_belum_datang,data_penjemput_bermasalah,lainnya',
            'complaint_detail' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return redirect()->to($returnUrl)
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        try {
            $this->fulfillment->createBuyerDispute(
                $transaksi,
                (int) auth()->id(),
                (string) $validated['complaint_reason'],
                isset($validated['complaint_detail']) ? (string) $validated['complaint_detail'] : null,
            );
            app(NotificationOutboxService::class)->processPending(50);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->to($returnUrl)->with('error', 'Komplain gagal diproses. Silakan coba lagi.');
        }

        return redirect()->to($returnUrl)->with('sukses', 'Komplain berhasil diajukan dan sedang ditinjau admin.');
    }

    private function safeReturnUrl(mixed $candidate, string $fallback): string
    {
        return safeInternalReturnUrl($candidate, $fallback);
    }

    private function isCompletedPurchase(?Transaksi $transaksi): bool
    {
        if (! $transaksi) {
            return false;
        }

        return $transaksi->completed_by_buyer_at !== null
            || (string) $transaksi->pickup_status === 'completed'
            || (string) $transaksi->fulfillment_state === 'SELESAI';
    }

    private function applyCompletedPurchaseScope($query): void
    {
        $query->whereNotNull('completed_by_buyer_at')
            ->orWhere('pickup_status', 'completed')
            ->orWhere('fulfillment_state', 'SELESAI');
    }

    private function storePublicUpload(UploadedFile $file, string $directory, string $fieldName): string
    {
        $disk = Storage::disk('public');

        try {
            if (! $disk->exists($directory)) {
                $disk->makeDirectory($directory);
            }

            $storedPath = $file->store($directory, 'public');
            if (! is_string($storedPath) || $storedPath === '') {
                throw new \RuntimeException('Uploaded file path is empty.');
            }

            if (str_starts_with((string) $file->getMimeType(), 'image/')) {
                $this->optimizeStoredImage($storedPath);
            }

            return $storedPath;
        } catch (Throwable $e) {
            report($e);

            throw ValidationException::withMessages([
                $fieldName => 'Upload gagal di server. Pastikan penyimpanan dapat ditulis, lalu coba lagi.',
            ]);
        }
    }
}
