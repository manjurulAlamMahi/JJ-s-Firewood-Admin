<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiveawayDrawReplay extends Model
{
    protected $fillable = [
        'giveaway_id',
        'draw_id',
        'animation_style',
        'winner_id',
        'animation_data',
        'video_path',
        'total_entries',
        'total_participants',
        'drawn_at',
        'drawn_by'
    ];

    protected $casts = [
        'animation_data' => 'array',
        'drawn_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function giveaway()
    {
        return $this->belongsTo(Giveaway::class);
    }

    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function drawnBy()
    {
        return $this->belongsTo(User::class, 'drawn_by');
    }

    /**
     * Check if draw is recent (within 24 hours)
     */
    public function isLive()
    {
        return $this->drawn_at->gt(now()->subHours(24));
    }

    /**
     * Get formatted draw ID
     */
    public static function generateDrawId($giveawayId)
    {
        return 'DRAW-' . date('Y') . '-' . str_pad($giveawayId, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Scope to get latest draw
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('drawn_at', 'desc');
    }
}