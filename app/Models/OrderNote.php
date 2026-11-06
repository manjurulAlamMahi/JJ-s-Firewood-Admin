<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderNote extends Model
{
    protected $table = 'order_notes';

    protected $fillable = [
        'order_id',
        'user_id',
        'body',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'user_id'  => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
