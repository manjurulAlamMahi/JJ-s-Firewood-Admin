<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Calculator extends Model
{
    protected $fillable = [
        'pieces_per_night',
        'nights_per_week',
        'months_in_season',
        'total_pieces',
        'firewood_bags_needed',
        'estimated_weight_tonnes',
        'trailer_loads',
        'recommended_loads',
        'prices',
    ];

    protected $casts = [
        'recommended_loads' => 'array',
        'prices' => 'array',
        'estimated_weight_tonnes' => 'decimal:2',
    ];
}
