<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $table = 'banners';

    protected $fillable = [
        'title',
        'sub_title',
        'image',
    ];

    public function getImageAttribute($value)
    {
        return image_asset($value);
    }
}
