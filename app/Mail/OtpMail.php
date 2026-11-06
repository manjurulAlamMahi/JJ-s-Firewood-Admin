<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class OtpMail extends Mailable
{
    public $otp;
    public $userName;

    public function __construct($otp, $userName = null)
    {
        $this->otp = $otp;
        $this->userName = $userName;
    }

    public function build()
    {
        return $this->subject("Your JJ's login code: {$this->otp}")
            ->view('emails.otp');
    }
}
