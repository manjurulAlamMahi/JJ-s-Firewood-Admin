<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    protected $table = 'notification_templates';

    protected $fillable = [
        'name', 'channel', 'title', 'body', 'payload', 'brevo_template_id', 'created_by',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
