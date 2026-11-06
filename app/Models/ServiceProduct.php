<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceProduct extends Model
{
    protected $table = 'service_products';
    protected $fillable = [
        'service_category_id',
        'service_sub_category_id',
        'name',
        'price',
        'installation_included',
    ];

    public function features()
    {
        return $this->hasMany(ServiceProductFeature::class);
    }

    public function serviceCategory()
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function serviceSubCategory()
    {
        return $this->belongsTo(ServiceSubCategory::class, 'service_sub_category_id');
    }

    public function getImageAttribute($value)
    {
        return image_asset($value);
    }
}
