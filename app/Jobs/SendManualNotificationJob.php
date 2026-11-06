<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendManualNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $user;
    public $title;
    public $body;

    public function __construct($user, $title, $body)
    {
        $this->user = $user;
        $this->title = $title;
        $this->body = $body;
    }

    public function handle()
    {
        // Implement your notification sending logic here
        // For example, you can use Firebase, Pusher, or any other service
        // For now, this is a placeholder
        \Log::info('SendManualNotificationJob: Notification sent', [
            'user_id' => $this->user->id,
            'title' => $this->title,
            'body' => $this->body,
        ]);
    }
}

