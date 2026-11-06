<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BundleExtra extends Model
{
    protected $fillable = [
        'bundle_id',
        'product_id',
        'is_required',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    public function bundle()
    {
        return $this->belongsTo(Bundle::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
