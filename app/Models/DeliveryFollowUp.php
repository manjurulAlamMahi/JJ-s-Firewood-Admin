<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class DeliveryFollowUp extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'status',
        'channels_sent',
        'action_taken',
        'feedback_rating',
        'feedback_comment',
        'sent_at',
        'opened_at',
        'completed_at',
        'dismissed_at',
        'error',
    ];

    protected $casts = [
        'channels_sent' => 'array',
        'feedback_rating' => 'integer',
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'completed_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['completed', 'dismissed'], true);
    }

    public function markSent(array $channels): void
    {
        $this->update([
            'status' => 'sent',
            'channels_sent' => $channels,
            'sent_at' => Carbon::now(),
        ]);
    }

    public function markOpened(): void
    {
        if ($this->isTerminal()) {
            return;
        }

        $this->update([
            'status' => 'opened',
            'opened_at' => $this->opened_at ?? Carbon::now(),
        ]);
    }

    public function markCompleted(string $action, array $extra = []): void
    {
        $this->update(array_merge($extra, [
            'status' => 'completed',
            'action_taken' => $action,
            'completed_at' => Carbon::now(),
        ]));
    }

    public function markDismissed(): void
    {
        $this->update([
            'status' => 'dismissed',
            'dismissed_at' => Carbon::now(),
        ]);
    }
}
