<?php

namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;
use App\Models\Cart;
use App\Models\Product;
use App\Models\Coupon;
use App\Models\DeliveryType;
use Illuminate\Support\Facades\DB;
use App\Models\Giveaway;
use App\Models\User;
use App\Models\ReferralUsage;
use App\Services\OrderCalculationService;
use App\Models\Bundle;
use App\Models\BundleExtra;
use App\Models\CartBundleExtra;

class CartController extends Controller
{
    use ApiResponse;


    public function index(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) return $this->error('Unauthorized', 401);

            $cartItems = Cart::with([
                'product:id,name,price,quantity,image',
                'giveaway:id,prize_name,prize_value,image,prize_type',
                // Bundle Image With Base Url
                'bundle:id,name,image',
                // Bundle Image With Base Url

                'bundleExtras.product:id,name',
                'product.reviews' => function ($query) {
                    $query->select('id', 'product_id', 'rating')
                        ->where('status', 'approved');
                }
            ])->where('user_id', $user->id)->get();

            $cartItems->transform(function ($item) {
                if ($item->product) {
                    $item->product->average_rating = round($item->product->reviews->avg('rating') ?? 0, 1);
                    $item->product->review_count = $item->product->reviews->count();
                    unset($item->product->reviews);
                }

                // Add full URL for bundle image
                if ($item->bundle && $item->bundle->image) {
                    $item->bundle->image = asset('uploads/bundles/' . $item->bundle->image);
                }

                return $item;
            });

            $subtotal = $cartItems->sum('subtotal');

            $giveawayDiscount = 0;
            $packageGiveaway = $cartItems->first(function ($item) {
                return $item->giveaway && $item->giveaway->prize_type === 'discount';
            });

            if ($packageGiveaway) {
                $giveawayDiscount = $packageGiveaway->giveaway->prize_value;
            }

            $couponDiscount = 0;
            if ($user->appliedCoupon ?? false) {
                $coupon = Coupon::where('code', $user->appliedCoupon)->first();
                if ($coupon && $coupon->isValid($user->id)) {
                    $couponDiscount = $coupon->calculateDiscount($subtotal, $user->id);
                }
            }

            $totalDiscount = $giveawayDiscount + $couponDiscount;

            $tax = $subtotal * 0.1;
            $deliveryFee = 0;
            $total = round($subtotal + $deliveryFee - $totalDiscount, 2);

