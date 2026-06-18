<?php

namespace App\Http\Controllers;

use App\Models\Bid;
use App\Models\Ikan;
use App\Models\Transaksi;
use App\Services\LelangService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PenawaranController extends Controller
{
    public function __construct(private LelangService $lelang) {}

    public function store(Request $request, Ikan $ikan)
    {
        $returnUrl = $this->safeReturnUrl(
            $request->input('return_url'),
            route('ikans.show', $ikan)
        );

        if (! auth()->check()) {
            return redirect()->route('login')->with('error', 'Login dulu untuk bid.');
        }

        if ((int) $ikan->user_id === (int) auth()->id()) {
            return redirect()->to($returnUrl)->with('error', $this->ownLotBlockedMessage('bid'));
        }

        $eligibilityRedirect = $this->ensureBidderEligible($returnUrl);
        if ($eligibilityRedirect) {
            return $eligibilityRedirect;
        }

        $validator = Validator::make(
            $request->all(),
            [
                'jumlah_bid' => ['required', 'integer', 'min:1000'],
            ],
            [
                'jumlah_bid.integer' => 'Nominal bid harus bilangan bulat rupiah (tanpa desimal).',
                'jumlah_bid.min' => 'Nominal bid minimal Rp 1.000.',
            ]
        );

        if ($validator->fails()) {
            return redirect()->to($returnUrl)
                ->withErrors($validator)
                ->withInput();
        }

        $submittedBid = (int) $request->input('jumlah_bid');
        $antiSnipingExtended = false;
        $buyNowTriggered = false;
        $buyNowFinalPrice = null;

        try {
            DB::transaction(function () use ($request, $submittedBid, $ikan, &$antiSnipingExtended, &$buyNowTriggered, &$buyNowFinalPrice): void {
                $lot = Ikan::query()
                    ->lockForUpdate()
                    ->findOrFail($ikan->id);

                if ($lot->status === 'menunggu' && now()->between($lot->waktu_mulai, $lot->waktu_selesai)) {
                    $lot->status = 'aktif';
                    $lot->auction_state = LelangService::STATE_AKTIF;
                    $lot->bumpStateVersion();
                    $lot->save();
                }

                if (now()->gte($lot->waktu_selesai)) {
                    $lot->status = 'selesai';
                    $lot->save();

                    throw ValidationException::withMessages([
                        'jumlah_bid' => 'Waktu lelang sudah habis.',
                    ]);
                }

                if ($lot->status !== 'aktif' || now()->lt($lot->waktu_mulai)) {
                    throw ValidationException::withMessages([
                        'jumlah_bid' => 'Lelang ini belum aktif atau sudah selesai.',
                    ]);
                }

                $this->ensureNotSpammyBid($lot, (int) auth()->id());
                $this->validateBidAmount($lot, (float) $submittedBid);

                $normalizedIp = Bid::normalizeIp($request->ip());
                [$isSuspicious, $suspicionReason] = $this->detectBidAnomaly($lot, (int) auth()->id(), (float) $submittedBid, $normalizedIp);

                Bid::query()->create([
                    'ikan_id' => $lot->id,
                    'user_id' => auth()->id(),
                    'jumlah_bid' => $submittedBid,
                    'bidder_ip' => $normalizedIp,
                    'bidder_user_agent' => substr((string) $request->userAgent(), 0, 255),
                    'is_suspicious' => $isSuspicious,
                    'suspicion_reason' => $suspicionReason,
                ]);

                if ($lot->isLelangTurun()) {
                    $lot->harga_tertinggi = (float) (Bid::query()
                        ->where('ikan_id', $lot->id)
                        ->max('jumlah_bid') ?? $submittedBid);
                } else {
                    $lot->harga_tertinggi = $submittedBid;
                }

                $lot->last_bidder_id = auth()->id();
                $lot->last_bid_at = now();

                if ($lot->applyAntiSnipingIfNeeded(now())) {
                    $antiSnipingExtended = true;
                }

                $buyNowTarget = $lot->buyNowTarget();
                $buyNowRespectsReserve = $buyNowTarget !== null
                    && ($lot->reserve_price === null || (float) $buyNowTarget >= (float) $lot->reserve_price);

                if ($lot->hasReachedBuyNow((float) $submittedBid) && $buyNowRespectsReserve && ! $lot->transaksi()->exists()) {
                    $buyNowFinalPrice = $buyNowTarget ?? $submittedBid;
                    $lot->harga_tertinggi = $buyNowFinalPrice;
                    $lot->status = 'selesai';
                    $lot->waktu_selesai = now();
                    $buyNowTriggered = true;
                }

                $lot->bumpStateVersion();
                $lot->save();
            }, 3);
        } catch (ValidationException $e) {
            return redirect()->to($returnUrl)
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Throwable $e) {
            report($e);

            return redirect()->to($returnUrl)->with('error', 'Terjadi kendala saat memproses bid, silakan coba lagi.');
        }

        if ($buyNowTriggered) {
            $this->lelang->tutupLelang($ikan->fresh());
            $transaksiId = Transaksi::query()->where('ikan_id', $ikan->id)->value('id');

            if ($transaksiId) {
                return redirect()->route('pembayaran.show', ['transaksi' => $transaksiId, 'return_url' => $returnUrl])
                    ->with('sukses', 'Beli sekarang tercapai. Silakan lanjutkan pembayaran.');
            }

            return redirect()->to($returnUrl)
                ->with('sukses', 'Beli sekarang tercapai. Lelang ditutup otomatis di harga ' . formatRupiah($buyNowFinalPrice ?? $submittedBid));
        }

        $message = 'Bid berhasil dipasang! ' . formatRupiah($submittedBid);
        if ($antiSnipingExtended) {
            $message .= ' Waktu lelang diperpanjang otomatis (anti-sniping).';
        }

        return redirect()->to($returnUrl)->with('sukses', $message);
    }

    public function buyNow(Request $request, Ikan $ikan)
    {
        $returnUrl = $this->safeReturnUrl(
            $request->input('return_url'),
            route('ikans.show', $ikan)
        );

        if (! auth()->check()) {
            return redirect()->route('login')->with('error', 'Login dulu untuk membeli langsung.');
        }

        if ((int) $ikan->user_id === (int) auth()->id()) {
            return redirect()->to($returnUrl)->with('error', $this->ownLotBlockedMessage('buy_now'));
        }

        $eligibilityRedirect = $this->ensureBidderEligible($returnUrl);
        if ($eligibilityRedirect) {
            return $eligibilityRedirect;
        }

        if (! $ikan->canBuyNow()) {
            return redirect()->to($returnUrl)->with('error', 'Beli sekarang tidak tersedia untuk lot ini.');
        }

        try {
            $transaksi = $this->lelang->selesaikanDenganBuyNow(
                $ikan,
                (int) auth()->id(),
                $request->ip(),
                $request->userAgent()
            );

            if (! $transaksi) {
                return redirect()->to($returnUrl)->with('error', 'Beli sekarang gagal diproses, kemungkinan lot sudah berubah status.');
            }

            if ((int) $transaksi->pemenang_id !== (int) auth()->id()) {
                return redirect()->to($returnUrl)->with('error', 'Lot sudah dibeli pengguna lain beberapa detik lebih cepat.');
            }

            return redirect()->route('pembayaran.show', ['transaksi' => $transaksi, 'return_url' => $returnUrl])
                ->with('sukses', 'Beli sekarang berhasil. Silakan selesaikan pembayaran sebelum tenggat.');
        } catch (\Throwable $e) {
            report($e);

            return redirect()->to($returnUrl)->with('error', 'Terjadi kendala saat memproses beli sekarang.');
        }
    }

    private function validateBidAmount(Ikan $lot, float $amount): void
    {
        if ($lot->isLelangTurun()) {
            if (((int) $amount) % 1000 !== 0) {
                throw ValidationException::withMessages([
                    'jumlah_bid' => 'Pada lelang turun, nominal bid harus kelipatan Rp 1.000.',
                ]);
            }

            if ($amount >= (float) $lot->harga_awal) {
                throw ValidationException::withMessages([
                    'jumlah_bid' => 'Pada lelang turun, bid harus lebih rendah dari harga patokan.',
                ]);
            }

            return;
        }

        $hargaSaatIni = (float) $lot->harga_tertinggi;
        $bidThreshold = $lot->bidMinimal();

        if ($amount <= $hargaSaatIni) {
            throw ValidationException::withMessages([
                'jumlah_bid' => 'Bid harus lebih tinggi dari harga tertinggi saat ini.',
            ]);
        }

        if ($amount < $bidThreshold) {
            throw ValidationException::withMessages([
                'jumlah_bid' => 'Bid minimal adalah ' . formatRupiah($bidThreshold),
            ]);
        }
    }

    private function ensureNotSpammyBid(Ikan $lot, int $userId): void
    {
        $cooldownSeconds = max(1, (int) config('marketplace.bid_spam_cooldown_seconds', 2));
        $lastBid = Bid::query()
            ->where('ikan_id', $lot->id)
            ->where('user_id', $userId)
            ->latest('id')
            ->first();

        if ($lastBid && $lastBid->created_at && $lastBid->created_at->gt(now()->subSeconds($cooldownSeconds))) {
            throw ValidationException::withMessages([
                'jumlah_bid' => 'Terlalu cepat memasang bid. Tunggu beberapa detik lalu coba lagi.',
            ]);
        }
    }

    private function detectBidAnomaly(Ikan $lot, int $userId, float $amount, ?string $normalizedIp): array
    {
        $recentBidCount = Bid::query()
            ->where('ikan_id', $lot->id)
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->subMinute())
            ->count();

        $ipCandidates = Bid::ipCandidates($normalizedIp);

        $ipDipakaiAkunLainLot = ! empty($ipCandidates)
            && Bid::query()
                ->where('ikan_id', $lot->id)
                ->whereIn('bidder_ip', $ipCandidates)
                ->where('user_id', '!=', $userId)
                ->exists();

        $ipDipakaiAkunLainGlobal = ! empty($ipCandidates)
            && Bid::query()
                ->whereIn('bidder_ip', $ipCandidates)
                ->where('user_id', '!=', $userId)
                ->exists();

        return Bid::deteksiAnomali(
            $amount,
            (float) $lot->harga_tertinggi,
            $recentBidCount,
            $ipDipakaiAkunLainLot,
            $ipDipakaiAkunLainGlobal
        );
    }

    private function ensureBidderEligible(string $returnUrl): ?\Illuminate\Http\RedirectResponse
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        if (config('app.bypass_onboarding', false)) {
            return $user->canActAsPembeli()
                ? null
                : redirect()->to($returnUrl)
                    ->with('error', 'Switch ke mode pembeli terlebih dahulu untuk ikut bidding.');
        }

        if ($user->needsOnboarding()) {
            return redirect()->route('auth.onboarding.show')
                ->with('error', 'Lengkapi data akun dan verifikasi WhatsApp sebelum ikut bidding.');
        }

        if (! $user->canActAsPembeli()) {
            return redirect()->to($returnUrl)
                ->with('error', 'Switch ke mode pembeli terlebih dahulu untuk ikut bidding.');
        }

        if (! $user->hasVerifiedWhatsapp()) {
            return redirect()->route('auth.otp.challenge')
                ->with('error', 'Verifikasi OTP WhatsApp diperlukan sebelum ikut bidding.');
        }

        if ($user->isBanned()) {
            return redirect()->to($returnUrl)->with('error', 'Akun Anda diblokir permanen dari aktivitas bidding.');
        }

        if ($user->isSuspended()) {
            $until = $user->suspended_until?->format('d M Y H:i') ?? 'waktu yang belum ditentukan';

            return redirect()->to($returnUrl)->with('error', 'Akun Anda sedang disuspend sampai ' . $until . '.');
        }

        return null;
    }

    private function ownLotBlockedMessage(string $action): string
    {
        $user = auth()->user();
        $verb = $action === 'buy_now' ? 'beli sekarang' : 'bid';

        if ($user?->isSuperAdmin() && $user->superAdminViewMode() === 'PEMBELI') {
            return "Lot ini dibuat oleh akun admin yang sama, jadi tidak bisa {$verb} dari mode pembeli. Gunakan akun pembeli lain atau lot milik penjual lain untuk uji bidding.";
        }

        return $action === 'buy_now'
            ? 'Anda tidak bisa membeli ikan milik sendiri.'
            : 'Anda tidak bisa bid ikan milik sendiri.';
    }

    private function safeReturnUrl(mixed $candidate, string $fallback): string
    {
        return safeInternalReturnUrl($candidate, $fallback);
    }
}
