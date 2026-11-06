<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationRun extends Model
{
    protected $table = 'notification_runs';

    protected $fillable = [
        'notification_id',
        'user_id',
        'channel',
        'status',
        'provider',
        'recipient',
        'error',
    ];
}
