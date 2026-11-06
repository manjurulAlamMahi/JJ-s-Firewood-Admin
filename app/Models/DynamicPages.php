<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DynamicPages extends Model
{
    protected $table = 'dynamic_pages';

    protected $fillable = [
        'title',
        'slug',
        'page_content',
        'status',
    ];
}
