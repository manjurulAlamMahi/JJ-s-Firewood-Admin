<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SetmoreWebhookEvent extends Model
{
    protected $table = 'setmore_webhook_events';

    protected $fillable = [
        'customer_email',
        'customer_phone',
        'customer_name',
        'appointment_at',
        'appointment_id',
        'raw_payload',
        'matched_order_id',
        'candidate_order_ids',
        'candidate_count',
        'status',
        'applied_order_id',
        'reviewed_by',
        'reviewed_at',
        'notes',
    ];

    protected $casts = [
        'appointment_at'      => 'datetime',
        'raw_payload'         => 'array',
        'candidate_order_ids' => 'array',
        'candidate_count'     => 'integer',
        'reviewed_at'         => 'datetime',
    ];

    public function matchedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'matched_order_id');
    }

    public function appliedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'applied_order_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }
}
