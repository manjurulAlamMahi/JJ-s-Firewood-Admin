<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Category extends Model
{
    protected $fillable = ['name', 'image', 'status', 'inapp_name'];

    public function subCategories()
    {
        return $this->hasMany(SubCategory::class);
    }

    public function getImageAttribute($value)
    {
        return image_asset($value);
    }
}
