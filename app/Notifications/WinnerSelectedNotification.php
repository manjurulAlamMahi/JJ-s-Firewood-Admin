<?php

namespace App\Notifications;

use App\Models\Giveaway;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Notifications\Channels\FcmChannel;


class WinnerSelectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $giveaway;
    protected $winner;

    public function __construct(Giveaway $giveaway, User $winner)
    {
        $this->giveaway = $giveaway;
        $this->winner = $winner;
    }

    public function via($notifiable)
    {
        return [FcmChannel::class];
    }

    public function toFcm($notifiable): array
    {
        $giveaway = $this->giveaway;
        $isWinner = $notifiable->id === $this->winner->id;

        $winnerName = $this->winner->name;
        $latestOrder = $this->winner->orders()->latest()->first();
        if ($latestOrder && $latestOrder->address) {
            $winnerName = $latestOrder->address->full_name ?? $winnerName;
        }

        $title = $isWinner ? 'Congratulations! 🎁' : 'Giveaway Winner Announced! 🏆';
        $body = $isWinner 
            ? "Congratulations! You've won the '{$giveaway->prize_name}' giveaway! 🎁"
            : "The winner of '{$giveaway->prize_name}' is {$winnerName}! Congratulations! 🎉";

        return [
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
            'data' => [
                'type' => 'giveaway_winner',
                'giveaway_id' => (string) $giveaway->id,
            ],
            'options' => [
                'screen' => 'giveaway_details',
            ]
        ];
    }

    public function toMail($notifiable)
    {
        $giveaway = $this->giveaway;
        $winner = $this->winner;

        $winnerName = $winner->name;
        $latestOrder = $winner->orders()->latest()->first();
        if ($latestOrder && $latestOrder->address) {
            $winnerName = $latestOrder->address->full_name ?? $winnerName;
        }

        return (new MailMessage)
            ->subject("Giveaway Winner Announced: {$giveaway->prize_name}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Giveaway '{$giveaway->prize_name}' has concluded and a winner has been selected!")
            ->line("Prize: {$giveaway->prize_name} ({$giveaway->prize_value})")
            ->line("Winner Name: {$winnerName}")
            ->line("Winner Email: {$winner->email}")
            ->line("Winner Phone: " . ($winner->phone ?? '-'))
            ->action('View Giveaways', url(route('giveaways.index')))
            ->line('Thank you, Your Service Team');
    }
}
