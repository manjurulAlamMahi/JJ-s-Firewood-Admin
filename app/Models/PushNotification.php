<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushNotification extends Model
{
    protected $fillable = ['title', 'message', 'status', 'is_send', 'category', 'use_preferences', 'product_id'];

    protected $casts = [
        'use_preferences' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
