<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionDispute extends Model
{
    protected $fillable = [
        'transaksi_id',
        'ikan_id',
        'buyer_id',
        'seller_id',
        'status',
        'complaint_reason',
        'complaint_detail',
        'opened_by_type',
        'opened_by_id',
        'opened_at',
        'resolved_by_id',
        'resolution_note',
        'resolved_at',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function transaksi()
    {
        return $this->belongsTo(Transaksi::class);
    }

    public function ikan()
    {
        return $this->belongsTo(Ikan::class);
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
