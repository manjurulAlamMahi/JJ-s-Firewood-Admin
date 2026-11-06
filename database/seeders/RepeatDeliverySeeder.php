<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Schedule;
use App\Models\ShippingAddress;
use App\Models\User;
use App\Models\ZipCode;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RepeatDeliverySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Ensure deliverable zip code exists
        ZipCode::firstOrCreate(
            ['zip_code' => '6000'],
            ['status' => 1, 'group_id' => null]
        );

        // 2. Identify target users to seed repeat deliveries for
        $targetEmails = [
            'forhadali.softvence@gmail.com',
            'test@jjsfirewood.test',
            'admin@gmail.com',
        ];

        $users = [];
        foreach ($targetEmails as $email) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $email === 'forhadali.softvence@gmail.com' ? 'Forhad Ali' : ($email === 'admin@gmail.com' ? 'Admin User' : 'Test Customer'),
                    'phone' => '0400000000',
                    'password' => Hash::make('password123'),
                    'email_verified_at' => now(),
                ]
            );

            // Ensure email is verified and password is valid
            if (!$user->email_verified_at) {
                $user->email_verified_at = now();
                $user->save();
            }

            $users[] = $user;
        }

        // 3. Resolve products
        $product1T = Product::where('status', 1)->where('name', 'like', '%1T%')->first()
            ?? Product::where('status', 1)->first();

        $productHalfT = Product::where('status', 1)->where('name', 'like', '%HT%')->first()
            ?? Product::where('status', 1)->skip(1)->first()
            ?? $product1T;

        $productTruck = Product::where('status', 1)->where('name', 'like', '%4T%')->first()
            ?? Product::where('status', 1)->skip(2)->first()
            ?? $product1T;

        $productBags = Product::where('status', 1)->where('name', 'like', '%Bags%')->first()
            ?? Product::where('status', 1)->skip(3)->first()
            ?? $product1T;

        foreach ($users as $user) {
            $address = $this->ensureShippingAddress($user);

            $this->seedDeliveriesForUser($user, $address, [
                'product_1t' => $product1T,
                'product_ht' => $productHalfT,
                'product_4t' => $productTruck,
                'product_bags' => $productBags,
            ]);
        }

        $this->command?->info('Repeat delivery seed data populated successfully.');
    }

    private function ensureShippingAddress(User $user): ShippingAddress
    {
        return ShippingAddress::firstOrCreate(
            ['user_id' => $user->id, 'label' => 'Home'],
            [
                'full_name' => $user->name ?: 'Customer Name',
                'phone' => $user->phone ?: '0400000000',
                'email' => $user->email,
                'address' => '123 Hay Street',
                'suburb' => 'Perth',
                'city' => 'Perth',
                'state' => 'WA',
                'country' => 'Australia',
                'zip_code' => '6000',
                'is_default' => 1,
            ]
        );
    }

    private function seedDeliveriesForUser(User $user, ShippingAddress $address, array $products): void
    {
        $specs = [
            [
                'code' => 'RPT-01',
                'product' => $products['product_1t'],
                'load_size' => Schedule::tonnesFromProductName($products['product_1t']?->name) ?? 1.00,
                'frequency' => 'every_2_weeks',
                'custom_days' => null,
                'preferred_time' => 'Anytime (6:00am - 5:00pm)',
                'days_from_now' => 5,
                'status' => 'active',
                'payment_method' => 'square',
            ],
            [
                'code' => 'RPT-02',
                'product' => $products['product_ht'],
                'load_size' => Schedule::tonnesFromProductName($products['product_ht']?->name) ?? 0.50,
                'frequency' => 'every_month',
                'custom_days' => null,
                'preferred_time' => 'Morning (6:00am - 12:00pm)',
                'days_from_now' => 16,
                'status' => 'active',
                'payment_method' => 'cash_on_delivery',
            ],
            [
                'code' => 'RPT-03',
                'product' => $products['product_bags'],
                'load_size' => Schedule::tonnesFromProductName($products['product_bags']?->name) ?? 1.00,
                'frequency' => 'every_week',
                'custom_days' => null,
                'preferred_time' => 'Afternoon (12:00pm - 5:00pm)',
                'days_from_now' => 10,
                'status' => 'paused',
                'payment_method' => 'bank_transfer',
            ],
            [
                'code' => 'RPT-04',
                'product' => $products['product_4t'],
                'load_size' => Schedule::tonnesFromProductName($products['product_4t']?->name) ?? 4.00,
                'frequency' => 'custom',
                'custom_days' => 21,
                'preferred_time' => 'Anytime (6:00am - 5:00pm)',
                'days_from_now' => 8,
                'status' => 'active',
                'payment_method' => 'square',
            ],
            [
                'code' => 'RPT-05',
                'product' => $products['product_1t'],
                'load_size' => Schedule::tonnesFromProductName($products['product_1t']?->name) ?? 1.00,
                'frequency' => 'every_2_weeks',
                'custom_days' => null,
                'preferred_time' => 'Anytime (6:00am - 5:00pm)',
                'days_from_now' => -14,
                'status' => 'cancelled',
                'cancel_reason' => 'No longer needed for current season',
                'cancelled_at' => Carbon::now()->subDays(3),
                'payment_method' => 'square',
            ],
        ];

        foreach ($specs as $index => $spec) {
            $product = $spec['product'];
            if (!$product) {
                continue;
            }

            $price = (float) $product->price;
            $idempotencyKey = sprintf('seed-repeat-order-%d-%02d', $user->id, $index + 1);
            $deliveryDate = Carbon::now()->addDays($spec['days_from_now'])->toDateString();

            // 1. Order
            $order = Order::updateOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'user_id' => $user->id,
                    'shipping_address_id' => $address->id,
                    'subtotal' => $price,
                    'delivery_fee' => 0,
                    'discount' => 0,
                    'tax' => 0,
                    'total' => $price,
                    'payment_method' => $spec['payment_method'],
                    'payment_status' => 'paid',
                    'order_status' => $spec['status'] === 'cancelled' ? 'cancelled' : 'processing',
                    'preferred_delivery_date' => $deliveryDate,
                ]
            );

            // 2. Order Item
            OrderItem::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => 1,
                    'price' => $price,
                    'subtotal' => $price,
                ]
            );

            // 3. Repeat Delivery Schedule
            Schedule::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                ],
                [
                    'preferred_date' => $deliveryDate,
                    'next_delivery_date' => $deliveryDate,
                    'preferred_time' => $spec['preferred_time'],
                    'repeat' => true,
                    'frequency' => $spec['frequency'],
                    'custom_days' => $spec['custom_days'],
                    'status' => $spec['status'],
                    'cancel_reason' => $spec['cancel_reason'] ?? null,
                    'cancelled_at' => $spec['cancelled_at'] ?? null,
                    'shipping_address_id' => $address->id,
                    'product_id' => $product->id,
                    'load_size' => $spec['load_size'],
                    'unit_price' => $price,
                ]
            );
        }
    }
}
