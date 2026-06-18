<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'actor_type',
        'actor_id',
        'action',
        'resource_type',
        'resource_id',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
