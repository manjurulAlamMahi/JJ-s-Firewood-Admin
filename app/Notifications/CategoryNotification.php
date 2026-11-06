<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Support\Facades\Log;

class CategoryNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $category;
    protected $data;
    protected $subject;
    protected $body;

    /**
     * Create a new notification instance.
     *
     * @param string $category  The notification category: transactional, helpful, updates
     * @param array  $data      Notification data
     */
    public function __construct(string $category, array $data)
    {
        $this->category = $category;
        $this->data = $data;
        $this->subject = $data['subject'] ?? 'Notification';
        $this->body = $data['body'] ?? '';
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        $channels = [];

        // Get user's preference for this category
        $preference = $notifiable->preferenceFor($this->category);

        $pushEnabled = $preference ? $preference->push : true;

        if ($pushEnabled) {
            $channels[] = FcmChannel::class;
        }

        return $channels; 
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject)
            ->line($this->body)
            ->action('View More', url('/'))
            ->line('Thank you for using our application!');
    }

    /**
     * Get the FCM push representation of the notification.
     */
    public function toFcm($notifiable): array
    {
        return [
            'notification' => [
                'title' => $this->data['title'] ?? $this->subject,
                'body' => $this->body,
            ],
            'data' => [
                'category' => $this->category,
                'user_id' => (string) $notifiable->id,
                ...$this->data['data'] ?? [],
            ],
            'options' => [
                'screen' => $this->data['screen'] ?? null,
                'link' => $this->data['link'] ?? null,
                'image' => $this->data['image'] ?? null,
            ]
        ];
    }

    /**
     * Get the array representation of the notification (for database storage).
     */
    public function toArray($notifiable): array
    {
        return [
            'category' => $this->category,
            'subject' => $this->subject,
            'body' => $this->body,
            'data' => $this->data['data'] ?? [],
        ];
    }
}
