<?php
 
namespace App\Listeners;
 
use App\Events\OrderDelivered;
use App\Mail\OrderDeliveredMail;
use App\Models\NotificationSetting;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Services\NotificationService;
use App\Notifications\CategoryNotification;
 
class SendOrderDeliveredNotification
{
    public function __construct()
    {
        //
    }
 
    public function handle(OrderDelivered $event)
    {
        $order = $event->order;

        try {
            // 1. Database Notification to User (always on)
            NotificationService::success(
                $order->user,
                'Order Delivered',
                "Your order #{$order->order_number} has been delivered. Enjoy!"
            );

            // 2. Database Notification to Admins (always on)
            $admins = User::where('is_admin', 1)->get();
            foreach ($admins as $admin) {
                NotificationService::success(
                    $admin,
                    'Order Delivered',
                    "Order #{$order->order_number} has been marked as delivered."
                );
            }

            $adminNotifications = $admins->reject(fn($a) => $a->id === $order->user_id || $a->email === $order->user?->email);

            // 3. Email Notification — gated
            if (NotificationSetting::isEnabled('email_order_delivered')) {
                $mail = Mail::to($order->user->email);
                if (NotificationSetting::isEnabled('admin_bcc_enabled')) {
                    $bcc = NotificationSetting::get('admin_bcc_address');
                    if ($bcc) $mail->bcc($bcc);
                }
                $mail->send(new OrderDeliveredMail($order));
            }
            
            // 4. Push to User — gated
            if (NotificationSetting::isEnabled('push_order_delivered')) {
                $order->user->notify(new CategoryNotification('transactional', [
                    'subject' => 'Order Delivered',
                    'body' => "Your order #{$order->order_number} has been delivered. Enjoy!",
                    'title' => 'Order Delivered',
                    'screen' => 'order_details',
                    'data' => [
                        'order_id' => (string) $order->id,
                        'order_number' => $order->order_number,
                        'type' => 'order_delivered'
                    ]
                ]));

                // 5. Push to Admins (same toggle)
                foreach ($adminNotifications as $admin) {
                    $admin->notify(new CategoryNotification('transactional', [
                        'subject' => 'Order Delivered',
                        'body' => "Order #{$order->order_number} has been delivered.",
                        'title' => 'Order Delivered',
                        'screen' => 'order_details',
                        'data' => [
                            'order_id' => (string) $order->id,
                            'order_number' => $order->order_number,
                            'type' => 'order_delivered'
                        ]
                    ]));
                }
            }
            
            // 6. Generate referral code on first delivered + paid order (always on — business logic)
            $user = $order->user;
            if ($user && !$user->referral_code && $order->payment_status === 'paid') {
                $user->referral_code = $user->generateReferralCode();
                $user->save();
                \Log::info('SendOrderDeliveredNotification: Referral code generated for user', [
                    'user_id' => $user->id,
                    'referral_code' => $user->referral_code,
                    'order_id' => $order->id
                ]);
            }
        } catch (\Exception $e) {
            \Log::error("SendOrderDeliveredNotification Error for Order #{$order->order_number}: " . $e->getMessage());
        }
    }
}
