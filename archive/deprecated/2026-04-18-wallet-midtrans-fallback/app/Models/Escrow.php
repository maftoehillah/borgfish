<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Escrow extends Model
{
    protected $fillable = [
        'transaction_id',
        'amount',
        'currency',
        'status',
        'held_at',
        'released_at',
        'released_by',
        'external_payment_id',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'meta' => 'array',
        'held_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaksi::class, 'transaction_id');
    }
}
