<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Blog extends Model
{
    protected $fillable = [
        'title',
        'short_description',
        'long_description',
        'image',
        'status',
        'published_at',
        'featured',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'featured' => 'boolean',
    ];
}
