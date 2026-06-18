<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InAppNotification extends Model
{
    protected $fillable = [
        'user_id',
        'category',
        'title',
        'message',
        'payload',
        'read_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'read_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
