<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReturnsNoStoreJson;
use App\Models\PaymentAttempt;
use App\Models\Transaksi;
use App\Services\LelangService;
use App\Services\NotificationOutboxService;
use App\Services\PembayaranService;
use Illuminate\Http\Request;

class PembayaranController extends Controller
{
    use ReturnsNoStoreJson;

    public function show(Transaksi $transaksi, PembayaranService $service)
    {
        $this->authorizeBuyer($transaksi);
        $this->expireIfNeeded($transaksi);

        $transaksi->refresh();
        $this->authorizeBuyer($transaksi);

        $transaksi->load(['ikan', 'pemenang', 'paymentAttempts' => fn ($query) => $query->latest('id')]);

        $paymentMethods = $service->availableMethods();

        return view('pembayaran.show', [
            'transaksi' => $transaksi,
            'paymentMethods' => $paymentMethods,
            'defaultPaymentMethod' => $service->defaultMethod($paymentMethods),
            'latestPayment' => $transaksi->paymentAttempts->first(),
        ]);
    }

    public function createAttempt(Request $request, Transaksi $transaksi, PembayaranService $service)
    {
        $this->authorizeBuyer($transaksi);
        $this->expireIfNeeded($transaksi);

        $transaksi->refresh();
        $this->authorizeBuyer($transaksi);

        if (! $transaksi->isBelumBayar()) {
            return $this->noStoreJson(['error' => 'Transaksi ini tidak dalam status menunggu pembayaran.'], 422);
        }

        $methods = $service->availableMethods();
        $validated = $request->validate([
            'payment_method' => ['nullable', 'string', 'max:50'],
        ]);

        $methodCode = $service->resolvePaymentMethod($validated['payment_method'] ?? null, $methods);

        try {
            $attempt = $service->createAttempt($transaksi, $methodCode);

            return $this->noStoreJson($attempt);
        } catch (\RuntimeException $e) {
            return $this->noStoreJson(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);

            return $this->noStoreJson(['error' => 'Terjadi kendala sistem pembayaran. Silakan coba beberapa saat lagi.'], 500);
        }
    }

    public function webhook(Request $request, PembayaranService $service)
    {
        try {
            $result = $service->handleCallback($request);

            return $this->noStoreJson([
                'success' => true,
                ...$result,
            ]);
        } catch (\RuntimeException $e) {
            report($e);

            return $this->noStoreJson(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);

            return $this->noStoreJson(['error' => 'Webhook TriPay gagal diproses.'], 500);
        }
    }

    public function refreshStatus(Transaksi $transaksi, PembayaranService $service)
    {
        $this->authorizeBuyer($transaksi);
        $this->expireIfNeeded($transaksi);

        $transaksi->refresh();
        $this->authorizeBuyer($transaksi);

        try {
            $result = $service->refreshPendingAttempt($transaksi);

            return $this->noStoreJson($result);
        } catch (\RuntimeException $e) {
            return $this->noStoreJson(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);

            return $this->noStoreJson(['error' => 'Gagal menyinkronkan status pembayaran.'], 500);
        }
    }

    public function selesai(Request $request, Transaksi $transaksi)
    {
        $this->authorizeBuyer($transaksi);

        $fallbackReturnUrl = route('ikans.show', [
            'ikan' => $transaksi->ikan_id,
            'return_url' => route('ikans.index'),
        ]);

        $safeReturnUrl = $this->safeReturnUrl($request->query('return_url'), $fallbackReturnUrl);

        $transaksi->load('ikan');

        return view('pembayaran.selesai', compact('transaksi', 'safeReturnUrl'));
    }

    private function expireIfNeeded(Transaksi $transaksi): void
    {
        if (! $transaksi->isKadaluarsa() || $transaksi->isLunas()) {
            return;
        }

        app(LelangService::class)->handleExpiredTransaction($transaksi, 'controller');
        app(NotificationOutboxService::class)->processPending(50);
        $transaksi->refresh();
    }

    private function authorizeBuyer(Transaksi $transaksi): void
    {
        if ((int) auth()->id() === (int) $transaksi->pemenang_id) {
            return;
        }

        if ($this->hasExpiredAttemptForCurrentUser($transaksi)) {
            abort(403, $this->expiredPaymentAccessMessage());
        }

        abort(403);
    }

    private function hasExpiredAttemptForCurrentUser(Transaksi $transaksi): bool
    {
        $userId = (int) auth()->id();
        if ($userId <= 0) {
            return false;
        }

        return PaymentAttempt::query()
            ->where('ikan_id', (int) $transaksi->ikan_id)
            ->where('bidder_id', $userId)
            ->whereIn('status_code', ['expired', 'failed'])
            ->exists();
    }

    private function expiredPaymentAccessMessage(): string
    {
        return 'Akses pembayaran sudah tidak tersedia karena waktu bayar habis.';
    }

    private function safeReturnUrl(mixed $candidate, string $fallback): string
    {
        return safeInternalReturnUrl($candidate, $fallback);
    }
}
