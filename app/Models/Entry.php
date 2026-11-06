<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Entry extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'user_id',
        'giveaway_id',
        'order_id',
        'entry_count',
        'purchase_type',
        'amount_paid',
        'drawn',        // ADD THIS - marks entry as consumed after giveaway ends
        'drawn_at'      // ADD THIS - timestamp when entry was consumed
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'drawn' => 'boolean',           // ADD THIS
        'drawn_at' => 'datetime'        // ADD THIS
    ];

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function giveaway()
    {
        return $this->belongsTo(Giveaway::class);
    }
    
    /**
     * Scopes
     */
    
    /**
     * Scope for active/available entries (not drawn yet)
     * Permanent entries (reward type) are always available
     */
    public function scopeAvailable($query)
    {
        return $query->where(function($q) {
            $q->where('drawn', false)
              ->orWhere('purchase_type', 'reward'); // Permanent entries always show
        });
    }
    
    /**
     * Scope for permanent entries (not tied to specific giveaway)
     */
    public function scopePermanent($query)
    {
        return $query->whereNull('giveaway_id')
            ->where('purchase_type', 'reward');
    }
    
    /**
     * Scope for temporary entries (tied to specific giveaway)
     */
    public function scopeTemporary($query)
    {
        return $query->whereNotNull('giveaway_id');
    }
    
    /**
     * Scope for consumed/drawn entries
     */
    public function scopeDrawn($query)
    {
        return $query->where('drawn', true);
    }
}
