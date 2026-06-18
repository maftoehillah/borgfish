<?php

namespace App\Services;

use App\Models\SaldoTopup;
use DomainException;
use Illuminate\Support\Facades\DB;
use Midtrans\Config;
use Midtrans\Snap;

class SaldoTopupService
{
    public function __construct(
        private readonly SaldoService $saldoService,
        private readonly NotificationOutboxService $notificationOutboxService,
    ) {
    }

    private function configureMidtrans(): void
    {
        $serverKey = trim((string) config('midtrans.server_key'));
        $clientKey = trim((string) config('midtrans.client_key'));
        $isProduction = (bool) config('midtrans.is_production');

        $this->validateConfig($serverKey, $clientKey, $isProduction);

        Config::$serverKey = $serverKey;
        Config::$isProduction = $isProduction;
        Config::$isSanitized = config('midtrans.is_sanitized');
        Config::$is3ds = config('midtrans.is_3ds');
    }

    private function validateConfig(string $serverKey, string $clientKey, bool $isProduction): void
    {
        if ($serverKey === '' || $clientKey === '') {
            throw new \RuntimeException('Konfigurasi Midtrans belum lengkap. Isi MIDTRANS_SERVER_KEY dan MIDTRANS_CLIENT_KEY di file .env.');
        }

        if (str_contains($serverKey, 'XXXXXXXX') || str_contains($clientKey, 'XXXXXXXX')) {
            throw new \RuntimeException('Key Midtrans masih placeholder. Ganti dengan key asli dari dashboard Midtrans.');
        }

        if ($isProduction && str_starts_with($serverKey, 'SB-')) {
            throw new \RuntimeException('MIDTRANS_IS_PRODUCTION=true tetapi server key masih sandbox (SB-).');
        }

        if (! $isProduction && str_starts_with($serverKey, 'Mid-server-')) {
            throw new \RuntimeException('MIDTRANS_IS_PRODUCTION=false tetapi server key terlihat production. Gunakan key sandbox (SB-).');
        }
    }

    public function createTopup(int $userId, float $amount): SaldoTopup
    {
        return SaldoTopup::create([
            'user_id' => $userId,
            'amount' => round($amount, 2),
            'status' => 'pending',
            'requested_at' => now(),
        ]);
    }

