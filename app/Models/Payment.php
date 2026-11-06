<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $table = 'payments';

    protected $fillable = [
        'payment_id',
        'refund_id',
        'order_id',
        'user_id',
        'amount',
        'refund_amount',
        'remaining_amount',
        'currency',
        'status',
        'refund_status',
        'refund_reason',
        'payment_method',
        'source_id',
        'card_details',
        'receipt_url',
        'error_code',
        'error_message',
        'refunded_at',
        'webhook_received',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'card_details' => 'json',
        'webhook_received' => 'boolean',
        'refunded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeRefunded($query)
    {
        return $query->where('refund_status', '!=', 'none');
    }

    public function scopeFullyRefunded($query)
    {
        return $query->where('refund_status', 'full');
    }

    public function scopePartiallyRefunded($query)
    {
        return $query->where('refund_status', 'partial');
    }

    /**
     * Helper Methods
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isRefunded(): bool
    {
        return $this->refund_status !== 'none';
    }

    public function isFullyRefunded(): bool
    {
        return $this->refund_status === 'full';
    }

    public function isPartiallyRefunded(): bool
    {
        return $this->refund_status === 'partial';
    }

    public function getRefundableAmount(): float
    {
        if ($this->isFullyRefunded()) {
            return 0;
        }

        if ($this->isPartiallyRefunded()) {
            return max(0, $this->amount - ($this->refund_amount ?? 0));
        }

        return $this->amount;
    }

    public function canBeRefunded(): bool
    {
        return $this->isCompleted() && !$this->isFullyRefunded() && $this->getRefundableAmount() > 0;
    }

    public function markAsCompleted($paymentData = []): void
    {
        $this->status = 'completed';

        if (isset($paymentData['receipt_url'])) {
            $this->receipt_url = $paymentData['receipt_url'];
        }

        if (isset($paymentData['card_details'])) {
            $this->card_details = $paymentData['card_details'];
        }

        $this->save();
    }

    public function markAsFailed($errorCode, $errorMessage): void
    {
        $this->status = 'failed';
        $this->error_code = $errorCode;
        $this->error_message = $errorMessage;
        $this->save();
    }

    public function markAsRefunded($refundId, $refundAmount, $refundStatus = 'full', $reason = null): void
    {
        $this->refund_id = $refundId;
        $this->refund_amount = $refundAmount;
        $this->refund_status = $refundStatus;
        $this->refund_reason = $reason;
        $this->refunded_at = now();
        $this->save();
    }
}
