<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'type',
        'value',
        'min_order_amount',
        'max_discount',
        'usage_limit',
        'used_count',
        'start_date',
        'end_date',
        'is_active'
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function isValid($userId = null)
    {
        if (!$this->is_active)
            return false;

        if ($this->start_date && Carbon::now()->lt($this->start_date)) {
            return false;
        }

        if ($this->end_date && Carbon::now()->gt($this->end_date)) {
            return false;
        }

        if ($this->usage_limit && $this->used_count >= $this->usage_limit) {
            return false;
        }

        if ($userId) {
            $alreadyUsed = \App\Models\CouponUsage::where('user_id', $userId)
                ->where('coupon_id', $this->id)
                ->exists();
            if ($alreadyUsed) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get detailed validation error message for why coupon is invalid
     * Returns null if coupon is valid, or error message string if invalid
     */
    public function getValidationError($userId = null)
    {
        if (!$this->is_active) {
            return 'This coupon is no longer active.';
        }

        if ($this->start_date && Carbon::now()->lt($this->start_date)) {
            return 'This coupon is not yet available. It starts on ' . $this->start_date->format('M d, Y') . '.';
        }

        if ($this->end_date && Carbon::now()->gt($this->end_date)) {
            return 'This coupon has expired on ' . $this->end_date->format('M d, Y') . '.';
        }

        if ($this->usage_limit && $this->used_count >= $this->usage_limit) {
            return 'This coupon has reached its usage limit and is no longer available.';
        }

        if ($userId) {
            $alreadyUsed = \App\Models\CouponUsage::where('user_id', $userId)
                ->where('coupon_id', $this->id)
                ->exists();
            if ($alreadyUsed) {
                return 'You have already used this coupon.';
            }
        }

        return null; // Coupon is valid
    }

    public function calculateDiscount($subtotal, $userId = null)
    {
        // Validate coupon first
        if (!$this->isValid($userId)) {
            return 0;
        }

        // Check minimum order amount
        if ($this->min_order_amount && $subtotal < $this->min_order_amount) {
            return 0;
        }

        $discount = 0;

        if ($this->type === 'percentage') {
            $discount = ($subtotal * $this->value) / 100;

            if ($this->max_discount && $discount > $this->max_discount) {
                $discount = $this->max_discount;
            }
        } else {
            $discount = $this->value;
        }

        if ($discount > $subtotal) {
            $discount = $subtotal;
        }

        return round($discount, 2);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', Carbon::now());
            })
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', Carbon::now());
            })
            ->where(function ($q) {
                $q->whereNull('usage_limit')->orWhereRaw('used_count < usage_limit');
            });
    }

    public function incrementUsage()
    {
        $this->increment('used_count');
    }

    public function usages()
    {
        return $this->hasMany(CouponUsage::class);
    }
}
