<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'subcategory_id',
        'name',
        'short_title',
        'price',
        'quantity',
        'description',
        'image',
        'status',
        'position',
        'size_info',
        'burn_quantity_info',
        'delivery_info',
    ];

    protected $casts = [
        'reviews_avg_rating' => 'float',
        'reviews_count' => 'integer',
    ];

    protected $hidden = ['other_images'];

    protected static function boot()
    {
        parent::boot();
        
        // Auto-set position for new products
        static::creating(function ($product) {
            if (is_null($product->position)) {
                $product->position = static::max('position') + 1;
            }
        });
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory()
    {
        return $this->belongsTo(SubCategory::class);
    }

    public function otherImages()
    {
        return $this->hasMany(ProductOtherImage::class);
    }

    public function getStockStatusAttribute()
    {
        return $this->quantity > 0 ? 'In Stock' : 'Out of Stock';
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function averageRating()
    {
        return round($this->reviews()->avg('rating'), 1);
    }

    public function reviewCount()
    {
        return $this->reviews()->count();
    }

    public function isInStock($quantity)
    {
        return $this->quantity >= $quantity;
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function upsells()
    {
        return $this->hasMany(ProductUpsell::class);
    }

    public function getImageAttribute($value)
    {
        return image_asset($value);
    }

    public function getNumericPriceAttribute()
    {
        $priceValue = $this->price;
        if (is_numeric($priceValue)) {
            return (float) $priceValue;
        }
        $priceValue = preg_replace('/[^0-9.]/', '', (string) $priceValue);
        return $priceValue === '' ? 0.0 : (float) $priceValue;
    }
}