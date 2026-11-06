<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Singleton storing toggles for which notifications get fired.
 *
 * Used by the order event listeners (SendOrderNotification,
 * SendOrderDeliveredNotification, etc.) to gate email and push sends.
 *
 * Defaults: every notification enabled, BCC to admin@Jjsfirewoodperth.com.au.
 */
class NotificationSetting extends Model
{
    protected $table = 'notification_settings';

    protected $fillable = ['settings'];

    protected $casts = [
        'settings' => 'array',
    ];

    /**
     * Default toggle state. New keys added here automatically apply to all
     * listeners on next deploy without needing a database migration.
     */
    public const DEFAULTS = [
        // Email toggles (sent to the customer)
        'email_order_placed'                => true,
        'email_order_delivered'             => true,
        'email_order_cancelled'             => true,
        'email_order_delivery_date_updated' => true,
        'email_payment_refunded'            => true,

        // Admin BCC: when an email is sent to the customer, also BCC the admin
        'admin_bcc_enabled'                 => true,
        'admin_bcc_address'                 => 'admin@Jjsfirewoodperth.com.au',

        // Push toggles (in-app push notifications via Firebase / etc.)
        'push_order_placed'                 => true,
        'push_order_delivered'              => true,
        'push_order_cancelled'              => true,
        'push_order_delivery_date_updated'  => true,
        'push_payment_refunded'             => true,

        // Post-delivery follow-up (review / feedback / reorder prompt) — fires
        // once, immediately, when an order is marked delivered.
        'push_delivery_followup'            => true,

        // ── Setmore delivery emails (phone / WooCommerce bookings) ──────────
        // Server-sent emails replacing the Zapier email steps. These only fire
        // for bookings that DON'T match an app order (app orders keep their own
        // emails). MASTER defaults OFF so deploying changes nothing until the
        // admin deliberately enables it.
        'setmore_email_master'              => false,   // master on/off — OFF by default
        'setmore_email_booked'              => true,    // gated by master
        'setmore_email_rescheduled'         => true,    // gated by master
        'setmore_email_cancelled'           => true,    // gated by master

        // BCC a copy of every delivery email to the admin address below — lets
        // the admin watch real customer emails go out (a live working-check)
        // without redirecting them away from the customer.
        'setmore_email_bcc_admin'           => false,
        'setmore_email_test_recipient'      => 'admin@jjsfirewoodperth.com.au', // BCC + "send test" address

        // Mailer for ALL scheduling-related emails (these new ones AND the
        // app-order delivery-date emails that flow through the matcher).
        // 'brevo' or 'gmail'. See config/mail.php for the mailer definitions.
        'setmore_email_mailer'              => 'brevo',
    ];

    /**
     * Get (or lazily create) the singleton row.
     */
    public static function singleton(): self
    {
        $row = static::first();
        if (!$row) {
            $row = static::create(['settings' => self::DEFAULTS]);
        }
        return $row;
    }

    /**
     * Check whether a given notification key is enabled.
     * Defaults to true on missing keys so existing behaviour is preserved
     * if the singleton hasn't been configured yet.
     */
    public static function isEnabled(string $key, bool $default = true): bool
    {
        $settings = static::singleton()->settings ?? [];
        return isset($settings[$key])
            ? (bool) $settings[$key]
            : ($default && (self::DEFAULTS[$key] ?? $default));
    }

    /**
     * Get a string/non-bool setting (e.g. admin_bcc_address).
     */
    public static function get(string $key, $default = null)
    {
        $settings = static::singleton()->settings ?? [];
        return $settings[$key] ?? self::DEFAULTS[$key] ?? $default;
    }

    /**
     * Update the settings array (merging with existing).
     */
    public static function setMany(array $values): self
    {
        $row = static::singleton();
        $row->settings = array_merge($row->settings ?? [], $values);
        $row->save();
        return $row;
    }
}
