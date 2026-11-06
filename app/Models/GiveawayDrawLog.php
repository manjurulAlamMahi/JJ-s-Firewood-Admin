<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiveawayDrawLog extends Model
{
    protected $fillable = [
        'giveaway_id',
        'winner_id',
        'drawn_at',
        'total_entries_at_draw',
        'total_participants_at_draw',
        'draw_method',
        'was_automatic',
        'notification_sent',
        'notes'
    ];

    protected $casts = [
        'drawn_at' => 'datetime',
        'was_automatic' => 'boolean',
        'notification_sent' => 'boolean',
    ];

    /**
     * Create a draw log entry
     */
    public static function createLog(
        int $giveawayId,
        int $winnerId,
        int $totalEntries,
        int $totalParticipants,
        bool $wasAutomatic = false,
        ?string $notes = null
    ): self {
        return self::create([
            'giveaway_id' => $giveawayId,
            'winner_id' => $winnerId,
            'drawn_at' => now(),
            'total_entries_at_draw' => $totalEntries,
            'total_participants_at_draw' => $totalParticipants,
            'draw_method' => $wasAutomatic ? 'automatic' : 'manual',
            'was_automatic' => $wasAutomatic,
            'notification_sent' => false,
            'notes' => $notes,
        ]);
    }

    public function giveaway()
    {
        return $this->belongsTo(Giveaway::class);
    }

    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_id');
    }
}