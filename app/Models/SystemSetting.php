<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    public const CACHE_KEY = 'system-settings:all';

    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
    ];

    protected static function booted(): void
    {
        static::saved(fn (): bool => Cache::forget(self::CACHE_KEY));
        static::deleted(fn (): bool => Cache::forget(self::CACHE_KEY));
    }
}
