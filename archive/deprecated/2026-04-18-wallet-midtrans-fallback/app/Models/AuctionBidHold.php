<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuctionBidHold extends Model
{
    protected $fillable = [
        'ikan_id',
        'user_id',
        'transaksi_id',
        'amount',
        'status',
        'reason',
        'held_at',
        'released_at',
        'captured_at',
        'release_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'held_at' => 'datetime',
        'released_at' => 'datetime',
        'captured_at' => 'datetime',
    ];

    public function ikan()
    {
        return $this->belongsTo(Ikan::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transaksi()
    {
        return $this->belongsTo(Transaksi::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
