<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralUsage extends Model
{
    protected $fillable = [
        'referrer_user_id',
        'referred_user_id',
        'referral_code',
        'discount_amount',
        'bonus_given',
        'referral_bonus',
        'used_at'
    ];

    public $timestamps = false;

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referrer_user_id');
    }
    public function referred()
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }
}
