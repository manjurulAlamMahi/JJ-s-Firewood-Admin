<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductOtherImage extends Model
{
    protected $fillable = ['product_id', 'other_image'];

    protected $hidden = ['other_image']; 
    protected $appends = ['full_image_url'];

    public function getFullImageUrlAttribute()
    {
        // other_image is already a full URL from its accessor below.
        return $this->other_image;
    }

    public function getOtherImageAttribute($value)
    {
        return image_asset($value);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
