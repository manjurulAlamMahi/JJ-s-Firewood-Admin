<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceSubCategoryFeature extends Model
{
    protected $fillable = ['sub_category_id', 'name'];

    public function subCategory()
    {
        return $this->belongsTo(ServiceSubCategory::class, 'sub_category_id');
    }
}

