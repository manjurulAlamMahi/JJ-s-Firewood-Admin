<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $fillable = ['user_id', 'category', 'email', 'push'];

    protected $casts = [
        'email' => 'boolean',
        'push' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
