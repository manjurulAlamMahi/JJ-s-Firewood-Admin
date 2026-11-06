<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'giveaway_id', 'bundle_id', 'product_name',
        'quantity', 'price', 'subtotal'
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'giveaway_id' => 'integer',
        'product_id' => 'integer',
        'bundle_id' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function giveaway()
    {
        return $this->belongsTo(Giveaway::class);
    }

    public function bundle()
    {
        return $this->belongsTo(Bundle::class);
    }

    public function bundleExtras()
    {
        return $this->hasMany(OrderItemBundleExtra::class);
    }
}
