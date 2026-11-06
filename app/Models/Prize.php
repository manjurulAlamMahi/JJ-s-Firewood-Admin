<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prize extends Model
{
    protected $fillable = [
        'user_id',
        'giveaway_id',
        'prize_value',
        'won_date',
        'status',
        'shipping_address',
        'tracking_number',
    ];

    protected $casts = [
        'won_date' => 'date',
        'prize_value' => 'decimal:2',
        'claim_expire_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function giveaway()
    {
        return $this->belongsTo(Giveaway::class);
    }

    /**
     * UTC Date Accessor
     */
    public function getWonDateUtcAttribute()
    {
        if (!$this->won_date) return null;
        // Shift from UTC+06:00 (user's local) to UTC
        return \Carbon\Carbon::parse($this->won_date->format('Y-m-d') . ' 00:00:00', '+06:00')
            ->setTimezone('UTC');
    }
}
