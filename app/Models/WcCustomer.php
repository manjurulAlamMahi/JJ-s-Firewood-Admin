<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WcCustomer extends Model
{
    protected $table = 'wc_customers';

    protected $fillable = [
        'woocommerce_id',
        'email',
        'first_name',
        'last_name',
        'phone',
        'billing_data',
        'shipping_data',
        'is_guest',
        'total_orders',
        'total_spent',
        'last_order_at',
    ];

    protected $casts = [
        'billing_data'  => 'array',
        'shipping_data' => 'array',
        'is_guest'      => 'boolean',
        'total_orders'  => 'integer',
        'total_spent'   => 'decimal:2',
        'last_order_at' => 'datetime',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(WcOrder::class, 'wc_customer_id');
    }

    public function fullName(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? '')) ?: 'Guest';
    }
}
