<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WcOrderItem extends Model
{
    protected $table = 'wc_order_items';

    protected $fillable = [
        'wc_order_id',
        'woocommerce_product_id',
        'woocommerce_variation_id',
        'sku',
        'name',
        'quantity',
        'price',
        'subtotal',
        'total',
        'tax',
        'meta',
    ];

    protected $casts = [
        'meta'     => 'array',
        'quantity' => 'decimal:3',
        'price'    => 'decimal:2',
        'subtotal' => 'decimal:2',
        'total'    => 'decimal:2',
        'tax'      => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(WcOrder::class, 'wc_order_id');
    }
}
