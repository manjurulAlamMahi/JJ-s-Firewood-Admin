<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PaymentSetting extends Model
{
    protected $guarded = [];

    public static function current(): self
    {
        return Cache::remember('payment_settings_row', 3600, function () {
            return static::first() ?? static::create([]);
        });
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('payment_settings_row'));
    }
}
