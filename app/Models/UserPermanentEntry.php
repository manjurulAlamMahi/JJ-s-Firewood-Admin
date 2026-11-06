<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPermanentEntry extends Model
{
    protected $fillable = [
        'user_id',
        'permanent_entries',
        'total_earned',
        'total_used',
        'last_updated_at'
    ];

    protected $casts = [
        'last_updated_at' => 'datetime'
    ];

    /**
     * Get the user that owns the permanent entries
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Set permanent entries to a specific amount
     */
    public function setEntries(int $amount): void
    {
        $this->permanent_entries = $amount;
        $this->last_updated_at = now();
        $this->save();
    }

    /**
     * Add entries
     */
    public function addEntries(int $amount, string $reason = 'Admin adjustment'): void
    {
        $this->permanent_entries += $amount;
        $this->total_earned += $amount;
        $this->last_updated_at = now();
        $this->save();
        
        \Log::info("Added {$amount} permanent entries to user {$this->user_id}. Reason: {$reason}");
    }

    /**
     * Deduct entries
     */
    public function deductEntries(int $amount, string $reason = 'Admin adjustment'): void
    {
        if ($this->permanent_entries < $amount) {
            throw new \Exception('Insufficient permanent entries');
        }
        
        $this->permanent_entries -= $amount;
        $this->total_used += $amount;
        $this->last_updated_at = now();
        $this->save();
        
        \Log::info("Deducted {$amount} permanent entries from user {$this->user_id}. Reason: {$reason}");
    }
}
