<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ManualNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $title;
    public $body;

    public function __construct($title, $body)
    {
        $this->title = $title;
        $this->body = $body;
    }

    public function build()
    {
        return $this->subject($this->title)
                    ->view('emails.manual_notification')
                    ->with(['body' => $this->body]);
    }
}
