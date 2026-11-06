<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeatherCampaignSend extends Model
{
    protected $fillable = [
        'weather_campaign_id',
        'user_id',
        'channel',
        'trigger_date',
        'status',
        'weather_snapshot',
        'error',
        'sent_at',
    ];

    protected $casts = [
        'trigger_date' => 'date',
        'weather_snapshot' => 'array',
        'sent_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(WeatherCampaign::class, 'weather_campaign_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
