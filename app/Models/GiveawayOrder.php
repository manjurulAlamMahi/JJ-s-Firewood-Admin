<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GiveawayOrder extends Model
{
    protected $fillable = [
        'user_id',
        'giveaway_id',
        'package_id',
        'order_number',
        'subtotal',
        'tax',
        'total',
        'status',
        'payment_method',
        'transaction_id'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            $order->order_number = 'ORD-' . strtoupper(Str::random(10));
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function giveaway()
    {
        return $this->belongsTo(Giveaway::class);
    }

    public function package()
    {
        return $this->belongsTo(EntryPackage::class, 'package_id');
    }
    
}
