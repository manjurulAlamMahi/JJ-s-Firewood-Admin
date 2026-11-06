<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderDeliveryDateUpdatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $order;
    public $oldDate;
    public $newDate;
    public $isFirstBooking;

    /**
     * Create a new message instance.
     *
     * @param Order $order
     * @param string|null $oldDate
     * @param string $newDate
     * @param bool $isFirstBooking  First confirmation vs later reschedule.
     */
    public function __construct(Order $order, $oldDate, $newDate, $isFirstBooking = false)
    {
        $this->order = $order;
        $this->oldDate = $oldDate;
        $this->newDate = $newDate;
        $this->isFirstBooking = $isFirstBooking;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        // Subject differs for a first booking vs a reschedule.
        try {
            $formattedDate = \Carbon\Carbon::parse($this->newDate)->format('j F');
            $subject = $this->isFirstBooking
                ? "Delivery scheduled: {$formattedDate} — order #{$this->order->order_number}"
                : "Delivery rescheduled: {$formattedDate} — order #{$this->order->order_number}";
        } catch (\Throwable $e) {
            $subject = $this->isFirstBooking
                ? "Delivery scheduled — order #{$this->order->order_number}"
                : "Delivery rescheduled — order #{$this->order->order_number}";
        }

        return $this->subject($subject)
                    ->view('emails.orders.delivery-date-updated')
                    ->with(['isFirstBooking' => $this->isFirstBooking]);
    }
}
