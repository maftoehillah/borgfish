<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationOutbox extends Model
{
    protected $table = 'notification_outbox';

    protected $fillable = [
        'recipient_user_id',
        'recipient_role',
        'category',
        'title',
        'message',
        'payload',
        'status',
        'attempts',
        'last_error',
        'available_at',
        'processed_at',
        'idempotency_key',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'available_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
