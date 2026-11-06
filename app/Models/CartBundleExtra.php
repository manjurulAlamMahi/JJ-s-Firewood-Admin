<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartBundleExtra extends Model
{
    protected $fillable = [
        'cart_id',
        'product_id',
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
