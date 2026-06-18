<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerSettlementBatch extends Model
{
    protected $fillable = [
        'batch_number',
        'status',
        'total_amount',
        'settlement_count',
        'transfer_reference',
        'transfer_proof_path',
        'admin_note',
        'created_by_id',
        'processed_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function settlements()
    {
        return $this->hasMany(SellerSettlement::class, 'batch_id')->orderBy('id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
