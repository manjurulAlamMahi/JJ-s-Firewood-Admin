<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntryHistory extends Model
{
    protected $table = 'entry_history';

    protected $fillable = [
        'user_id',
        'giveaway_id',
        'order_id',
        'admin_user_id',
        'action',
        'quantity',
        'source',
        'reason',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * Get the user who the entries belong to
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the giveaway
     */
    public function giveaway(): BelongsTo
    {
        return $this->belongsTo(Giveaway::class);
    }

    /**
     * Get the related order
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the admin user who made the change
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}