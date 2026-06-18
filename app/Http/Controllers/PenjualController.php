<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\OptimizesStoredImages;
use App\Models\Ikan;
use App\Models\SellerSettlement;
use App\Models\Transaksi;
use App\Models\User;
use App\Services\LelangService;
use App\Services\NotificationOutboxService;
use App\Services\TransaksiFulfillmentService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class PenjualController extends Controller
{
    use OptimizesStoredImages;

    public function __construct(
        private LelangService $lelang,
        private TransaksiFulfillmentService $fulfillment,
    ) {}

    public function dashboard(Request $request)
    {
        $seller = $request->user()->loadMissing('sellerProfile');

        $completedSales = Transaksi::query()
            ->whereHas('ikan', fn ($query) => $query->where('user_id', (int) $seller->id))
            ->where(function ($query): void {
                $this->applyCompletedSaleScope($query);
            })
            ->with(['ikan', 'pemenang'])
            ->orderByRaw('COALESCE(completed_by_buyer_at, buyer_reviewed_at, pickup_verified_at, updated_at) DESC')
            ->paginate(10)
            ->withQueryString();

        $summaryQuery = Transaksi::query()
            ->whereHas('ikan', fn ($query) => $query->where('user_id', (int) $seller->id))
            ->where(function ($query): void {
                $this->applyCompletedSaleScope($query);
            });

        $ratingQuery = (clone $summaryQuery)->whereNotNull('buyer_rating');
        $ratingCount = (clone $ratingQuery)->count();

        $sellerDashboardStats = [
            'total_selesai' => (clone $summaryQuery)->count(),
            'nilai_selesai' => (float) (clone $summaryQuery)->sum('harga_final'),
            'rating_average' => $ratingCount > 0
                ? round((float) (clone $ratingQuery)->avg('buyer_rating'), 1)
                : null,
            'rating_count' => $ratingCount,
        ];

        $settlementBaseQuery = SellerSettlement::query()
            ->where('seller_id', (int) $seller->id);

        $sellerSettlementStats = [
            'pending' => (clone $settlementBaseQuery)->where('status', 'pending')->count(),
            'held' => (clone $settlementBaseQuery)->where('status', 'held')->count(),
            'ready_to_pay' => (clone $settlementBaseQuery)->where('status', 'ready_to_pay')->count(),
            'paid' => (clone $settlementBaseQuery)->where('status', 'paid')->count(),
            'outstanding_amount' => (float) (clone $settlementBaseQuery)
                ->whereIn('status', ['pending', 'held', 'ready_to_pay'])
                ->sum('amount'),
            'paid_amount' => (float) (clone $settlementBaseQuery)
                ->where('status', 'paid')
                ->sum('amount'),
        ];

        $recentSettlements = (clone $settlementBaseQuery)
            ->with(['transaksi.ikan'])
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return view('penjual.dashboard', [
            'seller' => $seller,
            'sellerProfile' => $seller->sellerProfile,
            'completedSales' => $completedSales,
            'sellerDashboardStats' => $sellerDashboardStats,
            'sellerSettlementStats' => $sellerSettlementStats,
            'recentSettlements' => $recentSettlements,
        ]);
    }

    public function publicDashboard(User $seller)
    {
        if ($seller->isDeleted() || $seller->isBanned()) {
            abort(404);
        }

        $seller->loadMissing('sellerProfile');

        $hasStorePresence = $seller->sellerProfile !== null || $seller->ikans()->exists();
        abort_unless($hasStorePresence, 404);

        $this->lelang->aktifkanYangBelumMulai();
        $this->lelang->cekDanTutupSemua();

        $activeLots = Ikan::query()
            ->where('user_id', (int) $seller->id)
            ->with('transaksi')
            ->withCount('bids')
            ->whereIn('status', ['aktif', 'menunggu'])
            ->orderByRaw("CASE status WHEN 'aktif' THEN 0 WHEN 'menunggu' THEN 1 ELSE 2 END")
            ->orderBy('waktu_selesai')
            ->paginate(8, ['*'], 'lotPage')
            ->withQueryString();

        $completedLotBaseQuery = Ikan::query()
            ->where('user_id', (int) $seller->id)
            ->with('transaksi')
            ->withCount('bids')
            ->whereHas('transaksi', function ($query): void {
                $this->applyCompletedSaleScope($query);
            });

        $completedLots = (clone $completedLotBaseQuery)
            ->orderByDesc('updated_at')
            ->paginate(6, ['*'], 'historyPage')
            ->withQueryString();

        $ratingQuery = Transaksi::query()
            ->whereHas('ikan', fn ($query) => $query->where('user_id', (int) $seller->id))
            ->where(function ($query): void {
                $this->applyCompletedSaleScope($query);
            })
            ->whereNotNull('buyer_rating');

        $ratingCount = (clone $ratingQuery)->count();

        $storeStats = [
            'lot_aktif' => Ikan::query()
                ->where('user_id', (int) $seller->id)
                ->whereIn('status', ['aktif', 'menunggu'])
                ->count(),
            'lot_selesai' => (clone $completedLotBaseQuery)->count(),
            'rating_average' => $ratingCount > 0
                ? round((float) (clone $ratingQuery)->avg('buyer_rating'), 1)
                : null,
            'rating_count' => $ratingCount,
            'total_lot' => Ikan::query()
                ->where('user_id', (int) $seller->id)
                ->count(),
        ];

        return view('penjual.public-dashboard', [
            'seller' => $seller,
            'sellerProfile' => $seller->sellerProfile,
            'activeLots' => $activeLots,
            'completedLots' => $completedLots,
            'storeStats' => $storeStats,
        ]);
    }

    public function index(Request $request)
    {
        $this->lelang->aktifkanYangBelumMulai();
        $this->lelang->cekDanTutupSemua();

        $tipeLelang = $request->query('tipe_lelang', 'semua');
        if (! in_array($tipeLelang, ['semua', 'naik', 'turun'], true)) {
            $tipeLelang = 'semua';
        }

        $fokus = (string) $request->query('fokus', 'semua');
        if (! in_array($fokus, ['semua', 'total_lot', 'menunggu_tayang', 'perlu_jemput', 'lot_aktif'], true)) {
            $fokus = 'semua';
        }

        $baseListingQuery = auth()->user()->ikans()
            ->with('transaksi')
            ->withCount('bids')
            ->whereDoesntHave('transaksi', function ($query): void {
                $this->applyCompletedSaleScope($query);
            });

        if ($tipeLelang !== 'semua') {
            $baseListingQuery->where('tipe_lelang', $tipeLelang);
        }

        $statsQuery = clone $baseListingQuery;
        $buildSellerIkanIdQuery = function () use ($tipeLelang) {
            return Ikan::query()
                ->select('id')
                ->where('user_id', auth()->id())
                ->when($tipeLelang !== 'semua', fn ($query) => $query->where('tipe_lelang', $tipeLelang));
        };

        $buildTransaksiScope = function () use ($buildSellerIkanIdQuery) {
            return Transaksi::query()
                ->whereIn('ikan_id', $buildSellerIkanIdQuery())
                ->where(function ($query): void {
                    $this->applyNotCompletedSaleScope($query);
                });
        };
        $buildLunasScope = function () use ($buildTransaksiScope) {
            return $buildTransaksiScope()->where('status', 'lunas');
        };

        $sellerStats = [
            'total_lot' => (clone $statsQuery)->count(),
            'aktif' => (clone $statsQuery)->where('status', 'aktif')->count(),
            'menunggu' => (clone $statsQuery)->where('status', 'menunggu')->count(),
            'menunggu_bayar' => $buildTransaksiScope()
                ->where('status', 'menunggu_bayar')
                ->count(),
            'perlu_jemput' => $buildTransaksiScope()
                ->where('status', 'lunas')
                ->where('pickup_status', 'awaiting_pickup')
                ->count(),
            'jemput_terlambat' => $buildTransaksiScope()
                ->where('status', 'lunas')
                ->where('pickup_status', 'awaiting_pickup')
                ->whereNotNull('dibayar_pada')
                ->where('dibayar_pada', '<=', now()->subDay())
                ->count(),
            'pipeline_siapkan_total' => $buildLunasScope()
                ->whereNull('packed_at')
                ->count(),
            'pipeline_siapkan_confirmed' => $buildLunasScope()
                ->whereNotNull('packed_at')
                ->count(),
            'pipeline_penjemputan_total' => $buildLunasScope()
                ->whereNotNull('packed_at')
                ->where('pickup_status', 'awaiting_pickup')
                ->count(),
            'pipeline_penjemputan_confirmed' => $buildLunasScope()
                ->whereIn('pickup_status', ['pickup_arrived', 'completed'])
                ->count(),
            'pipeline_selesai_total' => $buildLunasScope()
                ->where('pickup_status', 'pickup_arrived')
                ->count(),
            'pipeline_selesai_confirmed' => 0,
            'nilai_penjualan_lunas' => (float) $buildLunasScope()->sum('harga_final'),
            'nilai_menunggu_bayar' => (float) $buildTransaksiScope()
                ->where('status', 'menunggu_bayar')
                ->sum('harga_final'),
        ];

        $baseQuery = clone $baseListingQuery;
        if ($fokus === 'menunggu_tayang') {
            $baseQuery->where('status', 'menunggu');
        } elseif ($fokus === 'lot_aktif') {
            $baseQuery->where('status', 'aktif');
        } elseif ($fokus === 'perlu_jemput') {
            $baseQuery->whereHas('transaksi', function ($query): void {
                $query->where('status', 'lunas')
                    ->where('pickup_status', 'awaiting_pickup')
                    ->whereNull('packed_at');
            });
        }

        $perluJemputIds = $buildTransaksiScope()
            ->where('status', 'lunas')
            ->whereNull('packed_at')
            ->whereNotNull('dibayar_pada')
            ->orderBy('dibayar_pada')
            ->limit(5)
            ->pluck('ikan_id');

        $penjemputanIds = $buildTransaksiScope()
            ->where('status', 'lunas')
            ->whereNotNull('packed_at')
            ->where('pickup_status', 'awaiting_pickup')
            ->orderByRaw('COALESCE(packed_at, updated_at) ASC')
            ->limit(5)
            ->pluck('ikan_id');

        $selesaiIds = $buildTransaksiScope()
            ->where('status', 'lunas')
            ->where('pickup_status', 'pickup_arrived')
            ->orderByRaw('COALESCE(completed_by_buyer_at, pickup_verified_at, updated_at) ASC')
            ->limit(5)
            ->pluck('ikan_id');

        $aksiIkanIds = $perluJemputIds
            ->concat($penjemputanIds)
            ->concat($selesaiIds)
            ->unique()
            ->values();

        $aksiIkanMap = $aksiIkanIds->isEmpty()
            ? collect()
            : Ikan::query()->with('transaksi')->whereIn('id', $aksiIkanIds)->get()->keyBy('id');

        $toOrderedAksi = function ($ids) use ($aksiIkanMap) {
            return $ids->map(fn ($id) => $aksiIkanMap->get($id))
                ->filter()
                ->values();
        };

        $aksiPrioritas = [
            'perlu_jemput' => $toOrderedAksi($perluJemputIds),
            'penjemputan' => $toOrderedAksi($penjemputanIds),
            'selesai' => $toOrderedAksi($selesaiIds),
        ];

        $belumBayar = (clone $baseQuery)
            ->whereHas('transaksi', fn ($query) => $query->where('status', 'menunggu_bayar'))
            ->orderByDesc('created_at')
            ->paginate(10, ['*'], 'belumBayarPage')
            ->withQueryString();

        $sudahBayar = (clone $baseQuery)
            ->whereHas('transaksi', fn ($query) => $query->where('status', 'lunas'))
            ->orderByDesc('created_at')
            ->paginate(10, ['*'], 'sudahBayarPage')
            ->withQueryString();

        $aktivitasLainnya = (clone $baseQuery)
            ->where(function ($query): void {
                $query->whereDoesntHave('transaksi')
                    ->orWhereHas('transaksi', fn ($trxQuery) => $trxQuery->whereNotIn('status', ['menunggu_bayar', 'lunas']));
            })
            ->orderByDesc('created_at')
            ->paginate(10, ['*'], 'lainnyaPage')
            ->withQueryString();

        return view('penjual.ikan.index', compact('belumBayar', 'sudahBayar', 'aktivitasLainnya', 'tipeLelang', 'sellerStats', 'aksiPrioritas', 'fokus'));
    }

    public function show(Ikan $ikan)
    {
        if ($ikan->user_id !== auth()->id()) {
            abort(403);
        }

        $this->lelang->aktifkanYangBelumMulai();
        $this->lelang->tutupLelang($ikan);

        $ikan->refresh()->load(['bids.user', 'transaksi.pemenang']);

        return view('penjual.ikan.show', compact('ikan'));
    }

    public function markPacked(Request $request, Ikan $ikan)
    {
        $returnUrl = $this->safeReturnUrl(
            $request->input('return_url'),
            route('penjual.ikans.index')
        );

        if ($ikan->user_id !== auth()->id()) {
            abort(403);
        }

        $transaksi = $ikan->transaksi;
        if (! $transaksi || ! $transaksi->isLunas()) {
            return redirect()->to($returnUrl)->with('error', 'Transaksi belum siap diproses untuk packing.');
        }

        if (in_array($transaksi->fulfillment_state, ['DISENGKETAKAN', 'GAGAL', 'SELESAI'], true)) {
            return redirect()->to($returnUrl)->with('error', 'Transaksi ini tidak dapat diproses karena status fulfillment saat ini.');
        }

        $validator = Validator::make($request->all(), [
            'packing_proof' => [$transaksi->packing_proof ? 'nullable' : 'required', 'image', 'max:3072'],
            'packing_location' => ['required', 'string', 'max:191'],
            'packing_recorded_at' => ['nullable', 'date'],
            'packing_description' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return redirect()->to($returnUrl)
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $proofPath = $transaksi->packing_proof;
        if ($request->hasFile('packing_proof')) {
            if ($proofPath) {
                Storage::disk('public')->delete($proofPath);
            }

            $proofPath = $this->storePublicUpload($request->file('packing_proof'), 'delivery-proof', 'packing_proof');
        }

        $recordedAt = isset($validated['packing_recorded_at'])
            ? new \DateTimeImmutable($validated['packing_recorded_at'])
            : null;

        $transaksi->markDipacking(
            $proofPath,
            $validated['packing_location'] ?? null,
            $validated['packing_description'] ?? null,
            $recordedAt
        );
        $transaksi->save();
        $this->fulfillment->markSellerProcessing($transaksi, (int) auth()->id());
        app(NotificationOutboxService::class)->queue(
            (int) ($transaksi->pemenang_id ?? 0),
            'packing',
            'Pesanan sedang dipacking',
            'Penjual sudah mengunggah foto packing dan mencatat lokasi packing.',
            ['ikan_id' => $ikan->id, 'transaksi_id' => $transaksi->id],
            'packing:' . $transaksi->id
        );
        app(NotificationOutboxService::class)->processPending(50);
        $ikan->increment('state_version');

        return redirect()->to($returnUrl)
            ->with('sukses', 'Packing berhasil disimpan. Lanjutkan pantau data penjemputan pembeli.')
            ->with('logistik_action', 'packing_saved');
    }

    public function markPickupArrived(Request $request, Ikan $ikan)
    {
        $returnUrl = $this->safeReturnUrl(
            $request->input('return_url'),
            route('penjual.ikans.index')
        );

        if ($ikan->user_id !== auth()->id()) {
            abort(403);
        }

        $transaksi = $ikan->transaksi;
        if (! $transaksi || ! $transaksi->isLunas()) {
            return redirect()->to($returnUrl)->with('error', 'Transaksi belum siap untuk penjemputan.');
        }

        if (in_array($transaksi->fulfillment_state, ['DISENGKETAKAN', 'GAGAL', 'SELESAI'], true)) {
            return redirect()->to($returnUrl)->with('error', 'Transaksi ini tidak dapat diproses karena status fulfillment saat ini.');
        }

        if (! $transaksi->packed_at) {
            return redirect()->to($returnUrl)->with('error', 'Konfirmasi packing wajib dilakukan sebelum menerima penjemput.');
        }

        if (! $transaksi->buyer_pickup_submitted_at) {
            return redirect()->to($returnUrl)->with('error', 'Pembeli belum mengisi data penjemput.');
        }

        $validator = Validator::make($request->all(), [
            'seller_pickup_driver_name' => ['required', 'string', 'max:150'],
            'seller_pickup_plate_number' => ['required', 'string', 'max:32'],
            'seller_pickup_driver_photo' => ['required', 'image', 'max:3072'],
            'seller_pickup_vehicle_photo' => ['required', 'image', 'max:3072'],
        ]);

        if ($validator->fails()) {
            return redirect()->to($returnUrl)
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $driverMatches = mb_strtolower(trim((string) $validated['seller_pickup_driver_name']))
            === mb_strtolower(trim((string) $transaksi->buyer_pickup_name));
        $plateMatches = $this->normalizePlate((string) $validated['seller_pickup_plate_number'])
            === $this->normalizePlate((string) $transaksi->buyer_pickup_plate_number);

        if (! $driverMatches || ! $plateMatches) {
            return redirect()->to($returnUrl)
                ->withErrors(['seller_pickup_plate_number' => 'Data penjemput tidak cocok dengan data yang diisi pembeli.'])
                ->withInput();
        }

        $driverPhotoPath = $this->storePublicUpload($request->file('seller_pickup_driver_photo'), 'pickup-proof', 'seller_pickup_driver_photo');
        $vehiclePhotoPath = $this->storePublicUpload($request->file('seller_pickup_vehicle_photo'), 'pickup-proof', 'seller_pickup_vehicle_photo');

        $transaksi->markPickupArrived(
            (string) $validated['seller_pickup_driver_name'],
            strtoupper((string) $validated['seller_pickup_plate_number']),
            $driverPhotoPath,
            $vehiclePhotoPath,
            true
        );
        $transaksi->save();
        $this->fulfillment->markPickupValidated($transaksi, (int) auth()->id());
        app(NotificationOutboxService::class)->queue(
            (int) ($transaksi->pemenang_id ?? 0),
            'penjemputan',
            'Penjemput sudah datang',
            'Penjual sudah memvalidasi sopir dan kendaraan penjemput di lokasi.',
            ['ikan_id' => $ikan->id, 'transaksi_id' => $transaksi->id],
            'pickup-arrived:' . $transaksi->id
        );
        app(NotificationOutboxService::class)->processPending(50);
        $ikan->increment('state_version');

        return redirect()->to($returnUrl)
            ->with('sukses', 'Data penjemput datang berhasil divalidasi.')
            ->with('logistik_action', 'pickup_arrived');
    }

    private function safeReturnUrl(mixed $candidate, string $fallback): string
    {
        return safeInternalReturnUrl($candidate, $fallback);
    }

    private function applyCompletedSaleScope($query): void
    {
        $query->whereNotNull('completed_by_buyer_at')
            ->orWhere('pickup_status', 'completed')
            ->orWhere('fulfillment_state', 'SELESAI');
    }

    private function applyNotCompletedSaleScope($query): void
    {
        $query->whereNull('completed_by_buyer_at')
            ->where(function ($query): void {
                $query->whereNull('pickup_status')
                    ->orWhere('pickup_status', '!=', 'completed');
            })
            ->where(function ($query): void {
                $query->whereNull('fulfillment_state')
                    ->orWhere('fulfillment_state', '!=', 'SELESAI');
            });
    }

    private function normalizePlate(string $value): string
    {
        return preg_replace('/[^A-Z0-9]+/', '', strtoupper($value)) ?: '';
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
