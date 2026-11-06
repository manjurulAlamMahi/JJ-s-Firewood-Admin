<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipCategory extends Model
{
    protected $fillable = [
        'name',
        'sort_order',
        'status',
    ];

    public function tipsAndGuides()
    {
        return $this->hasMany(TipsAndGuides::class, 'category_id');
    }
}
