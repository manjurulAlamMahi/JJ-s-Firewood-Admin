<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WcOrderNote extends Model
{
    protected $table = 'wc_order_notes';

    protected $fillable = [
        'wc_order_id',
        'user_id',
        'body',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(WcOrder::class, 'wc_order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
