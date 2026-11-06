<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Category;
use App\Models\SubCategory;
use App\Models\Product;
use App\Models\Cart;
use App\Models\NotificationPreference;
use App\Notifications\CategoryNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;

class AbandonedCartReminderTest extends TestCase
{
    use RefreshDatabase;

    protected $userWithToken;
    protected $userWithoutToken;
    protected $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup basic categories and product
        $category = Category::create(['name' => 'Firewoods']);
        $subcategory = SubCategory::create([
            'category_id' => $category->id,
            'name' => 'Kindling'
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'subcategory_id' => $subcategory->id,
            'name' => 'Premium Wood',
            'price' => 150.00,
            'quantity' => 20,
            'status' => 1
        ]);

        // Setup users
        $this->userWithToken = User::factory()->create([
            'fcm_token' => 'fcm_token_123'
        ]);

        $this->userWithoutToken = User::factory()->create([
            'fcm_token' => null
        ]);
    }

    public function test_sends_notification_for_abandoned_cart_older_than_24_hours()
    {
        Notification::fake();

        // Create an abandoned cart (updated 25 hours ago)
        $cart = Cart::create([
            'user_id' => $this->userWithToken->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => $this->product->numeric_price,
            'subtotal' => $this->product->numeric_price,
        ]);
        
        // Eloquent updates updated_at automatically, so we force updated_at in the DB
        $cart->updated_at = Carbon::now()->subHours(25);
        $cart->save();

        // Run the command
        Artisan::call('cart:send-abandoned-reminders');

        // Assert notification was sent
        Notification::assertSentTo(
            $this->userWithToken,
            CategoryNotification::class,
            function ($notification) {
                $fcmPayload = $notification->toFcm($this->userWithToken);
                return $fcmPayload['notification']['title'] === 'Abandoned Cart Reminder' &&
                       str_contains($fcmPayload['notification']['body'], 'an item');
            }
        );

        // Assert the database row was updated with cart_reminder_sent_at
        $this->assertNotNull($cart->fresh()->cart_reminder_sent_at);
    }

    public function test_does_not_send_notification_for_cart_items_modified_recently()
    {
        Notification::fake();

        // Create a cart item modified 12 hours ago
        $cart = Cart::create([
            'user_id' => $this->userWithToken->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => $this->product->numeric_price,
            'subtotal' => $this->product->numeric_price,
        ]);
        $cart->updated_at = Carbon::now()->subHours(12);
        $cart->save();

        // Run command
        Artisan::call('cart:send-abandoned-reminders');

        // Assert no notification was sent
        Notification::assertNothingSent();

        // Assert cart_reminder_sent_at is still null
        $this->assertNull($cart->fresh()->cart_reminder_sent_at);
    }

    public function test_marks_reminder_sent_for_users_without_fcm_token()
    {
        Notification::fake();

        // Create abandoned cart for user without token (updated 25 hours ago)
        $cart = Cart::create([
            'user_id' => $this->userWithoutToken->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => $this->product->numeric_price,
            'subtotal' => $this->product->numeric_price,
        ]);
        $cart->updated_at = Carbon::now()->subHours(25);
        $cart->save();

        // Run command
        Artisan::call('cart:send-abandoned-reminders');

        // Assert no notification sent (since user has no token)
        Notification::assertNothingSent();

        // Assert cart_reminder_sent_at is marked anyway to prevent retries
        $this->assertNotNull($cart->fresh()->cart_reminder_sent_at);
    }

    public function test_respects_user_push_notification_preferences()
    {
        Notification::fake();

        // Turn off transactional push notifications for the user
        NotificationPreference::create([
            'user_id' => $this->userWithToken->id,
            'category' => 'transactional',
            'email' => true,
            'push' => false
        ]);

        // Create an abandoned cart (updated 25 hours ago)
        $cart = Cart::create([
            'user_id' => $this->userWithToken->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => $this->product->numeric_price,
            'subtotal' => $this->product->numeric_price,
        ]);
        $cart->updated_at = Carbon::now()->subHours(25);
        $cart->save();

        // Run command
        Artisan::call('cart:send-abandoned-reminders');

        // Assert no notification sent because push channel is disabled for transactional category
        Notification::assertNothingSent();

        // Assert cart is marked as reminder sent
        $this->assertNotNull($cart->fresh()->cart_reminder_sent_at);
    }

    public function test_handles_plural_notification_messaging()
    {
        Notification::fake();

        // Create two different abandoned cart items (updated 25 hours ago)
        $product2 = Product::create([
            'category_id' => $this->product->category_id,
            'subcategory_id' => $this->product->subcategory_id,
            'name' => 'Premium Coal',
            'price' => 200.00,
            'quantity' => 10,
            'status' => 1
        ]);

        $cart1 = Cart::create([
            'user_id' => $this->userWithToken->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => $this->product->numeric_price,
            'subtotal' => $this->product->numeric_price,
        ]);
        $cart1->updated_at = Carbon::now()->subHours(25);
        $cart1->save();

        $cart2 = Cart::create([
            'user_id' => $this->userWithToken->id,
            'product_id' => $product2->id,
            'quantity' => 1,
            'price' => $product2->numeric_price,
            'subtotal' => $product2->numeric_price,
        ]);
        $cart2->updated_at = Carbon::now()->subHours(25);
        $cart2->save();

        // Run command
        Artisan::call('cart:send-abandoned-reminders');

        // Assert notification sent with plural text "2 items"
        Notification::assertSentTo(
            $this->userWithToken,
            CategoryNotification::class,
            function ($notification) {
                $fcmPayload = $notification->toFcm($this->userWithToken);
                return $fcmPayload['notification']['title'] === 'Abandoned Cart Reminder' &&
                       str_contains($fcmPayload['notification']['body'], '2 items');
            }
        );

        $this->assertNotNull($cart1->fresh()->cart_reminder_sent_at);
        $this->assertNotNull($cart2->fresh()->cart_reminder_sent_at);
    }
}
