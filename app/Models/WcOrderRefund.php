<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WcOrderRefund extends Model
{
    protected $table = 'wc_order_refunds';

    protected $fillable = [
        'wc_order_id',
        'woocommerce_refund_id',
        'amount',
        'reason',
        'refunded_by',
        'refunded_at',
        'api_response',
        'status',
    ];

    protected $casts = [
        'amount'        => 'decimal:2',
        'refunded_at'   => 'datetime',
        'api_response'  => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(WcOrder::class, 'wc_order_id');
    }

    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }
}
