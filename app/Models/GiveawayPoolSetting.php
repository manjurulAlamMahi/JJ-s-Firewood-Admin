<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiveawayPoolSetting extends Model
{
    protected $fillable = [
        'enabled',
        'draw_day',
        'draw_time',
        'rotation_mode',
        'next_template_id',
        'last_heartbeat_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'draw_day' => 'integer',
        'last_heartbeat_at' => 'datetime',
    ];

    public const ROTATION_MODES = [
        'random_no_repeat' => 'Random (never the same prize two weeks in a row)',
        'random' => 'Fully random',
        'shuffle' => 'Shuffle (every prize used once before any repeats)',
    ];

    public const DAYS = [
        0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday',
        4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday',
    ];

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], [
            'enabled' => true,
            'draw_day' => 5,
            'draw_time' => '20:00:00',
            'rotation_mode' => 'random_no_repeat',
        ]);
    }

    public function nextTemplate()
    {
        return $this->belongsTo(GiveawayTemplate::class, 'next_template_id');
    }
}
