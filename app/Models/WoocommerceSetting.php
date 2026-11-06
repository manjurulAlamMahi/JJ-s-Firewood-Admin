<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WoocommerceSetting extends Model
{
    protected $fillable = [
        'site_url',
        'consumer_key',
        'consumer_secret',
        'webhook_secret',
        'sync_enabled',
        'last_sync_at',
        'last_sync_result',
    ];

    protected $casts = [
        'sync_enabled'      => 'boolean',
        'last_sync_at'      => 'datetime',
        'last_sync_result'  => 'array',
    ];

    /**
     * Get the singleton settings row, creating one if missing.
     */
    public static function singleton(): self
    {
        return static::firstOrCreate([], [
            'sync_enabled' => false,
        ]);
    }

    public function isConfigured(): bool
    {
        return !empty($this->site_url) && !empty($this->consumer_key) && !empty($this->consumer_secret);
    }
}
