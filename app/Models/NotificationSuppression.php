<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationSuppression extends Model
{
    protected $table = 'notification_suppressions';

    protected $fillable = [
        'channel', 'value', 'reason', 'notes', 'user_id', 'added_by', 'suppressed_at',
    ];

    protected $casts = [
        'suppressed_at' => 'datetime',
    ];

    /** Quick check: is this email/phone suppressed for this channel? */
    public static function isSuppressed(string $channel, string $value): bool
    {
        return static::where('channel', $channel)
            ->where('value', $value)
            ->exists();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
