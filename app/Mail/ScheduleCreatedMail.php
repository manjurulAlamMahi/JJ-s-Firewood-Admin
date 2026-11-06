<?php

namespace App\Mail;

use App\Models\Schedule;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ScheduleCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $schedule;

    public function __construct(Schedule $schedule)
    {
        $this->schedule = $schedule;
    }

    public function build()
    {
        // Format the preferred date for the subject
        try {
            $formattedDate = \Carbon\Carbon::parse($this->schedule->preferred_date)->format('j F');
            $orderNum = $this->schedule->order?->order_number ?? '';
            $subject = $orderNum
                ? "Delivery confirmed for {$formattedDate} — order #{$orderNum}"
                : "Delivery confirmed for {$formattedDate}";
        } catch (\Throwable $e) {
            $orderNum = $this->schedule->order?->order_number ?? '';
            $subject = $orderNum
                ? "Delivery scheduled — order #{$orderNum}"
                : 'Delivery scheduled';
        }

        return $this->subject($subject)
            ->view('emails.schedule.created');
    }
}
