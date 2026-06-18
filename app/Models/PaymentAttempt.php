<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentAttempt extends Model
{
    protected $fillable = [
        'payment_code',
        'provider',
        'status_code',
        'ikan_id',
        'transaksi_id',
        'bidder_id',
        'rank',
        'amount_due',
        'status',
        'provider_transaction_id',
        'provider_status',
        'payment_method_code',
        'payment_method_name',
        'checkout_url',
        'checkout_expires_at',
        'bayar_sebelum',
        'assigned_at',
        'paid_at',
        'expired_at',
        'failed_at',
        'cancelled_at',
        'refunded_at',
        'payment_provider_ref',
        'idempotency_key',
        'callback_signature',
        'callback_idempotency_key',
        'callback_processed_at',
        'request_payload',
        'callback_payload',
        'retry_of_payment_id',
    ];

    protected $casts = [
        'rank' => 'integer',
        'amount_due' => 'decimal:2',
        'checkout_expires_at' => 'datetime',
        'bayar_sebelum' => 'datetime',
        'assigned_at' => 'datetime',
        'paid_at' => 'datetime',
        'expired_at' => 'datetime',
        'failed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'refunded_at' => 'datetime',
        'callback_processed_at' => 'datetime',
        'request_payload' => 'array',
        'callback_payload' => 'array',
    ];

    public function ikan()
    {
        return $this->belongsTo(Ikan::class);
    }

    public function transaksi()
    {
        return $this->belongsTo(Transaksi::class);
    }

    public function bidder()
    {
        return $this->belongsTo(User::class, 'bidder_id');
    }

    public function retryOf()
    {
        return $this->belongsTo(self::class, 'retry_of_payment_id');
    }

    public function isPending(): bool
    {
        return (string) $this->status_code === 'pending';
    }

    public function markPending(string $provider, ?string $methodCode = null, ?string $methodName = null): void
    {
        $this->provider = $provider;
        $this->status = 'menunggu_pembayaran';
        $this->status_code = 'pending';
        $this->payment_method_code = $methodCode;
        $this->payment_method_name = $methodName;
    }

    public function markPaid(?string $providerStatus = null): void
    {
        $this->status = 'dibayar';
        $this->status_code = 'paid';
        $this->provider_status = $providerStatus;
        $this->paid_at = $this->paid_at ?? now();
    }

    public function markFailed(?string $providerStatus = null): void
    {
        $this->status = 'gagal';
        $this->status_code = 'failed';
        $this->provider_status = $providerStatus;
        $this->failed_at = $this->failed_at ?? now();
    }

    public function markExpired(?string $providerStatus = null): void
    {
        $this->status = 'kadaluarsa';
        $this->status_code = 'expired';
        $this->provider_status = $providerStatus;
        $this->expired_at = $this->expired_at ?? now();
    }

    public function markCancelled(?string $providerStatus = null): void
    {
        $this->status = 'dibatalkan';
        $this->status_code = 'cancelled';
        $this->provider_status = $providerStatus;
        $this->cancelled_at = $this->cancelled_at ?? now();
    }

    public function markRefunded(?string $providerStatus = null): void
    {
        $this->status = 'refund';
        $this->status_code = 'refunded';
        $this->provider_status = $providerStatus;
        $this->refunded_at = $this->refunded_at ?? now();
    }
}
