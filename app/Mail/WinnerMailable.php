<?php

namespace App\Mail;

use App\Models\Giveaway;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WinnerMailable extends Mailable
{
    use Queueable, SerializesModels;

    public $giveaway;
    public $winner;

    public function __construct(Giveaway $giveaway, User $winner)
    {
        $this->giveaway = $giveaway;
        $this->winner = $winner;
    }

    public function build()
    {
        // Subject line mentions the prize specifically — much higher open rates
        // than a generic "you won" subject. Falls back to "a prize" if the
        // giveaway has no prize_name set (shouldn't happen in practice but
        // keeps the email from looking broken if it does).
        $prizeName = $this->giveaway->prize_name ?? 'a prize';
        $subject = "You won! Your {$prizeName} is in the app 🎉";

        return $this->subject($subject)
                    ->view('emails.giveaway-winner')      // HTML version (rich, branded)
                    ->text('emails.giveaway-winner-text') // Plain-text fallback for previews + accessibility
                    ->with([
                        'giveaway' => $this->giveaway,
                        'winner'   => $this->winner,
                    ]);
    }
}
