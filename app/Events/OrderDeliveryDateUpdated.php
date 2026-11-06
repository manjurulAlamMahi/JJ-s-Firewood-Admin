<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderDeliveryDateUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $order;
    public $oldDate;
    public $newDate;
    public $isFirstBooking;

    /**
     * Create a new event instance.
     *
     * @param Order $order
     * @param string|null $oldDate
     * @param string $newDate
     * @param bool $isFirstBooking  True when this is the FIRST time a delivery
     *                              date is confirmed for the order (vs a later
     *                              reschedule). Defaults false so existing
     *                              callers keep their current behaviour.
     */
    public function __construct(Order $order, $oldDate, $newDate, $isFirstBooking = false)
    {
        $this->order = $order;
        $this->oldDate = $oldDate;
        $this->newDate = $newDate;
        $this->isFirstBooking = $isFirstBooking;
    }
}
