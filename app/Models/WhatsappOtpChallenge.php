<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappOtpChallenge extends Model
{
    protected $fillable = [
        'user_id',
        'phone_number',
        'purpose',
        'otp_hash',
        'session_token',
        'attempts',
        'resend_count',
        'status',
        'expires_at',
        'sent_at',
        'verified_at',
        'cancelled_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'sent_at' => 'datetime',
        'verified_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
