<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuctionStateLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ikan_id',
        'from_state',
        'to_state',
        'event_name',
        'actor_type',
        'actor_id',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function ikan()
    {
        return $this->belongsTo(Ikan::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
