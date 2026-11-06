<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationQueue extends Model
{
    protected $table = 'notifications_queue';

    protected $fillable = [
        'channel',
        'title',
        'message',
        'audience',
        'schedule_type',
        'scheduled_at',
        'next_run_at',
        'rrule',
        'event_key',
        'status',
        'test_mode',
        'sent_count',
        'failed_count',
        'last_error',
        'last_ran_at',
        'sent_at',
        'total_targets',
        'processed_targets',
        'paused_at',
        'created_by',
        'template_id',
        'recipient_list',
        'brevo_campaign_id',
        // Centre rebuild additive fields
        'audience_spec',
        'template_ref_id',
        'estimated_cost',
        'sms_segments',
        'respects_marketing_optout',
    ];

    protected $casts = [
        'test_mode' => 'boolean',
        'scheduled_at' => 'datetime',
        'next_run_at' => 'datetime',
        'last_ran_at' => 'datetime',
        'sent_at' => 'datetime',
        'paused_at' => 'datetime',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
        'total_targets' => 'integer',
        'processed_targets' => 'integer',
        'template_id' => 'integer',
        'audience_spec' => 'array',
        'respects_marketing_optout' => 'boolean',
        'estimated_cost' => 'decimal:2',
        'sms_segments' => 'integer',
    ];

    public function runs()
    {
        return $this->hasMany(NotificationRun::class, 'notification_id');
    }

    /**
     * Get recipient list as array
     */
    public function getRecipientListAttribute($value)
    {
        return $value ? json_decode($value, true) : null;
    }

    /**
     * Set recipient list from array
     */
    public function setRecipientListAttribute($value)
    {
        $this->attributes['recipient_list'] = $value ? json_encode($value) : null;
    }
}