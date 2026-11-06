<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Wishlist;
use Illuminate\Support\Str;
use App\Models\ReferralUsage;

class User extends Authenticatable implements JWTSubject
{
    use HasRoles;
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use TwoFactorAuthenticatable;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'apple_id',
        'provider',
        'phone',
        'avatar',
        'password',
        'otp',
        'otp_created_at',
        'device_id',
        'device_token',
        'fcm_token',
        'is_guest',
        'role',
        'is_admin',
        'permanent_entries',
        'loyalty_points',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
        'profile_photo_url',
        'avatar',
    ];

    protected $appends = [
        'avatar_url',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'fcm_token_validated_at' => 'datetime',
        'password' => 'hashed',
    ];

     public function generateReferralCode()
    {
        if (!$this->referral_code) {
            do {
                $prefix = 'JJS';
                $suffix = strtoupper(Str::random(8));
                $code = $prefix . '-' . $suffix;
            } while(User::where('referral_code', $code)->exists());

            $this->referral_code = $code;
            $this->save();
        }
        return $this->referral_code;
    }

    public function referralsMade()
    {
        return $this->hasMany(ReferralUsage::class, 'referrer_user_id');
    }

    public function getAvatarUrlAttribute()
    {
        return image_asset($this->avatar ? 'uploads/avatars/' . $this->avatar : null);
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function couponUsages()
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function prizes()
    {
        return $this->hasMany(Prize::class);
    }

    public function wonGiveaways()
    {
        return $this->hasMany(Giveaway::class, 'winner_id');
    }

    public function pointHistories()
    {
        return $this->hasMany(PointHistory::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function notificationPreferences()
    {
        return $this->hasMany(NotificationPreference::class);
    }

    public function preferenceFor(string $category): ?NotificationPreference
    {
        return $this->notificationPreferences()->where('category', $category)->first();
    }

    public function entries()
    {
        return $this->hasMany(Entry::class);
    }

    // NEW: Shipping Addresses relationship for Customer Management
    public function shippingAddresses()
    {
        return $this->hasMany(ShippingAddress::class, 'user_id');
    }

}
