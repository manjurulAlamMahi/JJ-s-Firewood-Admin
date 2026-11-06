<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductUpsell extends Model
{
    protected $fillable = [
        'product_id',
        'upsell_product_id',
        'type',
        'sort_order',
        'status',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function upsellProduct()
    {
        return $this->belongsTo(Product::class, 'upsell_product_id');
    }
}