            return $this->success([
                'cart_items' => $cartItems,
                'items_count' => $cartItems->count(),
                'subtotal' => $subtotal,
                'tax' => $tax,
                'delivery_fee' => $deliveryFee,
                'discount' => round($totalDiscount, 2),
                'total' => $total,
                'publish_key' => env('STRIPE_PUBLIC_KEY')
            ], 'Cart retrieved successfully.');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve cart: ' . $e->getMessage(), 500);
        }
    }




    public function store(Request $request)
    {
        $request->validate([
            'product_id'  => 'nullable|exists:products,id',
            'giveaway_id' => 'nullable|exists:giveaways,id',
            'bundle_id'   => 'nullable|exists:bundles,id',
            'quantity'    => 'required|integer|min:1',
            'extras'                  => 'nullable|array',
            'extras.*.product_id'     => 'required_with:extras|exists:products,id',
            'extras.*.quantity'       => 'required_with:extras|integer|min:1',
        ]);

        if (!$request->product_id && !$request->giveaway_id && !$request->bundle_id) {
            return $this->error('One of product_id, giveaway_id, or bundle_id is required.', 422);
        }

        $user = $request->user();
        if (!$user) return $this->error('Unauthorized', 401);

        try {

            $cartItem = DB::transaction(function () use ($request, $user) {

                if ($request->product_id) {

                    $product = Product::lockForUpdate()->findOrFail($request->product_id);

                    $existing = Cart::where('user_id', $user->id)
                        ->where('product_id', $request->product_id)
                        ->first();

                    $newQty = ($existing->quantity ?? 0) + $request->quantity;

                    return Cart::updateOrCreate(
                        ['user_id' => $user->id, 'product_id' => $request->product_id],
                        [
                            'quantity' => $newQty,
                            'price'    => $product->numeric_price,
                            'subtotal' => $newQty * $product->numeric_price,
                            'cart_reminder_sent_at' => null
                        ]
                    )->load('product');
                }

                if ($request->giveaway_id) {

                    $giveaway = Giveaway::lockForUpdate()->findOrFail($request->giveaway_id);

                    if ($giveaway->winner_id !== $user->id) {
                        throw new \Exception('You are not the winner of this giveaway.');
                    }

                    $alreadyClaimed = \App\Models\OrderItem::where('giveaway_id', $request->giveaway_id)
                        ->whereHas('order', function ($query) use ($user) {
                            $query->where('user_id', $user->id)
                                ->whereIn('order_status', ['processing', 'completed', 'shipped', 'delivered']);
                        })
                        ->exists();

                    if ($alreadyClaimed) {
                        throw new \Exception('You have already claimed this giveaway prize. Each prize can only be claimed once.');
                    }

                    $existing = Cart::where('user_id', $user->id)
                        ->where('giveaway_id', $request->giveaway_id)
                        ->first();

                    if ($existing) {
                        return $this->success($existing->load('giveaway'), 'Item is already in your cart (maximum 1 per winner).');
                    }

                    return Cart::create([
                        'user_id' => $user->id,
                        'giveaway_id' => $request->giveaway_id,
                        'quantity' => 1,
                        'price'    => $giveaway->numeric_price ?? 0,
                        'subtotal' => 1 * ($giveaway->numeric_price ?? 0)
                    ])->load('giveaway');
                }

                if ($request->bundle_id) {
                    return $this->addBundleToCart($request, $user);
                }
            });

            return $this->success($cartItem, 'Item added to cart successfully.');
        } catch (\Throwable $e) {
            return $this->error('Failed to add item to cart: ' . $e->getMessage(), 500);
        }
    }

    private function addBundleToCart(Request $request, $user)
    {
        $bundle = Bundle::where('status', 1)->lockForUpdate()->findOrFail($request->bundle_id);
        $baseProduct = Product::lockForUpdate()->findOrFail($bundle->base_product_id);

        $quantity = (int) $request->quantity;

        if (!$baseProduct->isInStock($quantity)) {
            throw new \Exception("Base product {$baseProduct->name} is out of stock.");
        }

        $curatedExtras = BundleExtra::where('bundle_id', $bundle->id)
            ->where('status', 1)
            ->get();
        $requiredProductIds = $curatedExtras->where('is_required', true)->pluck('product_id')->toArray();
        $optionalProductIds = $curatedExtras->where('is_required', false)->pluck('product_id')->toArray();

        $selectedExtras = [];
        $extrasTotal = 0.0;

        // Required extras are server-determined, not client-submitted — the
        // customer cannot omit or opt out of one the admin marked mandatory.
        foreach ($requiredProductIds as $productId) {
            $requiredProduct = Product::lockForUpdate()->findOrFail($productId);

            if (!$requiredProduct->isInStock(1)) {
                throw new \Exception("Required item {$requiredProduct->name} is out of stock.");
            }

            $selectedExtras[] = [
                'product_id' => $productId,
                'quantity' => 1,
                'price' => $requiredProduct->numeric_price,
            ];
            $extrasTotal += $requiredProduct->numeric_price;
        }

        foreach ((array) $request->input('extras', []) as $extraInput) {
            $productId = (int) $extraInput['product_id'];

            // Already included above via the required-extras loop — ignore
            // rather than error, since the frontend may resubmit a required
            // extra's id alongside the optional ones it actually chose.
            if (in_array($productId, $requiredProductIds, true)) {
                continue;
            }

            if (!in_array($productId, $optionalProductIds, true)) {
                throw new \Exception("Product {$productId} is not a valid extra for this bundle.");
            }

            $extraQty = (int) $extraInput['quantity'];
            $extraProduct = Product::lockForUpdate()->findOrFail($productId);

            if (!$extraProduct->isInStock($extraQty)) {
                throw new \Exception("Extra {$extraProduct->name} is out of stock.");
            }

            $selectedExtras[] = [
                'product_id' => $productId,
                'quantity' => $extraQty,
                'price' => $extraProduct->numeric_price,
            ];
            $extrasTotal += $extraProduct->numeric_price * $extraQty;
        }

        $subtotalBeforeDiscount = $bundle->effective_price + $extrasTotal;
        $unitPrice = $bundle->applyDiscount($subtotalBeforeDiscount);
        $subtotal = $unitPrice * $quantity;

        $cart = Cart::updateOrCreate(
            ['user_id' => $user->id, 'bundle_id' => $bundle->id],
            [
                'quantity' => $quantity,
                'price'    => $unitPrice,
                'subtotal' => $subtotal,
                'cart_reminder_sent_at' => null,
            ]
        );

        // Replace-on-reconfigure: whatever extras were selected this time fully replace the previous set.
        CartBundleExtra::where('cart_id', $cart->id)->delete();
        foreach ($selectedExtras as $extra) {
            CartBundleExtra::create([
                'cart_id' => $cart->id,
                'product_id' => $extra['product_id'],
                'quantity' => $extra['quantity'],
                'price' => $extra['price'],
            ]);
        }

        return $cart->load(['bundle', 'bundleExtras.product']);
    }



    public function update(Request $request, $id)
    {
        $request->validate(['quantity' => 'required|integer|min:1']);

        $user = $request->user();
        if (!$user) return $this->error('Unauthorized', 401);

        try {
            $cartItem = Cart::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$cartItem) {
                return $this->error('Cart item not found or already removed.', 404);
            }

            DB::transaction(function () use ($cartItem, $request) {

                $cartItem->update([
                    'quantity' => $request->quantity,
                    'subtotal' => $request->quantity * $cartItem->price,
                    'cart_reminder_sent_at' => null
                ]);
            });

            $cartItem->load(['product', 'giveaway']);
            return $this->success($cartItem, 'Cart item updated successfully.');
        } catch (\Throwable $e) {
            return $this->error('Failed to update cart item: ' . $e->getMessage(), 500);
        }
    }



    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        if (!$user) return $this->error('Unauthorized', 401);

        $deleted = Cart::where('id', $id)->where('user_id', $user->id)->delete();
        if (!$deleted) return $this->error('Cart item not found.', 404);

        return $this->success([], 'Item removed from cart successfully.');
    }



    public function calculateTotal(Request $request)
    {
        $user = $request->user();
        $cartItems = Cart::where('user_id', $user->id)->get();

        if ($cartItems->isEmpty()) {
            return $this->error('Cart is empty.', 400);
        }

        $deliveryTypeId = $request->delivery_type_id ?: 1;
        $couponCode = $request->coupon_code;
        $shippingAddressId = $request->shipping_address_id;

        try {
            $calculation = OrderCalculationService::calculate(
                $user,
                $couponCode,
                $deliveryTypeId,
                $shippingAddressId
            );

            foreach ($cartItems as $cartItem) {
                if ($request->filled('delivery_type_id')) {
                    $cartItem->delivery_type_id = $deliveryTypeId;
                }
                if ($request->filled('coupon_code')) {
                    $cartItem->coupon_code = $couponCode;
                }
                $cartItem->save();
            }

            return $this->success($calculation, 'Cart total calculated successfully.');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}
