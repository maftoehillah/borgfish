<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerWalletLedger extends Model
{
    protected $fillable = [
        'user_id',
        'entry_type',
        'reference_type',
        'reference_id',
        'available_delta',
        'pending_delta',
        'balance_after',
        'pending_after',
        'note',
    ];

    protected $casts = [
        'available_delta' => 'decimal:2',
        'pending_delta' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'pending_after' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
