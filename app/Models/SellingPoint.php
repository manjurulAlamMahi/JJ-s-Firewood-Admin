<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellingPoint extends Model
{
    protected $table = 'selling_points';

    protected $fillable = ['name', 'description', 'status',        'image',
    ];

    public function getImageAttribute($value)
    {
        return image_asset($value);
    }
}
