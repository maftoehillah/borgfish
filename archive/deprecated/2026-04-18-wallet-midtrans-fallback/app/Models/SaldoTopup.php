<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaldoTopup extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'midtrans_order_id',
        'snap_token',
        'status',
        'payment_method',
        'requested_at',
        'paid_at',
        'expired_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'requested_at' => 'datetime',
        'paid_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ledgerEntries()
    {
        return $this->hasMany(SaldoLedger::class, 'reference_id')
            ->where('reference_type', 'saldo_topups');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired';
    }
}
