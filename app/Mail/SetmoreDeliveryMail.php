<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Generic delivery email for non-app (phone / WooCommerce) Setmore bookings.
 * Renders booked / rescheduled / cancelled from emails/delivery/{type}.blade.php.
 *
 * Driven purely by booking fields (no Order model) — phone/WC bookings have no
 * matched order. Address arrives as one combined string from the webhook
 * (customer_address) and is passed as $addressLine.
 */
class SetmoreDeliveryMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $type;          // booked | rescheduled | cancelled
    public array $data;           // customerName, appointmentDate, etc.

    public function __construct(string $type, array $data)
    {
        $this->type = in_array($type, ['booked', 'rescheduled', 'cancelled']) ? $type : 'booked';
        $this->data = $data;
    }

    public function build()
    {
        $name = $this->data['customerName'] ?? null;
        $date = $this->data['appointmentDate'] ?? null;

        try {
            $dateLabel = $date ? Carbon::parse($date)->format('j M') : null;
        } catch (\Throwable $e) {
            $dateLabel = null;
        }

        switch ($this->type) {
            case 'rescheduled':
                $subject = $dateLabel
                    ? "Your JJ's Firewood delivery has been rescheduled to {$dateLabel}"
                    : "Your JJ's Firewood delivery has been rescheduled";
                break;
            case 'cancelled':
                $subject = "Your JJ's Firewood delivery booking has been cancelled";
                break;
            case 'booked':
            default:
                $subject = $dateLabel
                    ? "Your JJ's Firewood order — delivery booked for {$dateLabel}"
                    : "Your JJ's Firewood delivery has been booked";
                break;
        }

        return $this->subject($subject)
                    ->view("emails.delivery.{$this->type}")
                    ->with($this->data);
    }
}
