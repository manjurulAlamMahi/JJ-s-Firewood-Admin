<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingAddress extends Model
{
    protected $fillable = [
        'user_id',
        'label',
        'full_name',
        'phone',
        'email',
        'address',
        'suburb',
        'city',
        'state',
        'country',
        'zip_code',
        'is_default'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
