<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaldoLedger extends Model
{
    protected $fillable = [
        'user_id',
        'entry_type',
        'reference_type',
        'reference_id',
        'available_delta',
        'held_delta',
        'balance_after',
        'held_after',
        'note',
    ];

    protected $casts = [
        'available_delta' => 'decimal:2',
        'held_delta' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'held_after' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
