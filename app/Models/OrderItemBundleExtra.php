<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItemBundleExtra extends Model
{
    protected $fillable = [
        'order_item_id',
        'product_id',
        'product_name',
        'quantity',
        'price',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'price' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
