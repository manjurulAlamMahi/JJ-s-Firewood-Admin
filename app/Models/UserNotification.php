<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserNotification extends Model
{
     protected $fillable = [
        'user_id',
        'push_notification_id',
        'is_read',
    ];
}
