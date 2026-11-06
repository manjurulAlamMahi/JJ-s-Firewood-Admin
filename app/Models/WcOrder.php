<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WcOrder extends Model
{
    protected $table = 'wc_orders';

    protected $fillable = [
        'woocommerce_id',
        'woocommerce_number',
        'wc_customer_id',
        'wc_status',
        'payment_status',
        'currency',
        'subtotal',
        'shipping_total',
        'tax_total',
        'discount_total',
        'total',
        'refund_total',
        'payment_method',
        'payment_method_title',
        'transaction_id',
        'customer_email',
        'customer_phone',
        'customer_first_name',
        'customer_last_name',
        'billing_address',
        'shipping_address',
        'suburb',
        'state',
        'postcode',
        'customer_note',
        'preferred_delivery_date',
        'wc_date_created',
        'wc_date_modified',
        'is_dismissed_from_refund_queue',
        'last_synced_at',
    ];

    protected $casts = [
        'billing_address'                => 'array',
        'shipping_address'               => 'array',
        'preferred_delivery_date'        => 'date',
        'wc_date_created'                => 'datetime',
        'wc_date_modified'                => 'datetime',
        'last_synced_at'                  => 'datetime',
        'is_dismissed_from_refund_queue'  => 'boolean',
        'subtotal'                        => 'decimal:2',
        'shipping_total'                  => 'decimal:2',
        'tax_total'                       => 'decimal:2',
        'discount_total'                  => 'decimal:2',
        'total'                           => 'decimal:2',
        'refund_total'                    => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(WcCustomer::class, 'wc_customer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WcOrderItem::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(WcOrderNote::class)->orderByDesc('created_at');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(WcOrderAuditLog::class)->orderByDesc('created_at');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(WcOrderRefund::class)->orderByDesc('created_at');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(OrderTag::class, 'wc_order_tag', 'wc_order_id', 'tag_id')
            ->withTimestamps();
    }

    public function customerFullName(): string
    {
        return trim(($this->customer_first_name ?? '') . ' ' . ($this->customer_last_name ?? ''));
    }

    /**
     * Total already refunded (sum across wc_order_refunds + the refund_total snapshot from WC).
     */
    public function totalRefunded(): float
    {
        return (float) max(
            $this->refund_total ?? 0,
            $this->refunds()->sum('amount')
        );
    }

    public function refundableAmount(): float
    {
        return (float) max(0, $this->total - $this->totalRefunded());
    }
}
