<?php

namespace Tests\Feature;

use App\Jobs\SendAdminPushNotificationJob;
use App\Models\NotificationPreference;
use App\Models\Product;
use App\Models\PushNotification;
use App\Models\User;
use App\Notifications\CategoryNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminPushNotificationProductLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_linked_push_includes_product_details_screen_and_id(): void
    {
        Notification::fake();

        $product = Product::create([
            'category_id' => 1, 'subcategory_id' => 1, 'name' => 'Jarrah Bag',
            'price' => '20.00', 'quantity' => 10, 'status' => 1,
        ]);

        $user = User::factory()->create(['is_guest' => false]);
        NotificationPreference::create([
            'user_id' => $user->id, 'category' => 'updates', 'email' => true, 'push' => true,
        ]);

        $pushNotification = PushNotification::create([
            'title' => 'New: Jarrah Bag',
            'message' => 'Check out our new product.',
            'category' => 'updates',
            'product_id' => $product->id,
            'use_preferences' => true,
            'status' => 1,
            'is_send' => 0,
        ]);

        (new SendAdminPushNotificationJob($pushNotification))->handle();

        Notification::assertSentTo($user, CategoryNotification::class, function ($notification) use ($product) {
            $fcm = $notification->toFcm(new User());
            return $fcm['options']['screen'] === 'product_details'
                && $fcm['data']['product_id'] == $product->id;
        });
    }

    public function test_push_without_a_product_sends_no_screen(): void
    {
        Notification::fake();

        $user = User::factory()->create(['is_guest' => false]);
        NotificationPreference::create([
            'user_id' => $user->id, 'category' => 'updates', 'email' => true, 'push' => true,
        ]);

        $pushNotification = PushNotification::create([
            'title' => 'General update',
            'message' => 'Nothing product-specific here.',
            'category' => 'updates',
            'use_preferences' => true,
            'status' => 1,
            'is_send' => 0,
        ]);

        (new SendAdminPushNotificationJob($pushNotification))->handle();

        Notification::assertSentTo($user, CategoryNotification::class, function ($notification) {
            $fcm = $notification->toFcm(new User());
            return $fcm['options']['screen'] === null;
        });
    }
}
