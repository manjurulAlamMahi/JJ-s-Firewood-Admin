<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipsAndGuides extends Model
{
    protected $table = 'tips_and_guides';

    protected $fillable = [
        'title',
        'short_description',
        'long_description',
        'image',
        'status',
        'is_featured',
        'category',
    ];
}
