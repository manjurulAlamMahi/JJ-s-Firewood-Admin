<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceSubCategory extends Model
{
    protected $fillable = [
        'service_category_id',
        'name',
        'inapp_name',
        'description',
        'image',
        'status'
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute()
    {
        return image_asset($this->image);
    }

    public function getImageAttribute($value)
    {
        return image_asset($value);
    }

    public function serviceCategory()
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function features()
    {
        return $this->hasMany(ServiceSubCategoryFeature::class, 'sub_category_id');
    }
}
