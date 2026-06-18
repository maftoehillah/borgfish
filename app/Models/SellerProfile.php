<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerProfile extends Model
{
    protected $fillable = [
        'user_id',
        'store_name',
        'store_location',
        'full_address',
        'supporting_information',
        'store_latitude',
        'store_longitude',
        'store_gps_accuracy',
        'store_gps_captured_at',
        'store_photo_path',
        'bank_name',
        'bank_account_number',
        'bank_account_name',
    ];

    protected $casts = [
        'store_latitude' => 'decimal:7',
        'store_longitude' => 'decimal:7',
        'store_gps_accuracy' => 'decimal:2',
        'store_gps_captured_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
