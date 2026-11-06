<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected $fillable = [
        'system_title',
        'system_short_title',
        'system_logo',
        'system_favicon',
        'company_name',
        'company_address',
        'tagline',
        'phone',
        'email',
        'timezone',
        'language',
        'copyright_text',
        'site_name',
        'designer_name',
        'is_bundles_enabled',
    ];

    protected $casts = [
        'is_bundles_enabled' => 'boolean',
    ];

    protected $appends = [
        'system_logo_url',
        'system_favicon_url',
    ];

    /**
     * Get cached settings (single row only)
     */
    public static function getCached()
    {
        return Cache::rememberForever('system_settings', function () {
            return static::firstOrCreate(['id' => 1]);
        });
    }

    public static function clearCache()
    {
        Cache::forget('system_settings');
    }

    // ------------------------
    // File URL Accessors
    // ------------------------
    public function getSystemLogoUrlAttribute()
    {
        return image_asset($this->system_logo);
    }

    public function getSystemFaviconUrlAttribute()
    {
        return image_asset($this->system_favicon);
    }

    public static function getSettings()
    {
        return self::getCached();
    }
}
