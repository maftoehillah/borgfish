<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReturnsNoStoreJson;
use App\Models\Ikan;
use App\Services\LelangService;
use App\Services\SystemSettingService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class IkanController extends Controller
{
    use ReturnsNoStoreJson;

    public function __construct(private LelangService $lelang) {}

    public function index(Request $request)
    {
        $this->lelang->aktifkanYangBelumMulai();
        $this->lelang->cekDanTutupSemua();

        $tipeLelang = $request->query('tipe_lelang', 'semua');
        if (! in_array($tipeLelang, ['semua', 'naik', 'turun'], true)) {
            $tipeLelang = 'semua';
        }

        $fokusStat = (string) $request->query('fokus', 'semua');
        if ($fokusStat === 'nilai_bid_tertinggi') {
            $fokusStat = 'terpopuler';
        }

        if (! in_array($fokusStat, ['semua', 'aktif', 'menunggu', 'hampir_selesai', 'selesai', 'terpopuler'], true)) {
            $fokusStat = 'semua';
        }

        $berlangsungQuery = Ikan::with(['user', 'transaksi'])
            ->withCount('bids')
            ->withCount([
                'bids as unique_bidder_count' => fn ($query) => $query->select(DB::raw('count(distinct user_id)')),
            ])
            ->whereIn('status', ['aktif', 'menunggu']);

        $selesaiQuery = Ikan::with(['user', 'transaksi'])
            ->withCount('bids')
            ->whereIn('status', ['selesai', 'terbayar']);

        if ($tipeLelang !== 'semua') {
            $berlangsungQuery->where('tipe_lelang', $tipeLelang);
            $selesaiQuery->where('tipe_lelang', $tipeLelang);
        }

        $berlangsungListQuery = clone $berlangsungQuery;
        $selesaiListQuery = clone $selesaiQuery;

        $berlangsungStatsQuery = clone $berlangsungQuery;
        $selesaiStatsQuery = clone $selesaiQuery;
        $now = now();

        switch ($fokusStat) {
            case 'aktif':
                $berlangsungListQuery->where('status', 'aktif');
                $selesaiListQuery->whereRaw('1 = 0');
                break;

            case 'menunggu':
                $berlangsungListQuery->where('status', 'menunggu');
                $selesaiListQuery->whereRaw('1 = 0');
                break;

            case 'hampir_selesai':
                $berlangsungListQuery
                    ->where('status', 'aktif')
                    ->whereBetween('waktu_selesai', [$now, $now->copy()->addMinutes(30)]);
                $selesaiListQuery->whereRaw('1 = 0');
                break;

            case 'selesai':
                $berlangsungListQuery->whereRaw('1 = 0');
                break;

            case 'terpopuler':
                $berlangsungListQuery->where('status', 'aktif');
                $selesaiListQuery->whereRaw('1 = 0');
                break;
        }

        $mostPopularActiveLot = (clone $berlangsungStatsQuery)
            ->where('status', 'aktif')
            ->orderByDesc('unique_bidder_count')
            ->orderByDesc('bids_count')
            ->orderBy('waktu_selesai')
            ->first();

        $marketStats = [
            'aktif_total' => (clone $berlangsungStatsQuery)
                ->where('status', 'aktif')
                ->count(),
            'menunggu_total' => (clone $berlangsungStatsQuery)
                ->where('status', 'menunggu')
                ->count(),
            'berakhir_30_menit' => (clone $berlangsungStatsQuery)
                ->where('status', 'aktif')
                ->whereBetween('waktu_selesai', [$now, $now->copy()->addMinutes(30)])
                ->count(),
            'lot_terpopuler_penawar' => (int) ($mostPopularActiveLot?->unique_bidder_count ?? 0),
            'lot_terpopuler_bid' => (int) ($mostPopularActiveLot?->bids_count ?? 0),
            'selesai_total' => (clone $selesaiStatsQuery)
                ->count(),
        ];

        if ($fokusStat === 'terpopuler') {
            $berlangsungListQuery
                ->orderByDesc('unique_bidder_count')
                ->orderByDesc('bids_count')
                ->orderBy('waktu_selesai');
        } elseif ($fokusStat === 'menunggu') {
            $berlangsungListQuery
                ->orderBy('waktu_mulai');
        } else {
            $berlangsungListQuery
                ->orderByRaw("CASE status WHEN 'aktif' THEN 0 WHEN 'menunggu' THEN 1 ELSE 2 END")
                ->orderBy('waktu_selesai');
        }

        $lelangBerlangsung = $berlangsungListQuery
            ->paginate(12, ['*'], 'berlangsungPage')
            ->withQueryString();

        $lelangSelesai = $selesaiListQuery
            ->orderByRaw("CASE status WHEN 'selesai' THEN 0 WHEN 'terbayar' THEN 1 ELSE 2 END")
            ->orderByDesc('waktu_selesai')
            ->paginate(12, ['*'], 'selesaiPage')
            ->withQueryString();

        return view('ikan.index', compact('lelangBerlangsung', 'lelangSelesai', 'tipeLelang', 'marketStats', 'fokusStat'));
    }

    public function show(Ikan $ikan)
    {
        $this->lelang->aktifkanYangBelumMulai();
        $this->lelang->tutupLelang($ikan);
        $ikan->refresh()->load(['user.sellerProfile', 'bids.user', 'transaksi.pemenang']);

        $user = auth()->user();
        $canActAsBuyer = $user?->canActAsPembeli() ?? false;
        $isOwnLot = $user && (int) $ikan->user_id === (int) $user->id;
        $isSuperAdminBuyerMode = $user?->isSuperAdmin() && $user->superAdminViewMode() === 'PEMBELI';

        $isPemenang = $user
            && $ikan->transaksi
            && (int) $ikan->transaksi->pemenang_id === (int) $user->id;

        $bidSaya = null;
        $isMemimpin = false;
        $isKalah = false;

        if ($user && $canActAsBuyer && ! $isOwnLot) {
            $bidUser = $ikan->bids->where('user_id', $user->id);
            $bidSaya = $ikan->isLelangTurun()
                ? $bidUser->max('jumlah_bid')
                : $bidUser->max('jumlah_bid');

            if ($bidSaya !== null) {
                $isMemimpin = (int) ($ikan->bestBid()?->user_id ?? 0) === (int) $user->id;
                $isKalah = ! $isMemimpin;
            }
        }

        $tidakAdaPemenang = $ikan->isSelesai() && ! $ikan->transaksi;

        return view('ikan.show', compact(
            'ikan',
            'isPemenang',
            'bidSaya',
            'isMemimpin',
            'isKalah',
            'tidakAdaPemenang',
            'canActAsBuyer',
            'isOwnLot',
            'isSuperAdminBuyerMode',
        ));
    }

    public function state(Ikan $ikan)
    {
        $this->lelang->aktifkanYangBelumMulai();
        $this->lelang->tutupLelang($ikan);

        $ikan->refresh()->load('transaksi');

        $transaksi = $ikan->transaksi;

        return $this->noStoreJson([
            'id' => $ikan->id,
            'status' => $ikan->status,
            'tipe_lelang' => $ikan->tipe_lelang,
            'bid_direction' => $ikan->isLelangTurun() ? 'turun' : 'naik',
            'harga_tertinggi' => (float) $ikan->harga_tertinggi,
            'bid_minimal' => $ikan->bidMinimal(),
            'bid_threshold' => $ikan->bidMinimal(),
            'minimal_increment' => (float) $ikan->minimal_increment,
            'buy_now_enabled' => $ikan->isLelangTurun() ? true : $ikan->buy_now_enabled,
            'buy_now_price' => $ikan->buyNowTarget(),
            'can_buy_now' => $ikan->canBuyNow(),
            'waktu_selesai_iso' => $ikan->waktu_selesai?->toISOString(),
            'waktu_selesai_potensial_iso' => $ikan->anti_sniping_enabled
                ? $ikan->waktu_selesai?->copy()->addSeconds((int) $ikan->anti_sniping_extend_seconds * max(0, (int) $ikan->anti_sniping_max_extensions - (int) $ikan->anti_sniping_extensions_used))->toISOString()
                : $ikan->waktu_selesai?->toISOString(),
            'anti_sniping_enabled' => $ikan->anti_sniping_enabled,
            'anti_sniping_window_seconds' => (int) $ikan->anti_sniping_window_seconds,
            'anti_sniping_extend_seconds' => (int) $ikan->anti_sniping_extend_seconds,
            'anti_sniping_extensions_used' => (int) $ikan->anti_sniping_extensions_used,
            'anti_sniping_max_extensions' => (int) $ikan->anti_sniping_max_extensions,
            'state_version' => (int) $ikan->state_version,
            'total_bid' => $ikan->bids()->count(),
            'server_time_iso' => now()->toISOString(),
            'transaksi' => $transaksi ? [
                'id' => $transaksi->id,
                'status' => $transaksi->status,
                'payment_status' => $transaksi->payment_status,
                'pickup_status' => $transaksi->pickup_status,
                'fulfillment_state' => $transaksi->fulfillment_state,
                'seller_ack_deadline_iso' => $transaksi->seller_ack_deadline_at?->toISOString(),
                'buyer_confirm_deadline_iso' => $transaksi->buyer_confirm_deadline_at?->toISOString(),
                'state_version' => (int) ($transaksi->state_version ?? 0),
            ] : null,
        ]);
    }

    public function create(Request $request)
    {
        $duplicateSourceId = (int) ($request->query('duplikat_dari') ?: $request->old('reuse_source_ikan_id'));
        $duplicateSourceIkan = null;
        $prefillIkan = new Ikan();

        if ($duplicateSourceId > 0) {
            $duplicateSourceIkan = Ikan::query()
                ->where('id', $duplicateSourceId)
                ->where('user_id', auth()->id())
                ->first();

            if ($duplicateSourceIkan) {
                $prefillIkan = $duplicateSourceIkan->replicate([
                    'status',
                    'auction_state',
                    'current_winner_rank',
                    'hard_stop_reason',
                    'ranking_frozen_at',
                    'last_bidder_id',
                    'last_bid_at',
                    'state_version',
                    'harga_tertinggi',
                ]);

                $prefillIkan->waktu_mulai = now()->addMinutes(10);
                $prefillIkan->waktu_selesai = now()->addHours(2);
            }
        }

        $draftIkan = auth()->user()?->ikans()->latest('id')->first();
        $systemSettings = app(SystemSettingService::class);

        return view('penjual.ikan.create', [
            'ikan' => $prefillIkan,
            'isEdit' => false,
            'draftIkan' => $draftIkan,
            'duplicateSourceIkan' => $duplicateSourceIkan,
            'antiSnipingDefaults' => $this->antiSnipingDefaults($systemSettings),
        ]);
    }

    public function edit(Request $request, Ikan $ikan)
    {
        $this->pastikanMilikPenjual($ikan);

        $returnUrl = $this->safeReturnUrl(
            $request->query('return_url'),
            route('penjual.ikans.index')
        );

        if (! $this->bolehEdit($ikan)) {
            return redirect()->route('penjual.ikans.show', ['ikan' => $ikan, 'return_url' => $returnUrl])
                ->with('error', 'Ikan tidak bisa diubah setelah lelang aktif.');
        }

        return view('penjual.ikan.create', [
            'ikan' => $ikan,
            'isEdit' => true,
            'draftIkan' => null,
            'antiSnipingDefaults' => $this->antiSnipingDefaults(app(SystemSettingService::class)),
        ]);
    }

    public function store(Request $request)
    {
        $returnUrl = $this->safeReturnUrl(
            $request->input('return_url'),
            route('penjual.ikans.index')
        );

        $validator = Validator::make($request->all(), [
            'nama_ikan' => 'required|string|max:191',
            'berat' => 'required|numeric|min:0.1',
            'estimasi_jumlah_ekor' => 'nullable|integer|min:1|max:5000',
            'jenis_kemasan' => 'nullable|in:keranjang,besek,styrofoam',
            'kondisi' => 'required|in:segar,beku',
            'deskripsi' => 'nullable|string|max:1000',
            'tanggal_tangkap' => 'nullable|date|before_or_equal:today',
            'metode_tangkap' => 'nullable|string|max:191',
            'tipe_lelang' => 'required|in:naik,turun',
            'harga_awal' => 'required|numeric|min:1000',
            'reserve_price' => 'nullable|numeric|min:1000',
            'minimal_increment' => 'required_if:tipe_lelang,naik|nullable|numeric|min:1000',
            'buy_now_price' => 'nullable|numeric|min:1000',
            'payment_deadline_minutes' => 'nullable|integer|min:1|max:4320',
            'payment_deadline_initial_minutes' => 'nullable|integer|min:1|max:4320',
            'anti_sniping_window_seconds' => 'nullable|integer|min:30|max:600',
            'anti_sniping_extend_seconds' => 'nullable|integer|min:30|max:600',
            'anti_sniping_max_extensions' => 'nullable|integer|min:1|max:20',
            'mulai_sekarang' => 'nullable|boolean',
            'waktu_mulai' => 'nullable|date',
            'waktu_selesai' => 'required|date|after:now',
            'reuse_source_ikan_id' => 'nullable|integer|min:1',
            'foto' => 'nullable|image|max:5120',
            'video' => 'nullable|file|mimetypes:video/mp4,video/quicktime,video/webm|max:30720',
            'video_duration_seconds' => 'nullable|numeric|min:0|max:30',
            'foto_diambil_pada' => 'nullable|date',
        ], [
            'video_duration_seconds.max' => 'Durasi video maksimal 30 detik.',
            'video_duration_seconds.numeric' => 'Durasi video tidak valid.',
        ]);

        $validator->after(function ($validator) use ($request): void {
            $reservePrice = $request->input('reserve_price');

            $reuseSourceId = (int) $request->input('reuse_source_ikan_id', 0);
            $reuseSourceIkan = $this->resolveReusableSourceIkan($reuseSourceId);

            if ($reuseSourceId > 0 && ! $reuseSourceIkan) {
                $validator->errors()->add('reuse_source_ikan_id', 'Lot sumber untuk upload ulang tidak ditemukan atau tidak dapat digunakan.');
            }

            if (! $request->hasFile('foto') && ! ($reuseSourceIkan && $reuseSourceIkan->foto)) {
                $validator->errors()->add('foto', 'Foto wajib diupload untuk lot baru, atau gunakan sumber upload ulang yang memiliki foto.');
            }

            if ($request->input('tipe_lelang') === 'turun') {
                if ((float) $request->input('harga_awal', 0) < 2000) {
                    $validator->errors()->add('harga_awal', 'Pada lelang turun, harga patokan minimal Rp 2.000 agar tetap ada ruang bid.');
                }

                if ($reservePrice !== null && $reservePrice !== '' && (float) $reservePrice >= (float) $request->input('harga_awal', 0)) {
                    $validator->errors()->add('reserve_price', 'Pada lelang turun, reserve price harus lebih rendah dari harga patokan.');
                }
            }

            $this->validatePaymentPolicyInput($validator, $request->all());
            $this->validateVideoDurationInput($validator, $request);
        });

        if ($validator->fails()) {
            $redirectParams = ['return_url' => $returnUrl];

            if ($request->filled('reuse_source_ikan_id')) {
                $redirectParams['duplikat_dari'] = (int) $request->input('reuse_source_ikan_id');
            }

            return redirect()->route('penjual.ikans.create', $redirectParams)
                ->withErrors($validator)
                ->with('error', 'Data upload belum lengkap. Mohon lengkapi data wajib lalu coba lagi.')
                ->withInput();
        }

        $validated = $validator->validated();
        $reuseSourceIkan = $this->resolveReusableSourceIkan($validated['reuse_source_ikan_id'] ?? null);

        $this->resolveWaktuMulai($request, $validated);
        $this->validateWaktuLelangConsistency($validated);
        $waktuMulai = Carbon::parse($validated['waktu_mulai']);

        $fotoPath = null;
        $videoPath = null;

        if (($validated['tipe_lelang'] ?? 'naik') === 'turun') {
            $validated['minimal_increment'] = 1000;
            $validated['buy_now_enabled'] = true;
            $validated['buy_now_price'] = (float) $validated['harga_awal'];
        } else {
            $validated['minimal_increment'] = (float) ($validated['minimal_increment'] ?? 1000);
            $validated['buy_now_enabled'] = $request->boolean('buy_now_enabled');

            if (! $validated['buy_now_enabled']) {
                $validated['buy_now_price'] = null;
            }

            if ($validated['buy_now_enabled'] && empty($validated['buy_now_price'])) {
                $validated['buy_now_price'] = (float) $validated['harga_awal'] + ((float) $validated['minimal_increment'] * 10);
            }
        }

        $validated = array_merge($validated, $this->resolvePaymentPolicyInputs($validated));
        $validated['reserve_price'] = ($validated['tipe_lelang'] ?? 'naik') === 'turun'
            ? (array_key_exists('reserve_price', $validated) && $validated['reserve_price'] !== null
                ? (float) $validated['reserve_price']
                : null)
            : null;

        $validated['asal_pelabuhan'] = null;
        $validated['grade_mutu'] = null;
        $validated['suhu_penyimpanan'] = null;
        $validated['surveyor'] = null;
        $validated['catatan_survey'] = null;
        $validated['verifikasi_pelabuhan_at'] = null;

        $validated['anti_sniping_enabled'] = $request->boolean('anti_sniping_enabled');
        $validated = array_merge($validated, $this->resolveAntiSnipingInputs());

        $this->validateAntiSnipingPotential($validated);

        // GPS capture is disabled; keep coordinates empty for new lots.
        $validated['foto_latitude'] = null;
        $validated['foto_longitude'] = null;

        $captureAt = ! empty($validated['foto_diambil_pada'] ?? null)
            ? Carbon::parse($validated['foto_diambil_pada'])
            : now();

        if ($request->hasFile('foto')) {
            $fotoPath = $this->storeFotoWithWatermark(
                $request->file('foto'),
                $captureAt,
            );

            $validated['foto_diambil_pada'] = $captureAt;
        } elseif ($reuseSourceIkan && $reuseSourceIkan->foto) {
            $fotoPath = $this->clonePublicFile($reuseSourceIkan->foto, 'ikans', 'foto');
            $validated['foto_diambil_pada'] = $reuseSourceIkan->foto_diambil_pada ?? now();
        }

        if ($request->hasFile('video')) {
            $videoPath = $this->storePublicUpload($request->file('video'), 'ikans-videos', 'video');
        } elseif ($reuseSourceIkan && $reuseSourceIkan->video) {
            $videoPath = $this->clonePublicFile($reuseSourceIkan->video, 'ikans-videos', 'video');
        }

        $statusAwal = now()->lt($waktuMulai) ? 'menunggu' : 'aktif';

        unset($validated['mulai_sekarang']);
        unset($validated['reuse_source_ikan_id']);
        unset($validated['video_duration_seconds']);

        $ikan = new Ikan([
            ...$validated,
            'harga_tertinggi' => $validated['harga_awal'],
            'foto' => $fotoPath,
            'video' => $videoPath,
            'status' => $statusAwal,
            'auction_state' => 'AKTIF',
            'current_winner_rank' => null,
            'hard_stop_reason' => null,
            'ranking_frozen_at' => null,
            'verifikasi_pelabuhan_at' => null,
            'state_version' => 1,
        ]);

        $ikan->user_id = auth()->id();
        $ikan->save();

        if ($ikan->isLelangNaik() && $ikan->buy_now_enabled && (float) $ikan->buy_now_price <= (float) $ikan->harga_awal) {
            $ikan->update([
                'buy_now_price' => (float) $ikan->harga_awal + ((float) $ikan->minimal_increment * 10),
            ]);
        }

        return redirect()->route('penjual.ikans.index')
            ->with('sukses', 'Ikan berhasil diupload ke lelang!');
    }

    public function update(Request $request, Ikan $ikan)
    {
        $this->pastikanMilikPenjual($ikan);

        $returnUrl = $this->safeReturnUrl(
            $request->input('return_url'),
            route('penjual.ikans.show', $ikan)
        );

        if (! $this->bolehEdit($ikan)) {
            return redirect()->to($returnUrl)->with('error', 'Ikan tidak bisa diubah setelah lelang aktif.');
        }

        $validator = Validator::make($request->all(), [
            'nama_ikan' => 'required|string|max:191',
            'berat' => 'required|numeric|min:0.1',
            'estimasi_jumlah_ekor' => 'nullable|integer|min:1|max:5000',
            'jenis_kemasan' => 'nullable|in:keranjang,besek,styrofoam',
            'kondisi' => 'required|in:segar,beku',
            'deskripsi' => 'nullable|string|max:1000',
            'tanggal_tangkap' => 'nullable|date|before_or_equal:today',
            'metode_tangkap' => 'nullable|string|max:191',
            'tipe_lelang' => 'required|in:naik,turun',
            'harga_awal' => 'required|numeric|min:1000',
            'reserve_price' => 'nullable|numeric|min:1000',
            'minimal_increment' => 'required_if:tipe_lelang,naik|nullable|numeric|min:1000',
            'buy_now_price' => 'nullable|numeric|min:1000',
            'payment_deadline_minutes' => 'nullable|integer|min:1|max:4320',
            'payment_deadline_initial_minutes' => 'nullable|integer|min:1|max:4320',
            'anti_sniping_window_seconds' => 'nullable|integer|min:30|max:600',
            'anti_sniping_extend_seconds' => 'nullable|integer|min:30|max:600',
            'anti_sniping_max_extensions' => 'nullable|integer|min:1|max:20',
            'mulai_sekarang' => 'nullable|boolean',
            'waktu_mulai' => 'nullable|date',
            'waktu_selesai' => 'required|date|after:now',
            'foto' => 'nullable|image|max:5120',
            'video' => 'nullable|file|mimetypes:video/mp4,video/quicktime,video/webm|max:30720',
            'video_duration_seconds' => 'nullable|numeric|min:0|max:30',
            'foto_diambil_pada' => 'nullable|date',
        ], [
            'video_duration_seconds.max' => 'Durasi video maksimal 30 detik.',
            'video_duration_seconds.numeric' => 'Durasi video tidak valid.',
        ]);

        $validator->after(function ($validator) use ($request): void {
            $reservePrice = $request->input('reserve_price');

            if ($request->input('tipe_lelang') === 'turun') {
                if ((float) $request->input('harga_awal', 0) < 2000) {
                    $validator->errors()->add('harga_awal', 'Pada lelang turun, harga patokan minimal Rp 2.000 agar tetap ada ruang bid.');
                }

                if ($reservePrice !== null && $reservePrice !== '' && (float) $reservePrice >= (float) $request->input('harga_awal', 0)) {
                    $validator->errors()->add('reserve_price', 'Pada lelang turun, reserve price harus lebih rendah dari harga patokan.');
                }
            }

            $this->validatePaymentPolicyInput($validator, $request->all());
            $this->validateVideoDurationInput($validator, $request);
        });

        if ($validator->fails()) {
            return redirect()->route('penjual.ikans.edit', ['ikan' => $ikan, 'return_url' => $returnUrl])
                ->withErrors($validator)
                ->with('error', 'Data ikan belum lengkap. Mohon lengkapi data wajib lalu coba lagi.')
                ->withInput();
        }

        $validated = $validator->validated();

        $this->resolveWaktuMulai($request, $validated);
        $this->validateWaktuLelangConsistency($validated);
        $waktuMulai = Carbon::parse($validated['waktu_mulai']);

        if ($request->hasFile('foto')) {
            if ($ikan->foto) {
                Storage::disk('public')->delete($ikan->foto);
            }

            $captureAt = ! empty($validated['foto_diambil_pada'] ?? null)
                ? Carbon::parse($validated['foto_diambil_pada'])
                : now();

            $validated['foto'] = $this->storeFotoWithWatermark(
                $request->file('foto'),
                $captureAt,
            );
            $validated['foto_diambil_pada'] = $captureAt;
        }

        if ($request->hasFile('video')) {
            if ($ikan->video) {
                Storage::disk('public')->delete($ikan->video);
            }

            $validated['video'] = $this->storePublicUpload($request->file('video'), 'ikans-videos', 'video');
        }

        if (($validated['tipe_lelang'] ?? 'naik') === 'turun') {
            $validated['minimal_increment'] = 1000;
            $validated['buy_now_enabled'] = true;
            $validated['buy_now_price'] = (float) $validated['harga_awal'];
        } else {
            $validated['minimal_increment'] = (float) ($validated['minimal_increment'] ?? 1000);
            $validated['buy_now_enabled'] = $request->boolean('buy_now_enabled');

            if (! $validated['buy_now_enabled']) {
                $validated['buy_now_price'] = null;
            }

            if ($validated['buy_now_enabled'] && empty($validated['buy_now_price'])) {
                $validated['buy_now_price'] = (float) $validated['harga_awal'] + ((float) $validated['minimal_increment'] * 10);
            }
        }

        $validated = array_merge($validated, $this->resolvePaymentPolicyInputs($validated));
        $validated['reserve_price'] = ($validated['tipe_lelang'] ?? 'naik') === 'turun'
            ? (array_key_exists('reserve_price', $validated) && $validated['reserve_price'] !== null
                ? (float) $validated['reserve_price']
                : null)
            : null;

        $validated['asal_pelabuhan'] = null;
        $validated['grade_mutu'] = null;
        $validated['suhu_penyimpanan'] = null;
        $validated['surveyor'] = null;
        $validated['catatan_survey'] = null;
        $validated['verifikasi_pelabuhan_at'] = null;

        $validated['anti_sniping_enabled'] = $request->boolean('anti_sniping_enabled');
        $validated = array_merge($validated, $this->resolveAntiSnipingInputs());

        $this->validateAntiSnipingPotential($validated);

        // GPS capture is disabled; clear existing coordinates on update.
        $validated['foto_latitude'] = null;
        $validated['foto_longitude'] = null;

        if (! array_key_exists('foto_diambil_pada', $validated) || empty($validated['foto_diambil_pada'])) {
            $validated['foto_diambil_pada'] = $ikan->foto_diambil_pada;
        }

        $validated['harga_tertinggi'] = $validated['harga_awal'];
        $validated['status'] = now()->lt($waktuMulai) ? 'menunggu' : 'aktif';
        $validated['anti_sniping_extensions_used'] = 0;
        $validated['auction_state'] = 'AKTIF';
        $validated['current_winner_rank'] = null;
        $validated['hard_stop_reason'] = null;
        $validated['ranking_frozen_at'] = null;
        $validated['last_bidder_id'] = null;
        $validated['last_bid_at'] = null;
        $validated['verifikasi_pelabuhan_at'] = null;

        unset($validated['mulai_sekarang']);
        unset($validated['video_duration_seconds']);

        $ikan->fill($validated);
        $ikan->bumpStateVersion();
        $ikan->save();

        if ($ikan->isLelangNaik() && $ikan->buy_now_enabled && (float) $ikan->buy_now_price <= (float) $ikan->harga_awal) {
            $ikan->update([
                'buy_now_price' => (float) $ikan->harga_awal + ((float) $ikan->minimal_increment * 10),
            ]);
        }

        return redirect()->to($returnUrl)->with('sukses', 'Data ikan berhasil diperbarui.');
    }

    public function destroy(Request $request, Ikan $ikan)
    {
        $this->pastikanMilikPenjual($ikan);

        $returnUrl = $this->safeReturnUrl(
            $request->input('return_url'),
            route('penjual.ikans.index')
        );

        if ($ikan->bids()->exists()) {
            return redirect()->to($returnUrl)->with('error', 'Ikan yang sudah memiliki bid tidak bisa dihapus.');
        }

        if ($ikan->foto) {
            Storage::disk('public')->delete($ikan->foto);
        }

        if ($ikan->video) {
            Storage::disk('public')->delete($ikan->video);
        }

        $ikan->delete();

        return redirect()->to($returnUrl)
            ->with('sukses', 'Ikan berhasil dihapus.');
    }

    private function resolveWaktuMulai(Request $request, array &$validated): void
    {
        if ($request->boolean('mulai_sekarang')) {
            $validated['waktu_mulai'] = now()->toDateTimeString();

            return;
        }

        if (empty($validated['waktu_mulai'])) {
            throw ValidationException::withMessages([
                'waktu_mulai' => 'Isi waktu mulai atau pilih mode mulai sekarang.',
            ]);
        }

        $waktuMulai = Carbon::parse($validated['waktu_mulai']);
        if ($waktuMulai->lt(now()->subMinute())) {
            throw ValidationException::withMessages([
                'waktu_mulai' => 'Waktu mulai manual tidak boleh backdated. Gunakan mode mulai sekarang untuk lelang instan.',
            ]);
        }

        $validated['waktu_mulai'] = $waktuMulai->toDateTimeString();
    }

    private function validateWaktuLelangConsistency(array $validated): array
    {
        $mulai = Carbon::parse($validated['waktu_mulai']);
        $selesai = Carbon::parse($validated['waktu_selesai']);

        if ($selesai->lessThanOrEqualTo($mulai)) {
            throw ValidationException::withMessages([
                'waktu_selesai' => 'Waktu selesai harus lebih besar dari waktu mulai.',
            ]);
        }

        return [$mulai, $selesai];
    }

    private function storeFotoWithWatermark(UploadedFile $foto, ?Carbon $capturedAt): string {
        $storedPath = $this->storePublicUpload($foto, 'ikans', 'foto');

        if (! function_exists('imagecreatefromstring') || ! function_exists('imagestring')) {
            return $storedPath;
        }

        $fullPath = Storage::disk('public')->path($storedPath);
        $binary = @file_get_contents($fullPath);
        if ($binary === false) {
            return $storedPath;
        }

        $img = @imagecreatefromstring($binary);
        if (! $img) {
            return $storedPath;
        }

        $stamp = $this->buildFotoStampText($capturedAt);
        $font = 3;
        $padding = 8;
        $textWidth = imagefontwidth($font) * strlen($stamp);
        $textHeight = imagefontheight($font);
        $width = imagesx($img);
        $height = imagesy($img);

        $x = max(6, $width - $textWidth - ($padding * 2) - 10);
        $y = max(6, $height - $textHeight - ($padding * 2) - 10);

        if (function_exists('imagealphablending')) {
            imagealphablending($img, true);
        }

        if (function_exists('imagesavealpha')) {
            imagesavealpha($img, true);
        }

        $bgColor = imagecolorallocatealpha($img, 0, 0, 0, 55);
        $textColor = imagecolorallocate($img, 255, 255, 255);

        imagefilledrectangle(
            $img,
            $x,
            $y,
            $x + $textWidth + ($padding * 2),
            $y + $textHeight + ($padding * 2),
            $bgColor,
        );
        imagestring($img, $font, $x + $padding, $y + $padding, $stamp, $textColor);

        $this->saveWatermarkedImage($img, $fullPath);
        imagedestroy($img);

        return $storedPath;
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

            return $storedPath;
        } catch (Throwable $e) {
            report($e);

            throw ValidationException::withMessages([
                $fieldName => 'Upload gagal di server. Pastikan penyimpanan dapat ditulis, lalu coba lagi.',
            ]);
        }
    }

    private function clonePublicFile(string $sourcePath, string $directory, string $fieldName): ?string
    {
        $source = trim($sourcePath);
        if ($source === '') {
            return null;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($source)) {
            return null;
        }

        if (! $disk->exists($directory)) {
            $disk->makeDirectory($directory);
        }

        $extension = pathinfo($source, PATHINFO_EXTENSION);
        $targetFilename = uniqid('copy_', true) . ($extension !== '' ? '.' . strtolower($extension) : '');
        $targetPath = trim($directory, '/') . '/' . $targetFilename;

        $copied = $disk->copy($source, $targetPath);
        if (! $copied) {
            throw ValidationException::withMessages([
                $fieldName => 'Gagal menyalin media dari lot sumber. Coba upload file baru.',
            ]);
        }

        return $targetPath;
    }

    private function resolveReusableSourceIkan(mixed $sourceId): ?Ikan
    {
        $id = (int) ($sourceId ?? 0);
        if ($id <= 0 || ! auth()->check()) {
            return null;
        }

        return Ikan::query()
            ->where('id', $id)
            ->where('user_id', auth()->id())
            ->first();
    }

    private function buildFotoStampText(?Carbon $capturedAt): string
    {
        $waktuText = ($capturedAt ?? now())->format('d-m-Y H:i');

        return $waktuText;
    }

    private function saveWatermarkedImage($img, string $fullPath): void
    {
        $type = function_exists('exif_imagetype') ? @exif_imagetype($fullPath) : null;

        $saved = false;
        if ($type === IMAGETYPE_PNG && function_exists('imagepng')) {
            $saved = (bool) @imagepng($img, $fullPath, 6);
        } elseif ($type === IMAGETYPE_GIF && function_exists('imagegif')) {
            $saved = (bool) @imagegif($img, $fullPath);
        } elseif ($type === IMAGETYPE_WEBP && function_exists('imagewebp')) {
            $saved = (bool) @imagewebp($img, $fullPath, 85);
        } elseif (function_exists('imagejpeg')) {
            $saved = (bool) @imagejpeg($img, $fullPath, 88);
        }

        if (! $saved && function_exists('imagejpeg')) {
            @imagejpeg($img, $fullPath, 88);
        }
    }

    private function validateAntiSnipingPotential(array $validated): void
    {
        if (! (($validated['anti_sniping_enabled'] ?? false) === true)) {
            return;
        }

        $mulai = Carbon::parse($validated['waktu_mulai']);
        $selesai = Carbon::parse($validated['waktu_selesai']);
        $extraSeconds = max(0, (int) ($validated['anti_sniping_extend_seconds'] ?? 0))
            * max(0, (int) ($validated['anti_sniping_max_extensions'] ?? 0));

        $potensiSelesai = $selesai->copy()->addSeconds($extraSeconds);

        if ($potensiSelesai->lessThanOrEqualTo($mulai)) {
            throw ValidationException::withMessages([
                'waktu_selesai' => 'Rentang waktu lelang tidak valid setelah memperhitungkan anti-sniping.',
            ]);
        }

        if ($mulai->diffInHours($potensiSelesai) > 72) {
            throw ValidationException::withMessages([
                'anti_sniping_max_extensions' => 'Durasi maksimal lelang (termasuk anti-sniping) tidak boleh melebihi 72 jam.',
            ]);
        }
    }

    private function validatePaymentPolicyInput($validator, array $input): void
    {
        $initialRaw = array_key_exists('payment_deadline_initial_minutes', $input)
            ? $input['payment_deadline_initial_minutes']
            : ($input['payment_deadline_minutes'] ?? null);

        $defaultDeadline = $this->defaultPaymentDeadlineMinutes();
        $initial = max(1, min(4320, (int) ($initialRaw ?: $defaultDeadline)));

        if ($initial !== $defaultDeadline) {
            $validator->errors()->add('payment_deadline_minutes', "Deadline pembayaran wajib {$defaultDeadline} menit sesuai aturan marketplace.");
        }
    }

    private function validateVideoDurationInput($validator, Request $request): void
    {
        if (! $request->hasFile('video')) {
            return;
        }

        $durationSeconds = $request->input('video_duration_seconds');

        if ($durationSeconds === null || $durationSeconds === '') {
            $validator->errors()->add('video', 'Durasi video tidak dapat diverifikasi. Pilih ulang video Anda.');

            return;
        }

        if (! is_numeric($durationSeconds)) {
            $validator->errors()->add('video', 'Durasi video tidak valid.');

            return;
        }

        if ((float) $durationSeconds > 30) {
            $validator->errors()->add('video', 'Durasi video maksimal 30 detik.');
        }
    }

    private function resolvePaymentPolicyInputs(array $input): array
    {
        $legacyOrInitial = array_key_exists('payment_deadline_initial_minutes', $input)
            ? $input['payment_deadline_initial_minutes']
            : ($input['payment_deadline_minutes'] ?? null);

        $defaultDeadline = $this->defaultPaymentDeadlineMinutes();
        $initial = max(1, min(240, (int) ($legacyOrInitial ?: $defaultDeadline)));

        return [
            'payment_deadline_minutes' => $initial,
            'payment_deadline_initial_minutes' => $initial,
        ];
    }

    private function defaultPaymentDeadlineMinutes(): int
    {
        return app(\App\Services\SystemSettingService::class)->paymentDeadlineMinutes();
    }

    /**
     * @return array{enabled: bool, window_seconds: int, extend_seconds: int, max_extensions: int}
     */
    private function antiSnipingDefaults(SystemSettingService $settings): array
    {
        return [
            'enabled' => $settings->antiSnipingEnabledByDefault(),
            'window_seconds' => $settings->antiSnipingWindowSeconds(),
            'extend_seconds' => $settings->antiSnipingExtendSeconds(),
            'max_extensions' => $settings->antiSnipingMaxExtensions(),
        ];
    }

    /**
     * @return array{anti_sniping_window_seconds: int, anti_sniping_extend_seconds: int, anti_sniping_max_extensions: int}
     */
    private function resolveAntiSnipingInputs(): array
    {
        $settings = app(SystemSettingService::class);

        return [
            'anti_sniping_window_seconds' => $settings->antiSnipingWindowSeconds(),
            'anti_sniping_extend_seconds' => $settings->antiSnipingExtendSeconds(),
            'anti_sniping_max_extensions' => $settings->antiSnipingMaxExtensions(),
        ];
    }

    private function safeReturnUrl(mixed $candidate, string $fallback): string
    {
        return safeInternalReturnUrl($candidate, $fallback);
    }

    private function pastikanMilikPenjual(Ikan $ikan): void
    {
        if (! auth()->check() || $ikan->user_id !== auth()->id()) {
            abort(403);
        }
    }

    private function bolehEdit(Ikan $ikan): bool
    {
        return $ikan->status !== 'aktif' && now()->lt($ikan->waktu_mulai);
    }
}
