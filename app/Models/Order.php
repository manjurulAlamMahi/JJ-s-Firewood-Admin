<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\ShippingAddress;
use App\Models\Coupon;
use App\Models\OrderItem;
use App\Models\Payment;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'shipping_address_id',
        'coupon_id',
        'order_number',
        'subtotal',
        'delivery_fee',
        'discount',
        'tax',
        'total',
        'payment_method',
        'payment_status',
        'order_status',
        'preferred_delivery_date',
        'notes',
        'giveaway_entries',
        'is_stock_deducted',
        'is_coupon_used',
        'loyalty_points',
        'charge_id',
        'transaction_id',
        'delivery_type_id',
        'cancellation_requested_at',
        'refund_processed_at',
        'refund_processed_by',
        // Needs Action dismiss + delivery-date confirmation (post-2026-05 fields)
        'delivery_date_confirmed_at',
        'needs_action_dismissed_at',
        'auto_delivered_at',
        'scheduled_via',
        'setmore_appointment_id',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'preferred_delivery_date' => 'date',
        'is_stock_deducted' => 'boolean',
        'is_coupon_used' => 'boolean',
        'cancellation_requested_at' => 'datetime',
        'refund_processed_at' => 'datetime',
        'delivery_type_id' => 'integer',
        'delivery_date_confirmed_at' => 'datetime',
        'needs_action_dismissed_at' => 'datetime',
        'auto_delivered_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {

            $lastOrder = self::orderBy('id', 'DESC')->first();

            if ($lastOrder && preg_match('/JJS(\d+)/', $lastOrder->order_number, $matches)) {
                $lastNumber = (int) $matches[1];
                $nextNumber = $lastNumber + 1;
            } else {
                $nextNumber = 1;
            }
            $order->order_number = 'JJS' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
        });
    }


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }


    public function address(): BelongsTo
    {
        return $this->belongsTo(ShippingAddress::class, 'shipping_address_id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function deliveryFollowUp()
    {
        return $this->hasOne(DeliveryFollowUp::class);
    }

    public function payment(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'id', 'order_id')
                    ->latest();
    }

    public function deliveryType(): BelongsTo
    {
        return $this->belongsTo(DeliveryType::class);
    }


    public function isPending(): bool
    {
        return $this->order_status === 'pending';
    }

    public function isConfirmed(): bool
    {
        return in_array($this->order_status, ['confirmed', 'processing', 'shipped', 'delivered']);
    }

    public function isCancelled(): bool
    {
        return $this->order_status === 'cancelled';
    }

    public function isDelivered(): bool
    {
        return $this->order_status === 'delivered';
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->order_status, ['pending', 'confirmed', 'processing', 'shipped']);
    }

    public function canBeConfirmed(): bool
    {
        return $this->order_status === 'pending';
    }


    public function getStatusBadgeAttribute(): string
    {
        $badges = [
            'pending' => '<span class="badge bg-warning">Pending</span>',
            'confirmed' => '<span class="badge bg-primary">Confirmed</span>',
            'processing' => '<span class="badge bg-info">Processing</span>',
            'shipped' => '<span class="badge bg-secondary">Shipped</span>',
            'delivered' => '<span class="badge bg-success">Delivered</span>',
            'cancelled' => '<span class="badge bg-danger">Cancelled</span>',
        ];

        return $badges[$this->order_status] ?? '<span class="badge bg-secondary">Unknown</span>';
    }


    public function scopePending($query)
    {
        return $query->where('order_status', 'pending');
    }

    public function scopeConfirmed($query)
    {
        return $query->whereIn('order_status', ['confirmed', 'processing', 'shipped', 'delivered']);
    }

    public function scopeCancelled($query)
    {
        return $query->where('order_status', 'cancelled');
    }

    public function scopeAwaitingApproval($query)
    {
        return $query->where('order_status', 'pending')
                     ->where('is_stock_deducted', false);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Payment-related helper methods
     */
    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function isPaymentPending(): bool
    {
        return $this->payment_status === 'pending';
    }

    public function isRefunded(): bool
    {
        return $this->payment_status === 'refunded';
    }

    public function canBePaid(): bool
    {
        return $this->payment_status === 'pending' && $this->order_status === 'pending';
    }

    public function getPaymentStatus(): ?string
    {
        $payment = Payment::where('order_id', $this->id)->latest()->first();
        return $payment?->status;
    }

    public function getLatestPayment(): ?Payment
    {
        return Payment::where('order_id', $this->id)->latest()->first();
    }


}
