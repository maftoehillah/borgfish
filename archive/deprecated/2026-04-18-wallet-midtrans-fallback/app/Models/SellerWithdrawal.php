<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerWithdrawal extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'status',
        'bank_name',
        'account_number',
        'account_holder_name',
        'seller_note',
        'review_note',
        'transfer_reference',
        'reviewed_by_id',
        'paid_by_id',
        'requested_at',
        'reviewed_at',
        'approved_at',
        'paid_at',
        'rejected_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    public function paidBy()
    {
        return $this->belongsTo(User::class, 'paid_by_id');
    }

    public function ledgerEntries()
    {
        return $this->hasMany(SellerWalletLedger::class, 'reference_id')
            ->where('reference_type', 'seller_withdrawals');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
