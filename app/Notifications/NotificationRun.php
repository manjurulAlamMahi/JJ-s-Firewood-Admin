<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationRun extends Model
{
    protected $table = 'notification_runs';

    protected $fillable = [
        'notification_id',
        'channel',
        'status',      // sent | failed
        'provider',    // firebase | mail | etc
        'user_id',
        'recipient',   // email for email channel, nullable
        'error',       // nullable text
        'meta',        // nullable json
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function notification()
    {
        return $this->belongsTo(NotificationQueue::class, 'notification_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
