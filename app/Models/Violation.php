<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Violation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'ikan_id',
        'transaksi_id',
        'admin_executor_id',
        'role',
        'type',
        'status',
        'action',
        'reason',
        'notes',
        'duration_hours',
        'effective_from',
        'effective_until',
        'resolved_at',
    ];

    protected $casts = [
        'effective_from' => 'datetime',
        'effective_until' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lot()
    {
        return $this->belongsTo(Ikan::class, 'ikan_id');
    }

    public function order()
    {
        return $this->belongsTo(Transaksi::class, 'transaksi_id');
    }

    public function adminExecutor()
    {
        return $this->belongsTo(User::class, 'admin_executor_id');
    }
}
