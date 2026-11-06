<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DeliveryDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var array<int,array> orders that were auto-delivered */
    public array $orders;

    /** @var array sweep stats: delivered, skipped, errors */
    public array $stats;

    /** @var array<int,array> outstanding orders needing admin action */
    public array $outstanding;

    public string $dateLabel;

    public function __construct(array $orders, array $stats, array $outstanding = [])
    {
        $this->orders      = $orders;
        $this->stats       = $stats;
        $this->outstanding = $outstanding;
        $this->dateLabel   = Carbon::now('Australia/Perth')->format('l j F Y');
    }

    public function build()
    {
        $count = count($this->orders);
        $outstandingCount = count($this->outstanding);

        $subject = "JJ's Firewood — {$count} auto-delivered";
        if ($outstandingCount > 0) {
            $subject .= ", {$outstandingCount} need action";
        }
        $subject .= " ({$this->dateLabel})";

        return $this->subject($subject)
            ->view('emails.delivery-digest')
            ->with([
                'orders'      => $this->orders,
                'stats'       => $this->stats,
                'outstanding' => $this->outstanding,
                'dateLabel'   => $this->dateLabel,
            ]);
    }
}
