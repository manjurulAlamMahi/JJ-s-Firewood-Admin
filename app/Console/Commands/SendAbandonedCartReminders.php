<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cart;
use App\Models\User;
use App\Notifications\CategoryNotification;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SendAbandonedCartReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cart:send-abandoned-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send push notifications to users who have abandoned their carts for more than 24 hours';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting abandoned cart reminders check...');

        $threshold = Carbon::now()->subHours(24);

        // Find abandoned carts
        $abandonedCarts = Cart::where('updated_at', '<=', $threshold)
            ->whereNull('cart_reminder_sent_at')
            ->with('user')
            ->get();

        if ($abandonedCarts->isEmpty()) {
            $this->info('No abandoned carts found needing reminders.');
            return 0;
        }

        // Group by user so we don't spam a user with multiple items
        $groupedByUser = $abandonedCarts->groupBy('user_id');
        $successCount = 0;

        foreach ($groupedByUser as $userId => $carts) {
            $user = $carts->first()->user;

            if ($user && $user->fcm_token) {
                try {
                    $itemCount = $carts->count();
                    $itemsText = $itemCount > 1 ? "$itemCount items" : "an item";

                    $user->notify(new CategoryNotification('transactional', [
                        'subject' => 'Forgot something?',
                        'body'    => "You still have $itemsText in your cart. Checkout now to complete your order!",
                        'title'   => 'Abandoned Cart Reminder',
                        'screen'  => 'cart',
                        'data'    => [
                            'type' => 'abandoned_cart',
                        ]
                    ]));

                    // Mark as sent for these carts
                    Cart::whereIn('id', $carts->pluck('id'))->update([
                        'cart_reminder_sent_at' => Carbon::now()
                    ]);

                    $successCount++;
                    $this->info("Reminder sent to user ID $userId.");
                } catch (\Exception $e) {
                    Log::error("Failed to send abandoned cart reminder to user $userId: " . $e->getMessage());
                    $this->error("Failed to send reminder to user_id: $userId.");
                }
            } else {
                // User has no FCM token or doesn't exist, just mark as sent so we don't retry endlessly
                Cart::whereIn('id', $carts->pluck('id'))->update([
                    'cart_reminder_sent_at' => Carbon::now()
                ]);
            }
        }

        $this->info("Abandoned cart check complete. Sent $successCount notifications.");
        return 0;
    }
}
