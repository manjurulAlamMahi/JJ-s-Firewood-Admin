<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bundle extends Model
{
    protected $fillable = [
        'name',
        'description',
        'image',
        'base_product_id',
        'base_price',
        'discount_percentage',
        'status',
        'position',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
    ];

    public function baseProduct()
    {
        return $this->belongsTo(Product::class, 'base_product_id');
    }

    public function extras()
    {
        return $this->hasMany(BundleExtra::class);
    }

    public function getEffectivePriceAttribute()
    {
        return $this->base_price !== null
            ? (float) $this->base_price
            : $this->baseProduct->numeric_price;
    }

    /**
     * Applies this bundle's discount_percentage to a pre-discount subtotal.
     * Single source of truth for the discount formula — used both when
     * pricing a real cart line (CartController) and when previewing a
     * bundle's price in the customer-facing list/detail API.
     */
    public function applyDiscount(float $subtotal): float
    {
        $percentage = (float) ($this->discount_percentage ?? 0);
        return round($subtotal - ($subtotal * $percentage / 100), 2);
    }
}
