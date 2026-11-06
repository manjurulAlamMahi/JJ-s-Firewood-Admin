<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderRefundedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $order;
    public $payment;
    public $refundAmount;
    public $refundType;

    /**
     * Create a new message instance.
     *
     * @param Order $order
     * @param Payment $payment
     * @param float $refundAmount
     * @param string $refundType 'full' or 'partial'
     */
    public function __construct(Order $order, Payment $payment, $refundAmount, $refundType)
    {
        $this->order = $order;
        $this->payment = $payment;
        $this->refundAmount = $refundAmount;
        $this->refundType = $refundType;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $subject = ($this->refundType === 'full')
            ? "Full refund processed — order #{$this->order->order_number}"
            : "Refund processed: $" . number_format((float) $this->refundAmount, 2) . " — order #{$this->order->order_number}";

        return $this->subject($subject)
                    ->view('emails.orders.refunded');
    }
}
