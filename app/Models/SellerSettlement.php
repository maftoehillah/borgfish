<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerSettlement extends Model
{
    protected $fillable = [
        'transaksi_id',
        'seller_id',
        'batch_id',
        'amount',
        'status',
        'bank_name',
        'bank_account_number',
        'bank_account_name',
        'admin_note',
        'hold_reason',
        'transfer_reference',
        'transfer_proof_path',
        'created_by_id',
        'updated_by_id',
        'ready_to_pay_at',
        'held_at',
        'paid_at',
        'cancelled_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'ready_to_pay_at' => 'datetime',
        'held_at' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function transaksi()
    {
        return $this->belongsTo(Transaksi::class);
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function batch()
    {
        return $this->belongsTo(SellerSettlementBatch::class, 'batch_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }
}
