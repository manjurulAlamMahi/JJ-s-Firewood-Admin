<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubCategory extends Model
{
    protected $fillable = ['category_id', 'name', 'description', 'image', 'position'];

    public function getImageAttribute($value)
    {
        return image_asset($value);
    }

    protected static function boot()
    {
        parent::boot();
        
        // Auto-set position for new subcategories
        static::creating(function ($subcategory) {
            if (is_null($subcategory->position)) {
                $subcategory->position = static::max('position') + 1;
            }
        });
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}