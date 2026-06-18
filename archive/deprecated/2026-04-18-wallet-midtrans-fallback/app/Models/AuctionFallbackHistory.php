<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuctionFallbackHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ikan_id',
        'from_rank',
        'to_rank',
        'reason',
        'fallback_count_after',
        'triggered_by_type',
        'triggered_by_id',
        'created_at',
    ];

    protected $casts = [
        'from_rank' => 'integer',
        'to_rank' => 'integer',
        'fallback_count_after' => 'integer',
        'created_at' => 'datetime',
    ];

    public function ikan()
    {
        return $this->belongsTo(Ikan::class);
    }

    public function triggeredBy()
    {
        return $this->belongsTo(User::class, 'triggered_by_id');
    }
}
