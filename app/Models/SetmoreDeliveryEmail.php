<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per server-sent delivery email (phone / WooCommerce Setmore bookings).
 * See migration 2026_05_22_000001_create_setmore_delivery_emails_table.
 */
class SetmoreDeliveryEmail extends Model
{
    protected $table = 'setmore_delivery_emails';

    protected $fillable = [
        'setmore_webhook_event_id',
        'appointment_id',
        'type',
        'channel',
        'recipient',
        'real_recipient',
        'customer_name',
        'appointment_date',
        'status',
        'mailer',
        'error',
        'was_test',
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'was_test'         => 'boolean',
    ];

    /**
     * Has a 'booked' email already been logged (successfully) for this
     * appointment? Used to decide booked-vs-rescheduled for non-app bookings.
     */
    public static function hasBookedFor(string $appointmentId): bool
    {
        if ($appointmentId === '') return false;
        return static::where('appointment_id', $appointmentId)
            ->where('type', 'booked')
            ->whereIn('status', ['sent', 'test'])
            ->exists();
    }
}
