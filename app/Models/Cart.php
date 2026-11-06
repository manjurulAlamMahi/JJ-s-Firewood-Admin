<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Giveaway;

class Cart extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
        'giveaway_id',
        'bundle_id',
        'quantity',
        'price',
        'subtotal',
        'delivery_type_id',
        'coupon_code',
        'cart_reminder_sent_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'delivery_type_id' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function giveaway()
    {
        return $this->belongsTo(Giveaway::class, 'giveaway_id');
    }

    public function bundle()
    {
        return $this->belongsTo(Bundle::class);
    }

    public function bundleExtras()
    {
        return $this->hasMany(CartBundleExtra::class);
    }
}
