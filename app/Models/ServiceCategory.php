<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceCategory extends Model
{
    protected $fillable = ['name', 'inapp_name', 'url', 'image', 'status'];

    // Always return full image URL
    protected $appends = ['image_url'];

    public function getImageUrlAttribute()
    {
        return image_asset($this->image);
    }

    public function getImageAttribute($value)
    {
        return image_asset($value);
    }

    public function subCategories()
    {
        return $this->hasMany(ServiceSubCategory::class);
    }
}
