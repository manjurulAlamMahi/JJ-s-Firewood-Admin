<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WoodEducationContent extends Model
{
    protected $fillable = [
        'title',
        'short_description',
        'long_description',
        'image',
        'status',
        'published_at',
        'category_id',
        'sub_category_id',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function subCategory()
    {
        return $this->belongsTo(SubCategory::class);
    }
}