    public function getPaymentSession(SaldoTopup $topup): array
    {
        if (! $topup->isPending()) {
            throw new \RuntimeException('Top up ini tidak lagi menunggu pembayaran.');
        }

        $this->configureMidtrans();

        if ($topup->snap_token && $topup->midtrans_order_id) {
            return $this->buildPaymentSession((string) $topup->snap_token);
        }

        $orderId = $topup->midtrans_order_id ?: 'BORGFISH-TOPUP-' . $topup->id;
        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int) round($topup->amount),
            ],
            'customer_details' => [
                'first_name' => $topup->user->name,
                'email' => $topup->user->email,
            ],
            'item_details' => [[
                'id' => 'TOPUP',
                'price' => (int) round($topup->amount),
                'quantity' => 1,
                'name' => 'Top Up Saldo Borgfish',
            ]],
            'expiry' => [
                'unit' => 'hours',
                'duration' => 24,
            ],
            'enabled_payments' => [
                'bca_va',
                'bni_va',
                'bri_va',
                'permata_va',
                'mandiri_bill',
                'other_va',
                'gopay',
                'shopeepay',
                'dana',
                'qris',
                'indomaret',
                'alfamart',
            ],
        ];

        $snapToken = Snap::getSnapToken($params);
        $topup->update([
            'snap_token' => $snapToken,
            'midtrans_order_id' => $orderId,
            'expired_at' => now()->addHours(24),
        ]);

        return $this->buildPaymentSession($snapToken);
    }

    public function handleWebhook(array $notification): void
    {
        $this->configureMidtrans();
        $this->validateWebhookNotification($notification);

        $orderId = (string) ($notification['order_id'] ?? '');
        $transactionStatus = (string) ($notification['transaction_status'] ?? '');
        $paymentType = isset($notification['payment_type'])
            ? (string) $notification['payment_type']
            : null;

        if (! $this->isTopupOrderId($orderId)) {
            return;
        }

        $topup = SaldoTopup::query()
            ->where('midtrans_order_id', $orderId)
            ->first();

        if (!$topup) {
            throw new \RuntimeException('Top up untuk order ini tidak ditemukan.');
        }

        $grossAmount = round((float) ($notification['gross_amount'] ?? 0), 2);
        $expectedAmount = round((float) $topup->amount, 2);
        if (abs($grossAmount - $expectedAmount) > 0.01) {
            throw new \RuntimeException('Nominal webhook top up tidak cocok dengan permintaan.');
        }

        $incomingStatus = $this->resolveStatus($transactionStatus, $topup->status);

        $this->applyStatusChange(
            $topup,
            $incomingStatus,
            $paymentType,
            'Top up saldo berhasil dikonfirmasi via Midtrans.',
            'midtrans_webhook'
        );
    }

    public function markSucceededByAdmin(
        SaldoTopup $topup,
        string $paymentMethod = 'manual_admin',
        ?string $ledgerNote = null
    ): SaldoTopup {
        return $this->applyStatusChange(
            $topup,
            'success',
            $paymentMethod,
            $ledgerNote ?: 'Top up saldo direkonsiliasi manual oleh admin.',
            'admin_manual'
        );
    }

    public function markUnsuccessfulByAdmin(
        SaldoTopup $topup,
        string $status = 'failed',
        ?string $paymentMethod = 'manual_admin'
    ): SaldoTopup {
        if (! in_array($status, ['failed', 'expired'], true)) {
            throw new \RuntimeException('Status rekonsiliasi top up tidak valid.');
        }

        return $this->applyStatusChange(
            $topup,
            $status,
            $paymentMethod,
            null,
            'admin_manual'
        );
    }

    private function resolveStatus(string $transactionStatus, string $fallback): string
    {
        return match ($transactionStatus) {
            'capture', 'settlement' => 'success',
            'pending' => 'pending',
            'deny', 'cancel' => 'failed',
            'expire' => 'expired',
            default => $fallback,
        };
    }

    private function normalizeStatus(string $currentStatus, string $incomingStatus): string
    {
        if ($currentStatus === 'success') {
            return 'success';
        }

        if ($incomingStatus === 'pending' && in_array($currentStatus, ['failed', 'expired'], true)) {
            return $currentStatus;
        }

        return $incomingStatus;
    }

    private function applyStatusChange(
        SaldoTopup $topup,
        string $incomingStatus,
        ?string $paymentType = null,
        ?string $successLedgerNote = null,
        string $notificationSource = 'system'
    ): SaldoTopup {
        return DB::transaction(function () use ($topup, $incomingStatus, $paymentType, $successLedgerNote, $notificationSource) {
            $lockedTopup = SaldoTopup::query()
                ->with('user')
                ->whereKey($topup->id)
                ->lockForUpdate()
                ->firstOrFail();

            $previousStatus = (string) $lockedTopup->status;
            $status = $this->normalizeStatus($previousStatus, $incomingStatus);
            $statusChanged = $status !== $previousStatus;
            $hasLedgerCredit = $lockedTopup->ledgerEntries()->exists();
            $shouldCredit = $status === 'success' && ($previousStatus !== 'success' || ! $hasLedgerCredit);

            $payload = [];
            if ($statusChanged) {
                $payload['status'] = $status;
            }

            if ($paymentType !== null && trim($paymentType) !== '') {
                $payload['payment_method'] = trim($paymentType);
            }

            if ($status === 'success') {
                $payload['paid_at'] = $lockedTopup->paid_at ?? now();
                $payload['expired_at'] = null;
            } elseif ($status === 'expired') {
                $payload['expired_at'] = $lockedTopup->expired_at ?? now();
            } elseif ($status === 'failed' && $previousStatus !== 'success') {
                $payload['paid_at'] = null;
            }

            if (! empty($payload)) {
                $lockedTopup->update($payload);
            }

            if ($shouldCredit) {
                try {
                    $this->saldoService->creditAvailableBalance(
                        $lockedTopup->user_id,
                        (float) $lockedTopup->amount,
                        'topup',
                        'saldo_topups',
                        (int) $lockedTopup->id,
                        $successLedgerNote ?: 'Top up saldo berhasil dikonfirmasi via Midtrans.'
                    );
                } catch (DomainException $e) {
                    throw new \RuntimeException($e->getMessage(), previous: $e);
                }
            }

            if (($statusChanged || $shouldCredit) && in_array($status, ['success', 'failed', 'expired'], true)) {
                $this->queueBuyerNotification($lockedTopup, $status, $notificationSource);
            }

            return $lockedTopup->fresh(['user']);
        });
    }

    private function validateWebhookNotification(array $notification): void
    {
        $orderId = (string) ($notification['order_id'] ?? '');
        $statusCode = (string) ($notification['status_code'] ?? '');
        $grossAmount = (string) ($notification['gross_amount'] ?? '');
        $signatureKey = strtolower((string) ($notification['signature_key'] ?? ''));
        $serverKey = trim((string) config('midtrans.server_key'));

        if ($orderId === '' || $statusCode === '' || $grossAmount === '' || $signatureKey === '') {
            throw new \RuntimeException('Payload webhook Midtrans tidak lengkap.');
        }

        $expectedSignature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        if (! hash_equals(strtolower($expectedSignature), $signatureKey)) {
            throw new \RuntimeException('Signature webhook Midtrans tidak valid.');
        }
    }

    private function buildPaymentSession(string $snapToken): array
    {
        return [
            'snap_token' => $snapToken,
            'client_key' => config('midtrans.client_key'),
            'script_url' => config('midtrans.is_production')
                ? 'https://app.midtrans.com/snap/snap.js'
                : 'https://app.sandbox.midtrans.com/snap/snap.js',
        ];
    }

    private function isTopupOrderId(string $orderId): bool
    {
        return str_starts_with($orderId, 'BORGFISH-TOPUP-')
            || str_starts_with($orderId, 'TOPUP-');
    }

    private function queueBuyerNotification(SaldoTopup $topup, string $status, string $source): void
    {
        $topup->loadMissing('user');

        $title = match ($status) {
            'success' => 'Top up berhasil',
            'failed' => 'Top up gagal',
            'expired' => 'Top up kadaluarsa',
            default => 'Update top up saldo',
        };

        $message = match ($status) {
            'success' => 'Top up '.formatRupiah($topup->amount).' telah dikonfirmasi dan saldo tersedia Anda bertambah.',
            'failed' => 'Pembayaran top up '.formatRupiah($topup->amount).' tidak berhasil. Anda bisa buat permintaan baru kapan saja.',
            'expired' => 'Batas pembayaran top up '.formatRupiah($topup->amount).' sudah berakhir. Buat permintaan baru jika masih ingin menambah saldo.',
            default => 'Status top up saldo Anda telah diperbarui.',
        };

        $this->notificationOutboxService->queue(
            (int) $topup->user_id,
            'saldo',
            $title,
            $message,
            [
                'event' => 'saldo_topup_' . $status,
                'topup_id' => (int) $topup->id,
                'amount' => (float) $topup->amount,
                'status' => $status,
                'source' => $source,
            ],
            $this->buildTopupKey((int) $topup->id, (int) $topup->user_id, $status)
        );
    }

    private function buildTopupKey(int $topupId, int $userId, string $status): string
    {
        return implode(':', [
            'topup',
            $topupId,
            'buyer',
            $userId,
            $status,
        ]);
    }
}
