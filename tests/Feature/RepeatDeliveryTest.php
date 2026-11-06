<?php

namespace Tests\Feature;

use App\Mail\RepeatDeliveryStatusMail;
use App\Mail\ScheduleCreatedMail;
use App\Models\Cart;
use App\Models\Category;
use App\Models\DeliveryType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Schedule;
use App\Models\ShippingAddress;
use App\Models\SubCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RepeatDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ShippingAddress $address;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-02 09:00:00');
        Mail::fake();
        Notification::fake();
        Event::fake([\App\Events\OrderPlaced::class, \App\Events\OrderDeliveryDateUpdated::class]);

        $this->user = User::factory()->create();
        $this->address = ShippingAddress::create([
            'user_id' => $this->user->id,
            'full_name' => 'Test Customer',
            'phone' => '0400000000',
            'email' => 'customer@example.com',
            'address' => '123 Example Rd',
            'suburb' => 'Baldivis',
            'city' => 'Perth',
            'state' => 'WA',
            'country' => 'Australia',
        ]);

        DeliveryType::create(['id' => 1, 'name' => 'Standard', 'fee' => 0, 'days' => '3-5 days']);

        $category = Category::create(['name' => 'Firewood', 'status' => 1]);
        $subcategory = SubCategory::create(['category_id' => $category->id, 'name' => 'Jarrah Bush Mix', 'status' => 1]);
        $this->product = Product::create([
            'category_id' => $category->id,
            'subcategory_id' => $subcategory->id,
            'name' => '2/4 Truck - (8 scoops / 2T)',
            'price' => '780',
            'quantity' => 50,
            'status' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function fillCart(?Product $product = null): void
    {
        Cart::create([
            'user_id' => $this->user->id,
            'product_id' => ($product ?? $this->product)->id,
            'quantity' => 1,
            'price' => 780,
            'subtotal' => 780,
        ]);
    }

    private function checkoutPayload(array $repeat = []): array
    {
        return [
            'shipping_address_id' => $this->address->id,
            'payment_method' => 'cash_on_delivery',
            'delivery_option' => 'repeat',
            'repeat_details' => array_merge([
                'frequency' => 'every_2_weeks',
                'custom_days' => null,
                'first_delivery_date' => '2026-11-06',
                'preferred_time' => 'Anytime (6:00am - 5:00pm)',
            ], $repeat),
        ];
    }

    private function makeSchedule(array $attributes = []): Schedule
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'shipping_address_id' => $this->address->id,
            'subtotal' => 780,
            'delivery_fee' => 0,
            'discount' => 0,
            'tax' => 0,
            'total' => 780,
            'payment_method' => 'square',
            'payment_status' => 'paid',
            'order_status' => 'processing',
            'preferred_delivery_date' => '2026-11-06',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'quantity' => 1,
            'price' => 780,
            'subtotal' => 780,
        ]);

        return Schedule::create(array_merge([
            'user_id' => $this->user->id,
            'order_id' => $order->id,
            'repeat' => true,
            'status' => 'active',
            'frequency' => 'every_2_weeks',
            'preferred_date' => '2026-11-06',
            'next_delivery_date' => '2026-11-06',
            'shipping_address_id' => $this->address->id,
            'product_id' => $this->product->id,
            'load_size' => 2,
            'unit_price' => 780,
        ], $attributes));
    }

    public function test_repeat_checkout_creates_order_and_linked_schedule(): void
    {
        $this->fillCart();

        $this->actingAs($this->user, 'api')
            ->postJson('/api/orders/store', $this->checkoutPayload())
            ->assertOk()
            ->assertJsonPath('success', true);

        $order = Order::where('user_id', $this->user->id)->firstOrFail();
        $this->assertSame('2026-11-06', $order->preferred_delivery_date->toDateString());

        $schedule = Schedule::where('order_id', $order->id)->firstOrFail();
        $this->assertTrue((bool) $schedule->repeat);
        $this->assertSame('active', $schedule->status);
        $this->assertSame('every_2_weeks', $schedule->frequency);
        $this->assertSame('2026-11-06', $schedule->next_delivery_date->toDateString());
        $this->assertSame($this->product->id, $schedule->product_id);
        $this->assertSame($this->address->id, $schedule->shipping_address_id);
        $this->assertSame('2.00', $schedule->load_size);
        $this->assertSame('780.00', $schedule->unit_price);

        Mail::assertSent(ScheduleCreatedMail::class);
        Notification::assertSentTo($this->user, \App\Notifications\CategoryNotification::class);
    }

    public function test_single_delivery_checkout_creates_no_schedule(): void
    {
        $this->fillCart();

        $this->actingAs($this->user, 'api')
            ->postJson('/api/orders/store', [
                'shipping_address_id' => $this->address->id,
                'payment_method' => 'cash_on_delivery',
                'preferred_delivery_date' => '06-11-2026',
            ])
            ->assertOk();

        $this->assertSame(1, Order::where('user_id', $this->user->id)->count());
        $this->assertSame(0, Schedule::count());
    }

    public function test_repeat_checkout_validates_details(): void
    {
        $this->fillCart();
        $client = $this->actingAs($this->user, 'api');

        $client->postJson('/api/orders/store', $this->checkoutPayload(['frequency' => 'hourly']))
            ->assertStatus(422)->assertJsonValidationErrors('repeat_details.frequency');
        $client->postJson('/api/orders/store', $this->checkoutPayload(['frequency' => 'custom']))
            ->assertStatus(422)->assertJsonValidationErrors('repeat_details.custom_days');
        $client->postJson('/api/orders/store', $this->checkoutPayload(['first_delivery_date' => '2026-11-01']))
            ->assertStatus(422)->assertJsonValidationErrors('repeat_details.first_delivery_date');

        $this->assertSame(0, Order::count());
    }

    public function test_repeat_checkout_rejects_a_cart_with_several_products(): void
    {
        $this->fillCart();
        $this->fillCart(Product::create(['category_id' => $this->product->category_id, 'subcategory_id' => $this->product->subcategory_id, 'name' => 'Half Cage - (2 Scoops, HT)', 'price' => '280', 'quantity' => 5, 'status' => 1]));

        $this->actingAs($this->user, 'api')
            ->postJson('/api/orders/store', $this->checkoutPayload())
            ->assertStatus(422);

        $this->assertSame(0, Order::count());
    }

    public function test_index_matches_spec_and_hides_cancelled_and_one_off_schedules(): void
    {
        $active = $this->makeSchedule();
        $this->makeSchedule(['status' => 'cancelled']);
        $this->makeSchedule(['repeat' => false, 'next_delivery_date' => null]);

        $response = $this->actingAs($this->user, 'api')->getJson('/api/v2/schedules')->assertOk();

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('message', 'Repeat deliveries retrieved successfully.')
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonPath('data.0.status', 'active')
            ->assertJsonPath('data.0.product.id', $this->product->id)
            ->assertJsonPath('data.0.product.name', 'Jarrah Bush Mix')
            ->assertJsonPath('data.0.product.load_size', '2 Tonne')
            ->assertJsonPath('data.0.product.price', '780.00')
            ->assertJsonPath('data.0.frequency', 'every_2_weeks')
            ->assertJsonPath('data.0.frequency_label', 'Every 2 weeks')
            ->assertJsonPath('data.0.next_delivery_date', '2026-11-06')
            ->assertJsonPath('data.0.next_delivery_date_formatted', 'Fri 6 Nov 2026')
            ->assertJsonPath('data.0.preferred_time', 'Anytime (6:00am - 5:00pm)')
            ->assertJsonPath('data.0.delivery_address.id', $this->address->id)
            ->assertJsonPath('data.0.delivery_address.full_address', '123 Example Rd, Baldivis WA')
            ->assertJsonPath('data.0.payment_method', 'Card');
    }

    public function test_legacy_index_keeps_the_response_the_shipped_app_reads(): void
    {
        $this->makeSchedule(['repeat' => false, 'next_delivery_date' => null]);

        $this->actingAs($this->user, 'api')->getJson('/api/schedules')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.preferred_date', '06/11/2026')
            ->assertJsonStructure(['data' => [['id', 'user_id', 'order_id', 'preferred_date', 'repeat', 'status', 'order_number', 'products']]]);
    }

    public function test_update_changes_schedule_and_moves_undelivered_first_order(): void
    {
        $schedule = $this->makeSchedule();

        $this->actingAs($this->user, 'api')
            ->putJson("/api/v2/schedules/{$schedule->id}", [
                'frequency' => 'every_month',
                'custom_days' => null,
                'next_delivery_date' => '2026-11-15',
                'preferred_time' => 'Morning (6:00am - 12:00pm)',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Repeat delivery updated successfully.')
            ->assertJsonPath('data.frequency', 'every_month')
            ->assertJsonPath('data.next_delivery_date', '2026-11-15')
            ->assertJsonPath('data.preferred_time', 'Morning (6:00am - 12:00pm)');

        $this->assertSame('2026-11-15', $schedule->order->fresh()->preferred_delivery_date->toDateString());
        Event::assertDispatched(\App\Events\OrderDeliveryDateUpdated::class);
    }

    public function test_update_requires_custom_days_for_custom_frequency(): void
    {
        $schedule = $this->makeSchedule();
        $client = $this->actingAs($this->user, 'api');

        $client->putJson("/api/v2/schedules/{$schedule->id}", ['frequency' => 'custom'])->assertStatus(422);
        $client->putJson("/api/v2/schedules/{$schedule->id}", ['next_delivery_date' => '2026-11-01'])->assertStatus(422);

        $client->putJson("/api/v2/schedules/{$schedule->id}", ['frequency' => 'custom', 'custom_days' => 21])
            ->assertOk()->assertJsonPath('data.frequency', 'custom');
        $this->assertSame(21, $schedule->fresh()->custom_days);
        $this->assertSame('Every 3 weeks', $schedule->fresh()->frequency_label);

        $client->putJson("/api/v2/schedules/{$schedule->id}", ['frequency' => 'every_week'])->assertOk();
        $this->assertNull($schedule->fresh()->custom_days);
    }

    /**
     * @dataProvider skipCases
     */
    public function test_skip_advances_by_frequency(string $frequency, ?int $customDays, string $from, string $expected): void
    {
        $schedule = $this->makeSchedule([
            'frequency' => $frequency,
            'custom_days' => $customDays,
            'next_delivery_date' => $from,
        ]);

        $this->actingAs($this->user, 'api')
            ->postJson("/api/v2/schedules/{$schedule->id}/skip")
            ->assertOk()
            ->assertJsonPath('data.previous_delivery_date', $from)
            ->assertJsonPath('data.next_delivery_date', $expected);
    }

    public static function skipCases(): array
    {
        return [
            'weekly' => ['every_week', null, '2026-11-06', '2026-11-13'],
            'two weeks' => ['every_2_weeks', null, '2026-11-06', '2026-11-20'],
            'monthly' => ['every_month', null, '2026-11-06', '2026-12-06'],
            'month end does not overflow' => ['every_month', null, '2027-01-31', '2027-02-28'],
            'custom' => ['custom', 21, '2026-11-06', '2026-11-27'],
            'stale date lands in the future' => ['every_week', null, '2026-10-01', '2026-11-05'],
        ];
    }

    public function test_skip_returns_spec_message_moves_first_order_and_notifies(): void
    {
        $schedule = $this->makeSchedule();

        $this->actingAs($this->user, 'api')
            ->postJson("/api/v2/schedules/{$schedule->id}/skip")
            ->assertOk()
            ->assertJsonPath('message', 'Next delivery skipped successfully. Your repeat schedule will continue on Fri 20 Nov 2026.')
            ->assertJsonPath('data.next_delivery_date_formatted', 'Fri 20 Nov 2026');

        $this->assertSame('2026-11-20', $schedule->order->fresh()->preferred_delivery_date->toDateString());
        Mail::assertSent(RepeatDeliveryStatusMail::class);
    }

    public function test_skip_does_not_touch_a_delivered_order(): void
    {
        $schedule = $this->makeSchedule();
        $schedule->order->update(['order_status' => 'delivered']);

        $this->actingAs($this->user, 'api')->postJson("/api/v2/schedules/{$schedule->id}/skip")->assertOk();

        $this->assertSame('2026-11-06', $schedule->order->fresh()->preferred_delivery_date->toDateString());
    }

    public function test_pause_and_resume_toggle_status(): void
    {
        $schedule = $this->makeSchedule();
        $client = $this->actingAs($this->user, 'api');

        $client->postJson("/api/v2/schedules/{$schedule->id}/resume")->assertStatus(422);

        $client->postJson("/api/v2/schedules/{$schedule->id}/pause")
            ->assertOk()
            ->assertJsonPath('message', 'Repeat delivery has been paused.')
            ->assertJsonPath('data.status', 'paused');

        $client->postJson("/api/v2/schedules/{$schedule->id}/skip")->assertStatus(422);

        $client->getJson('/api/v2/schedules')->assertJsonPath('data.0.status', 'paused');

        $client->postJson("/api/v2/schedules/{$schedule->id}/resume")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_resume_rolls_a_passed_date_forward(): void
    {
        $schedule = $this->makeSchedule(['status' => 'paused', 'frequency' => 'every_week', 'next_delivery_date' => '2026-10-01']);

        $this->actingAs($this->user, 'api')
            ->postJson("/api/v2/schedules/{$schedule->id}/resume")
            ->assertOk()
            ->assertJsonPath('data.next_delivery_date', '2026-11-05');
    }

    public function test_cancel_records_reason_and_timestamp_and_is_final(): void
    {
        $schedule = $this->makeSchedule();
        $client = $this->actingAs($this->user, 'api');

        $client->postJson("/api/v2/schedules/{$schedule->id}/cancel", ['reason' => 'Moving house'])
            ->assertOk()
            ->assertJsonPath('message', 'Repeat delivery has been cancelled. No future deliveries will be scheduled.')
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancelled_at', '2026-11-02T09:00:00Z');

        $fresh = $schedule->fresh();
        $this->assertSame('Moving house', $fresh->cancel_reason);
        $this->assertNotNull($fresh->cancelled_at);

        $client->postJson("/api/v2/schedules/{$schedule->id}/cancel")->assertStatus(422);
        $client->postJson("/api/v2/schedules/{$schedule->id}/skip")->assertStatus(422);
        $client->postJson("/api/v2/schedules/{$schedule->id}/resume")->assertStatus(422);
        $client->putJson("/api/v2/schedules/{$schedule->id}", ['frequency' => 'every_week'])->assertStatus(422);
        $client->getJson('/api/v2/schedules')->assertJsonCount(0, 'data');
    }

    public function test_cancel_without_a_reason_is_allowed(): void
    {
        $schedule = $this->makeSchedule();

        $this->actingAs($this->user, 'api')->postJson("/api/v2/schedules/{$schedule->id}/cancel")->assertOk();

        $this->assertNull($schedule->fresh()->cancel_reason);
    }

    public function test_customers_cannot_touch_each_others_schedules(): void
    {
        $schedule = $this->makeSchedule();
        $client = $this->actingAs(User::factory()->create(), 'api');

        $client->getJson('/api/v2/schedules')->assertJsonCount(0, 'data');
        $client->putJson("/api/v2/schedules/{$schedule->id}", ['frequency' => 'every_week'])->assertNotFound();
        foreach (['skip', 'pause', 'resume', 'cancel'] as $action) {
            $client->postJson("/api/v2/schedules/{$schedule->id}/{$action}")->assertNotFound();
        }

        $this->assertSame('active', $schedule->fresh()->status);
        $this->assertSame('every_2_weeks', $schedule->fresh()->frequency);
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v2/schedules')->assertUnauthorized();
        $this->postJson('/api/v2/schedules/1/skip')->assertUnauthorized();
    }

    public function test_status_mail_renders(): void
    {
        $schedule = $this->makeSchedule();
        $html = (new RepeatDeliveryStatusMail($schedule, 'Repeat Delivery Paused', 'Your repeat delivery has been paused.'))->render();
        $this->assertStringContainsString('Your repeat delivery has been paused.', $html);

        $this->assertStringContainsString('Recurring', (new ScheduleCreatedMail($schedule->load('order.items', 'user')))->render());
    }
}
