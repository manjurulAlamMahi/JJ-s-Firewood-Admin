<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PointHistory extends Model
{
    use HasFactory;

    protected $table = 'point_histories';

    protected $fillable = [
        'user_id',
        'type',
        'points',
        'description',
        'order_id',
        'point_usage_for',
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
