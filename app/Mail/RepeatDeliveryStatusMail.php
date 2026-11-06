<?php

namespace App\Mail;

use App\Models\Schedule;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RepeatDeliveryStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Schedule $schedule,
        public string $heading,
        public string $body
    ) {
    }

    public function build()
    {
        return $this->subject($this->heading)
            ->view('emails.schedule.repeat-status');
    }
}
