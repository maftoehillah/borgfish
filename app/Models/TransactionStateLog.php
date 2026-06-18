<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionStateLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'transaksi_id',
        'from_state',
        'to_state',
        'event_name',
        'actor_type',
        'actor_id',
        'reason_code',
        'reason_text',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function transaksi()
    {
        return $this->belongsTo(Transaksi::class);
    }
}
