<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BidderPenalty extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'ikan_id',
        'reason',
        'cooldown_until',
        'reputation_delta',
        'created_at',
    ];

    protected $casts = [
        'cooldown_until' => 'datetime',
        'reputation_delta' => 'integer',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ikan()
    {
        return $this->belongsTo(Ikan::class);
    }
}
