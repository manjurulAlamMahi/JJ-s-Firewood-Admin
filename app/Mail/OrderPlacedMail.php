<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;

class OrderPlacedMail extends Mailable
{
    public $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function build()
    {
        return $this->subject("Order confirmed: #{$this->order->order_number} — thanks for your order 🔥")
            ->view('emails.orders.placed');
    }
}
