<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuctionRanking extends Model
{
    protected $fillable = [
        'ikan_id',
        'rank',
        'bidder_id',
        'bid_id',
        'bid_amount',
        'bid_created_at',
        'snapshot_hash',
    ];

    protected $casts = [
        'rank' => 'integer',
        'bid_amount' => 'decimal:2',
        'bid_created_at' => 'datetime',
    ];

    public function ikan()
    {
        return $this->belongsTo(Ikan::class);
    }

    public function bidder()
    {
        return $this->belongsTo(User::class, 'bidder_id');
    }

    public function bid()
    {
        return $this->belongsTo(Bid::class);
    }
}
