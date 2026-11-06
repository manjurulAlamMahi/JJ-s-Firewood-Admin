<?php

namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\DeliveryType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\NotificationService;
use App\Services\SquarePaymentService;
use App\Events\OrderPlaced;
use App\Events\OrderCancelled;
use App\Models\ShippingAddress;
use App\Models\ZipCode;
use App\Models\Entry;
use App\Models\User;
use App\Models\PointHistory;
use Illuminate\Support\Facades\Auth;
use Stripe\StripeClient;
use Carbon\Carbon;
use App\Services\OrderCalculationService;
use App\Models\BankTransfer;
use App\Models\PaymentMethod;
use App\Models\Schedule;


class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(
        SquarePaymentService $squareService
    )
    {
        $this->squareService = $squareService;
    }


    public function index(Request $request, $orderNumber = null)
    {
        try {
            if ($orderNumber) {
                $order = Order::with(['orderItems.product.category', 'orderItems.giveaway', 'address'])
                    ->where('order_number', $orderNumber)
                    ->firstOrFail();

                $address = $order->address ? [
                    'full_name' => $order->address->full_name,
                    'phone'     => $order->address->phone,
                    'email'     => $order->address->email,
                    'address'   => $order->address->address,
                    'suburb'    => $order->address->suburb,
                    'city'      => $order->address->city,
                    'state'     => $order->address->state,
                    'country'   => $order->address->country,
                    'zip_code'  => $order->address->zip_code,
                ] : null;

                $categoriesQty = [];
                foreach ($order->orderItems as $item) {
                    $catName = ($item->product && $item->product->category) ? $item->product->category->name : ($item->giveaway ? 'Giveaway' : 'Uncategorized');
                    $categoriesQty[$catName] = ($categoriesQty[$catName] ?? 0) + $item->quantity;
                }

                $categories = [];
                foreach ($categoriesQty as $name => $qty) {
                    $categories[] = [
                        'category_name' => $name,
                        'add_product'   => $qty,
                    ];
                }

                $products = $order->orderItems->map(function ($item) {
                    $image = null;
                    if ($item->product && $item->product->image) {
                         $image = $item->product->image;
                    } elseif ($item->giveaway && $item->giveaway->image) {
                         $image = $item->giveaway->image;
                    }

                    return [
                        'product_id' => $item->product_id,
                        'name'       => $item->product_name ?? ($item->product->name ?? 'Deleted Product'),
                        'price'      => $item->price,
                        'quantity'   => $item->quantity,
                        'subtotal'   => $item->total_price,
                        'category'   => $item->product->category->name ?? ($item->giveaway ? 'Giveaway' : null),
                        'image'      => $image,
                    ];
                });

                $data = [
                    'order_id'     => $order->id,
                    'order_number' => $order->order_number,
                    'order_status' => $order->order_status,
                    'payment_status' => $order->payment_status,
                    'total_amount' => $order->total,
                    'payment_link' => $order->payment_link,
                    'payment_link_expired' => $order->payment_link_expires_at && now()->get($order->payment_link_expires_at),
                    'address'      => $address,
                    'notes'        => $order->notes,
                    'categories'   => $categories,
                    'products'     => $products,
                    'created_at'   => $order->created_at,
                    'updated_at'   => $order->updated_at,
                    'bank_transfer_details' => $order->payment_method === 'bank_transfer' ? BankTransfer::where('status', 1)->first() : null,
                ];

                return $this->success($data, 'Order tracked successfully.');
            }

            $orders = Order::with(['orderItems.product.category', 'orderItems.giveaway', 'address'])
                ->where('user_id', $request->user()->id)
                ->orderBy('id', 'desc')
                ->get()
                ->map(function ($order) {

                    $address = $order->address ? [
                        'full_name' => $order->address->full_name,
                        'phone'     => $order->address->phone,
                        'email'     => $order->address->email,
                        'address'   => $order->address->address,
                        'suburb'    => $order->address->suburb,
                        'city'      => $order->address->city,
                        'state'     => $order->address->state,
                        'country'   => $order->address->country,
                        'zip_code'  => $order->address->zip_code,
                    ] : null;

                    $products = $order->orderItems->map(function ($item) {
                        $image = null;
                        if ($item->product && $item->product->image) {
                             $image = $item->product->image;
                        } elseif ($item->giveaway && $item->giveaway->image) {
                             $image = $item->giveaway->image;
                        }

                        return [
                            'product_id' => $item->product_id, 
                            'name'       => $item->product_name ?? ($item->product->name ?? 'Deleted Product'),
                            'price'      => $item->price,
                            'quantity'   => $item->quantity,
                            'subtotal'   => $item->total_price,
                            'category'   => $item->product->category->name ?? ($item->giveaway ? 'Giveaway' : null),
                            'image'      => $image,
                        ];
                    });

                    return [
                        'order_id'     => $order->id,
                        'order_number' => $order->order_number,
                        'order_status' => $order->order_status,
                        'payment_status' => $order->payment_status,
                        'total_amount' => $order->total,
                        'payment_link' => $order->payment_link,
                        'payment_method' => $order->payment_method,
                        'payment_link_expired' => $order->payment_link_expires_at && now()->gt($order->payment_link_expires_at),
                        'address'      => $address,
                        'notes'        => $order->notes,
                        'preferred_delivery_date' => $order->preferred_delivery_date,
                        'products'     => $products,
                        'created_at'   => $order->created_at,
                        'updated_at'   => $order->updated_at,
                        'bank_transfer_details' => $order->payment_method === 'bank_transfer' ? BankTransfer::where('status', 1)->first() : null,
                    ];
                });

            $wcOrders = collect();
            if ($request->user()->woocommerce_customer_id) {
                $wcOrders = \App\Models\WcOrder::with(['items'])
                    ->where('wc_customer_id', $request->user()->woocommerce_customer_id)
                    ->orderBy('id', 'desc')
                    ->get()
                    ->map(function ($order) {
                        $products = $order->items->map(function ($item) {
                            return [
                                'product_id' => $item->woocommerce_product_id,
                                'name'       => $item->name,
                                'price'      => $item->price,
                                'quantity'   => $item->quantity,
                                'subtotal'   => $item->subtotal,
                                'category'   => 'Website Order',
                                'image'      => null,
                            ];
                        });

                        return [
                            'order_id'     => $order->id,
                            'order_number' => $order->woocommerce_number,
                            'order_status' => $order->wc_status,
                            'payment_status' => $order->payment_status,
                            'total_amount' => $order->total,
                            'payment_link' => null,
                            'payment_method' => $order->payment_method,
                            'payment_link_expired' => false,
                            'address'      => $order->shipping_address,
                            'notes'        => $order->customer_note,
                            'preferred_delivery_date' => $order->preferred_delivery_date,
                            'products'     => $products,
                            'created_at'   => $order->wc_date_created ?? $order->created_at,
                            'updated_at'   => $order->wc_date_modified ?? $order->updated_at,
                            'bank_transfer_details' => null,
                        ];
                    });
            }

            $allOrders = $orders->merge($wcOrders)->sortByDesc('created_at')->values();

            return $this->success($allOrders, 'Orders retrieved successfully.');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve orders: ' . $e->getMessage(), 500);
        }
    }

    public function LetestOne(Request $request)
    {
        try {
            $order = Order::with(['orderItems.product.category', 'orderItems.giveaway', 'address'])
                ->where('user_id', $request->user()->id)
                ->latest('id') 
                ->first();

            $wcOrder = null;
            if ($request->user()->woocommerce_customer_id) {
                $wcOrder = \App\Models\WcOrder::with(['items'])
                    ->where('wc_customer_id', $request->user()->woocommerce_customer_id)
                    ->latest('id')
                    ->first();
            }

            if (!$order && !$wcOrder) {
                return $this->error('No orders found for this user.', 404);
            }

            $latestIsWc = false;
            if ($wcOrder && !$order) {
                $latestIsWc = true;
            } elseif ($wcOrder && $order) {
                $wcDate = $wcOrder->wc_date_created ?? $wcOrder->created_at;
                if (\Carbon\Carbon::parse($wcDate)->gt($order->created_at)) {
                    $latestIsWc = true;
                }
            }

            if ($latestIsWc) {
                $products = $wcOrder->items->map(function ($item) {
                    return [
                        'product_id' => $item->woocommerce_product_id,
                        'name' => $item->name,
                        'price' => $item->price,
                        'quantity' => $item->quantity,
                        'subtotal' => $item->subtotal,
                        'category' => 'Website Order',
                        'image' => null,
                    ];
                });

                $data = [
                    'order_id' => $wcOrder->id,
                    'order_number' => $wcOrder->woocommerce_number,
                    'order_status' => $wcOrder->wc_status,
                    'payment_status' => $wcOrder->payment_status,
                    'total_amount' => $wcOrder->total,
                    'payment_method' => $wcOrder->payment_method,
                    'payment_link' => null,
                    'payment_link_expired' => false,
                    'address' => $wcOrder->shipping_address,
                    'notes' => $wcOrder->customer_note,
                    'preferred_delivery_date' => $wcOrder->preferred_delivery_date,
                    'products' => $products,
                    'created_at' => $wcOrder->wc_date_created ?? $wcOrder->created_at,
                    'updated_at' => $wcOrder->wc_date_modified ?? $wcOrder->updated_at,
                    'bank_transfer_details' => null,
                ];

                return $this->success($data, 'Latest order retrieved successfully.');
            }

            $address = $order->address ? [
                'full_name' => $order->address->full_name,
                'phone' => $order->address->phone,
                'email' => $order->address->email,
                'address' => $order->address->address,
                'suburb' => $order->address->suburb,
                'city' => $order->address->city,
                'state' => $order->address->state,
                'country' => $order->address->country,
                'zip_code' => $order->address->zip_code,
            ] : null;

            $products = $order->orderItems->map(function ($item) {
                $image = null;
                if ($item->product && $item->product->image) {
                    $image = $item->product->image;
                } elseif ($item->giveaway && $item->giveaway->image) {
                    $image = $item->giveaway->image;
                }

                return [
                    'product_id' => $item->product_id,
                    'name' => $item->product_name ?? ($item->product->name ?? 'Deleted Product'),
                    'price' => $item->price,
                    'quantity' => $item->quantity,
                    'subtotal' => $item->total_price,
                    'category' => $item->product->category->name ?? ($item->giveaway ? 'Giveaway' : null),
                    'image' => $image,
                ];
            });

            $data = [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'order_status' => $order->order_status,
                'payment_status' => $order->payment_status,
                'total_amount' => $order->total,
                'payment_method' => $order->payment_method,
                'payment_link' => $order->payment_link,
                'payment_link_expired' => $order->payment_link_expires_at ? now()->gt($order->payment_link_expires_at) : false,
                'address' => $address,
                'notes' => $order->notes,
                'preferred_delivery_date' => $order->preferred_delivery_date,
                'products' => $products,
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
                'bank_transfer_details' => $order->payment_method === 'bank_transfer' 
                    ? BankTransfer::where('status', 1)->first() 
                    : null,
            ];

            return $this->success($data, 'Latest order retrieved successfully.');

        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve latest order: ' . $e->getMessage(), 500);
        }
    }


    public function store(Request $request)
    {
        $request->validate([
            'shipping_address_id' => 'required|exists:shipping_addresses,id',
            'coupon_code' => 'nullable|string|max:50',
            'preferred_delivery_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'payment_method' => 'nullable|string|in:square,cash_on_delivery,bank_transfer,direct_bank_transfer',
        ]);

        $paymentMethod = $request->input('payment_method', 'square');
        if ($paymentMethod === 'credit_card') {
            $paymentMethod = 'square';
        }
        if ($paymentMethod === 'direct_bank_transfer') {
            $paymentMethod = 'bank_transfer';
        }

        if ($request->filled('source_id')) {
            $isActive = PaymentMethod::where('slug', $paymentMethod)->where('is_active', true)->exists();
            if (!$isActive) {
                return $this->error('The selected payment method is currently disabled.', 422);
            }
        }


        \Log::info('Order Store Request', $request->all());

        $user = $request->user();

        $shippingAddress = ShippingAddress::find($request->shipping_address_id);
        if ($shippingAddress && $shippingAddress->zip_code) {
            $zipCodeActive = ZipCode::where('zip_code', $shippingAddress->zip_code)
                ->isDeliverable()
                ->exists();

            if (!$zipCodeActive) {
                return $this->error('The provided zip code is not available for delivery.', 422);
            }
        }

        $cartItems = Cart::with('product')->where('user_id', $user->id)->get();

        if ($cartItems->isEmpty()) {
            return $this->error('Cart is empty.', 400);
        }

        foreach ($cartItems as $cartItem) {
            if (!$cartItem->product->isInStock($cartItem->quantity)) {
                return $this->error(
                    'Product ' . $cartItem->product->name . ' is out of stock.',
                    400
                );
            }
        }

        $subtotal = $cartItems->sum('subtotal');
        $tax = 0;

        $deliveryFee = 0;
        $deliveryInfo = null;
        $deliveryTypeId = $user->delivery_type_id ?: 1;

        $deliveryType = DeliveryType::find($deliveryTypeId);
        if ($deliveryType) {
            $deliveryFee = $deliveryType->fee;

            // Check for zone-specific fee
            if ($request->shipping_address_id) {
                $address = ShippingAddress::find($request->shipping_address_id);
                if ($address && $address->zip_code) {
                    $zipCode = ZipCode::where('zip_code', $address->zip_code)->first();
                    if ($zipCode && $zipCode->group_id) {
                        $zoneFee = \App\Models\ZoneWiseDeliveryType::where('zip_code_group_id', $zipCode->group_id)
                            ->where('delivery_type_id', $deliveryTypeId)
                            ->first();
                        
                        if ($zoneFee) {
                            $deliveryFee = $zoneFee->fee;
                        }
                    }
                }
            }

            $deliveryInfo = [
                'id' => $deliveryType->id,
                'name' => $deliveryType->name,
                'fee' => $deliveryFee,
                'days' => $deliveryType->days,
            ];
        }

        $discount = 0;
        $referralDiscount = 0;
        $couponInfo = null;
        $couponError = null;
        $referrer = null;
        $isReferralCode = false;

        if ($request->coupon_code) {
            $coupon = Coupon::where('code', $request->coupon_code)->first();

            if ($coupon) {
                $validationError = $coupon->getValidationError($user->id);
                if ($validationError) {
                    return $this->error($validationError, 400);
                }
                
                if ($coupon->min_order_amount && $subtotal < $coupon->min_order_amount) {
                    return $this->error(
                        'Minimum order amount £' . number_format($coupon->min_order_amount, 2) . ' required.',
                        400
                    );
                }

                $discount = $coupon->calculateDiscount($subtotal);
                
                $couponInfo = [
                    'id' => $coupon->id,
                    'code' => $coupon->code,
                    'type' => $coupon->type,
                    'value' => $coupon->value,
                    'discount_amount' => round($discount, 2),
                ];

            } else {
                $referrer = User::where('referral_code', $request->coupon_code)->first();

                if ($referrer) {
                    if ($referrer->id === $user->id) {
                        return $this->error('You cannot use your own referral code.', 400);
                    }

                    $alreadyUsed = ReferralUsage::where('referred_user_id', $user->id)
                        ->where('referral_code', $request->coupon_code)
                        ->exists();

                    if ($alreadyUsed) {
                        return $this->error('You have already used this referral code.', 400);
                    }

                    $isReferralCode = true;
                    $referralDiscount = min(10.00, $subtotal); 


                    $couponInfo = [
                        'code' => $request->coupon_code,
                        'type' => 'referral',
                        'discount_amount' => round($referralDiscount, 2),
                        'referrer_name' => $referrer->name,
                    ];

                } else {
                    return $this->error('Invalid coupon or referral code.', 400);
                }
            }
        }

        $total = round(
            $subtotal  + $deliveryFee - $discount - $referralDiscount,
            2
        );

            $loyalty_points = round($total);

            $sessionId = 'pending_order_' . uniqid() . '_' . $user->id;

            $orderData = [
                'user_id' => $user->id,
                'shipping_address_id' => $request->shipping_address_id,
                'coupon_id' => $couponInfo['id'] ?? null,
                'coupon_info' => $couponInfo,
                'referral_info' => $isReferralCode ? [
                    'referrer_id' => $referrer->id ?? null,
                    'referral_code' => $request->coupon_code,
                    'discount_amount' => $referralDiscount,
                ] : null,
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'discount' => $discount + $referralDiscount,
                'tax' => $tax,
                'total' => $total,
                'payment_method' => $paymentMethod,
                'preferred_delivery_date' => $request->preferred_delivery_date ? \Carbon\Carbon::parse($request->preferred_delivery_date)->format('Y-m-d') : null,
                'notes' => $request->notes,
                'loyalty_points' => $loyalty_points,
                'delivery_type_id' => $deliveryTypeId,
                'delivery_info' => $deliveryInfo,
                'cart_items' => $cartItems->map(function ($item) {
                    return [
                        'product_id' => $item->product_id,
                        'product_name' => $item->product->name,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'subtotal' => $item->subtotal,
                    ];
                })->toArray(),
            ];

            Cache::put($sessionId, $orderData, now()->addHours(24));

            \Log::info('Order data cached, creating payment link', [
                'session_id' => $sessionId,
                'user_id' => $user->id,
                'total' => $total
            ]);

            try {
                $tempOrder = new Order();
                $tempOrder->user_id = $user->id;
                $tempOrder->total = $total;
                $tempOrder->payment_method = $paymentMethod;
                $tempOrder->setAttribute('orderItems', collect($orderData['cart_items'])->map(function ($item) {
                    $orderItem = new OrderItem();
                    $orderItem->product_id = $item['product_id'];
                    $orderItem->product_name = $item['product_name'];
                    $orderItem->quantity = $item['quantity'];
                    $orderItem->price = $item['price'];
                    $orderItem->subtotal = $item['subtotal'];
                    
                    $product = Product::find($item['product_id']);
                    $orderItem->setRelation('product', $product);
                    
                    return $orderItem;
                }));
                $tempOrder->setRelation('user', $user);

                $paymentLinkResult = $this->squareService->createPaymentLink($tempOrder, [
                    'session_id' => $sessionId,
                    'user_id' => $user->id,
                ]);

                if ($paymentMethod === 'cash_on_delivery') {
                    $orderService = new \App\Services\OrderCompletionService();
                    $order = $orderService->completeOrder(
                        $sessionId,
                        null, 
                        'cod_' . uniqid(), 
                        'PENDING', 
                        $total,
                        'AUD',
                        ['is_cod' => true]
                    );

                    if ($order->payment_status !== 'paid') {
                        $order->makeHidden(['loyalty_points']);
                    }

                    return $this->success([
                        'success' => true,
                        'message' => 'Order placed successfully (Cash on Delivery).',
                        'order' => $order,
                        'payment_status' => 'pending',
                        'code' => 200
                    ], 200);
                }

                if ($paymentLinkResult && isset($paymentLinkResult['success']) && $paymentLinkResult['success']) {
                    
                    Cache::put($sessionId . '_payment_link', $paymentLinkResult['payment_link'], now()->addHours(24));
                    Cache::put($sessionId . '_square_order_id', $paymentLinkResult['provider_order_id'] ?? null, now()->addHours(24));

                    return response()->json([
                        'success' => true,
                        'message' => 'Redirect to payment link to complete order.',
                        'payment_link' => $paymentLinkResult['payment_link'],
                        'payment_link_id' => $paymentLinkResult['payment_link_id'] ?? null,
                        'provider' => $paymentMethod,
                        'session_id' => $sessionId,
                        'amount' => $total,
                        'delivery_info' => $deliveryInfo,
                        'promo' => [
                            'code_used' => $request->coupon_code,
                            'type' => $isReferralCode ? 'referral' : ($couponInfo ? 'coupon' : null),
                            'discount' => $discount + $referralDiscount,
                            'referrer_name' => $referrer->name ?? null,
                        ],
                        'currency' => 'AUD',
                        'code' => 200
                    ]);
                } else {
                    \Log::error('Payment link creation failed', [
                        'session_id' => $sessionId,
                        'result' => $paymentLinkResult
                    ]);
                    return $this->error('Failed to create payment link.', 500);
                }
            } catch (\Exception $e) {
                \Log::error('Square Service Error', [
                    'session_id' => $sessionId,
                    'error' => $e->getMessage()
                ]);
                return $this->error('Failed to create payment link: ' . $e->getMessage(), 500);
            }
    }


    public function newstore(Request $request)
    {
        $request->validate([
            'shipping_address_id' => 'required|exists:shipping_addresses,id',
            'preferred_delivery_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'source_id' => 'nullable|string|max:255',
            'verification_token' => 'nullable|string|max:1024',
            'payment_method' => 'nullable|string|in:square,cash_on_delivery,bank_transfer,direct_bank_transfer,credit_card,paypal',
            'delivery_option' => 'nullable|string|in:single,repeat',
            'repeat_details' => 'required_if:delivery_option,repeat|array',
            'repeat_details.frequency' => 'required_if:delivery_option,repeat|string|in:' . implode(',', Schedule::FREQUENCIES),
            'repeat_details.custom_days' => 'nullable|integer|min:1|max:' . Schedule::MAX_CUSTOM_DAYS . '|required_if:repeat_details.frequency,custom',
            'repeat_details.first_delivery_date' => 'required_if:delivery_option,repeat|date|after_or_equal:today',
            'repeat_details.preferred_time' => 'nullable|string|max:100',
        ]);

        $user = $request->user();

        // SECURITY: verify the shipping address belongs to the calling user.
        // The 'exists' rule above only checks the row exists in the table; it
        // doesn't enforce ownership. Without this guard, a logged-in user
        // could pass any other user's shipping_address_id and create an
        // order linked to someone else's delivery details.
        $shippingAddress = ShippingAddress::find($request->shipping_address_id);
        if (!$shippingAddress || ($user && $shippingAddress->user_id !== $user->id)) {
            return $this->error('Invalid shipping address.', 403);
        }
        if ($shippingAddress && $shippingAddress->zip_code) {
            $zipCodeActive = ZipCode::where('zip_code', $shippingAddress->zip_code)
                ->isDeliverable()
                ->exists();

            if (!$zipCodeActive) {
                return $this->error('The provided zip code is not available for delivery.', 422);
            }
        }

        $paymentMethod = $request->input('payment_method', 'square');
        if ($paymentMethod === 'credit_card') {
            $paymentMethod = 'square';
        }
        if ($paymentMethod === 'direct_bank_transfer') {
            $paymentMethod = 'bank_transfer';
        }

        if ($request->filled('session_id') || $request->filled('source_id')) {
            $isActive = PaymentMethod::where('slug', $paymentMethod)->where('is_active', true)->exists();
            if (!$isActive) {
                return $this->error('The selected payment method is currently disabled.', 422);
            }
        }
        $sessionId = $request->input('session_id');
        $cachedOrderData = null;

        if ($sessionId) {
            $cachedOrderData = Cache::get($sessionId);
            if ($cachedOrderData) {
                if ($user && $cachedOrderData['user_id'] != $user->id) {
                    \Log::info('OrderController: Web user differs from order owner - proceeding with session_id validation', [
                        'session_id' => $sessionId,
                        'cached_user' => $cachedOrderData['user_id'],
                        'web_user' => $user->id
                    ]);
                }
            }
        }

        if ($cachedOrderData) {
            \Log::info('OrderController: Using cached order data', ['session_id' => $sessionId]);
            $calculation = [
                'total' => $cachedOrderData['total'],
                'subtotal' => $cachedOrderData['subtotal'],
                'delivery_fee' => $cachedOrderData['delivery_fee'],
                'total_discount' => $cachedOrderData['discount'],
                'tax' => $cachedOrderData['tax'],
                'delivery_info' => $cachedOrderData['delivery_info'],
                'coupon' => $cachedOrderData['coupon_info'] ?? null,
            ];
            $orderData = $cachedOrderData;
            $couponCode = $cachedOrderData['coupon_info']['code'] ?? null;
        } else {
            if (!$user) {
                return $this->error('Authentication required to create order from cart', 401);
            }

            $cartItems = Cart::with(['product', 'giveaway', 'bundle', 'bundleExtras.product'])
                ->where('user_id', $user->id)
                ->get();

            if ($cartItems->isEmpty()) {
                \Log::warning('OrderController: Cart is empty for user', ['user_id' => $user->id]);
                return $this->error('Cart is empty.', 400);
            }

            foreach ($cartItems as $cartItem) {
                if ($cartItem->product_id && $cartItem->product && !$cartItem->product->isInStock($cartItem->quantity)) {
                    return $this->error(
                        'Product ' . $cartItem->product->name . ' is out of stock.',
                        400
                    );
                }

                if ($cartItem->bundle_id && $cartItem->bundle) {
                    if (!$cartItem->bundle->baseProduct->isInStock($cartItem->quantity)) {
                        return $this->error(
                            'Bundle base product ' . $cartItem->bundle->baseProduct->name . ' is out of stock.',
                            400
                        );
                    }
                    foreach ($cartItem->bundleExtras as $bundleExtra) {
                        if (!$bundleExtra->product->isInStock($bundleExtra->quantity)) {
                            return $this->error(
                                'Bundle extra ' . $bundleExtra->product->name . ' is out of stock.',
                                400
                            );
                        }
                    }
                }

                if ($cartItem->giveaway_id) {
                    if ($cartItem->giveaway->winner_id !== $user->id) {
                        return $this->error('You are not the winner of this giveaway prize.', 403);
                    }

                    $alreadyClaimed = \App\Models\OrderItem::where('giveaway_id', $cartItem->giveaway_id)
                        ->whereHas('order', function ($query) use ($user) {
                            $query->where('user_id', $user->id)
                                ->whereIn('order_status', ['processing', 'completed', 'shipped', 'delivered']);
                        })
                        ->exists();

                    if ($alreadyClaimed) {
                        $cartItem->delete();
                        \Log::info('Removed already claimed giveaway from cart', [
                            'user_id' => $user->id,
                            'giveaway_id' => $cartItem->giveaway_id
                        ]);
                        
                        $cartItems = $cartItems->reject(function ($item) use ($cartItem) {
                            return $item->id === $cartItem->id;
                        });
                        
                        continue;
                    }
                }
            }

            if ($cartItems->isEmpty()) {
                 return $this->error('Cart is empty after removing invalid items.', 400);
            }

            $repeatDetails = null;
            if ($request->input('delivery_option') === 'repeat') {
                if ($cartItems->count() !== 1 || !$cartItems->first()->product_id) {
                    return $this->error('Repeat delivery can only be set up for a single product.', 422);
                }

                $repeatDetails = $request->input('repeat_details');
            }

            $deliveryTypeId = $cartItems->first()->delivery_type_id ?: 1;
            $couponCode = $cartItems->first()->coupon_code;

            $calculation = OrderCalculationService::calculate(
                $user,
                $couponCode,
                $deliveryTypeId,
                $request->shipping_address_id
            );

            $preferredDate = $request->preferred_delivery_date
                ? \Carbon\Carbon::parse($request->preferred_delivery_date)->format('Y-m-d')
                : null;

            if ($repeatDetails) {
                $preferredDate = \Carbon\Carbon::parse($repeatDetails['first_delivery_date'])->format('Y-m-d');
            }

            $earnedPoints = round($calculation['subtotal']);

            $referralInfo = null;
            if (isset($calculation['coupon']) && $calculation['coupon']['type'] === 'referral') {
                $referrer = User::where('referral_code', $calculation['coupon']['code'])->first();
                if ($referrer) {
                    $referralInfo = [
                        'referrer_id' => $referrer->id,
                        'referral_code' => $calculation['coupon']['code'],
                        'discount_amount' => $calculation['referral_discount'],
                    ];
                }
            }

            $sessionId = 'pending_order_' . uniqid() . '_' . $user->id;

            $orderData = [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'shipping_address_id' => $request->shipping_address_id,
                'coupon_id' => $calculation['coupon']['id'] ?? null,
                'coupon_info' => $calculation['coupon'],
                'referral_info' => $referralInfo,
                'subtotal' => $calculation['subtotal'],
                'delivery_fee' => $calculation['delivery_fee'],
                'discount' => $calculation['total_discount'],
                'tax' => $calculation['tax'],
                'total' => $calculation['total'],
                'payment_method' => $paymentMethod,
                'preferred_delivery_date' => $preferredDate,
                'notes' => $request->notes,
                'loyalty_points' => $earnedPoints,
                'delivery_type_id' => $deliveryTypeId,
                'delivery_info' => $calculation['delivery_info'],
                // Carried through the cached session so every payment path
                // (card, PayPal, webhook, offline) creates the schedule when
                // the order is actually placed.
                'repeat_details' => $repeatDetails,
                'cart_items' => $cartItems->map(function ($item) {
                    return [
                        'product_id' => $item->product_id,
                        'giveaway_id' => $item->giveaway_id,
                        'bundle_id' => $item->bundle_id,
                        'product_name' => $item->product
                            ? $item->product->name
                            : ($item->giveaway ? $item->giveaway->prize_name : ($item->bundle ? $item->bundle->name : 'Item')),
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'subtotal' => $item->subtotal,
                        'bundle_extras' => $item->bundle_id
                            ? $item->bundleExtras->map(function ($extra) {
                                return [
                                    'product_id' => $extra->product_id,
                                    'product_name' => $extra->product->name,
                                    'quantity' => $extra->quantity,
                                    'price' => $extra->price,
                                ];
                            })->toArray()
                            : [],
                    ];
                })->toArray(),
            ];

            Cache::put($sessionId, $orderData, now()->addHours(24));
        }

        if ($calculation['total'] <= 0) {
            try {
                \Log::info('Processing free order (giveaway entry)', [
                    'session_id' => $sessionId,
                    'user_id' => $user ? $user->id : ($cachedOrderData['user_id'] ?? null),
                    'total' => $calculation['total']
                ]);

                $orderService = new \App\Services\OrderCompletionService();
                $order = $orderService->completeOrder(
                    $sessionId,
                    null, 
                    'free_order_' . uniqid(), 
                    'COMPLETED', 
                    0, 
                    null, 
                    [
                        'is_free_order' => true,
                        'order_type' => 'giveaway_entry',
                    ]
                );

                return $this->success([
                    'success' => true,
                    'message' => 'Order placed successfully.',
                    'order' => $order,
                    'payment_status' => 'paid',
                    'is_free_order' => true,
                    'code' => 200
                ], 200);
            } catch (\Exception $e) {
                \Log::error('Free order processing error', [
                    'session_id' => $sessionId,
                    'error' => $e->getMessage()
                ]);
                return $this->error('Failed to place order: ' . $e->getMessage(), 500);
            }
        }

        if ($request->input('payment_method') === 'cash_on_delivery') {
            try {
                \Log::info('Processing COD order (newstore)', [
                    'session_id' => $sessionId,
                    'user_id' => $user ? $user->id : ($cachedOrderData['user_id'] ?? null),
                    'total' => $calculation['total']
                ]);

                $cachedOrderData = Cache::get($sessionId);
                if ($cachedOrderData) {
                    $cachedOrderData['payment_method'] = 'cash_on_delivery';
                    Cache::put($sessionId, $cachedOrderData, now()->addHours(24));
                }

                $orderService = new \App\Services\OrderCompletionService();
                $order = $orderService->completeOrder(
                    $sessionId,
                    null, 
                    'cod_' . uniqid(), 
                    'PENDING', 
                    $calculation['total'],
                    'AUD',
                    ['is_cod' => true]
                );

                return $this->success([
                    'success' => true,
                    'message' => 'Order placed successfully (Cash on Delivery).',
                    'order' => $order,
                    'payment_status' => 'pending',
                    'code' => 200
                ], 200);
            } catch (\Exception $e) {
                \Log::error('COD order processing error (newstore)', [
                    'session_id' => $sessionId,
                    'error' => $e->getMessage()
                ]);
                return $this->error('Failed to place COD order: ' . $e->getMessage(), 500);
            }
        }

        if (in_array($request->input('payment_method'), ['bank_transfer', 'direct_bank_transfer'])) {
            try {
                \Log::info('Processing Bank Transfer order (newstore)', [
                    'session_id' => $sessionId,
                    'user_id' => $user ? $user->id : ($cachedOrderData['user_id'] ?? null),
                    'total' => $calculation['total']
                ]);

                $cachedOrderData = Cache::get($sessionId);
                if ($cachedOrderData) {
                    $cachedOrderData['payment_method'] = 'bank_transfer';
                    Cache::put($sessionId, $cachedOrderData, now()->addHours(24));
                }

                $orderService = new \App\Services\OrderCompletionService();
                $order = $orderService->completeOrder(
                    $sessionId,
                    null, 
                    'bank_transfer_' . uniqid(), 
                    'PENDING', 
                    $calculation['total'],
                    'AUD',
                    ['is_bank_transfer' => true]
                );

                if ($order->payment_status !== 'paid') {
                    $order->makeHidden(['loyalty_points']);
                }

                return $this->success([
                    'success' => true,
                    'message' => 'Order placed successfully. Please complete your bank transfer using your Order ID as reference.',
                    'order' => $order,
                    'payment_status' => 'pending',
                    'code' => 200
                ], 200);
            } catch (\Exception $e) {
                \Log::error('Bank Transfer order processing error (newstore)', [
                    'session_id' => $sessionId,
                    'error' => $e->getMessage()
                ]);
                return $this->error('Failed to place Bank Transfer order: ' . $e->getMessage(), 500);
            }
        }

        if ($request->input('payment_method') === 'paypal') {
            try {
                $cachedOrderData = Cache::get($sessionId);
                if ($cachedOrderData) {
                    $cachedOrderData['payment_method'] = 'paypal';
                    Cache::put($sessionId, $cachedOrderData, now()->addHours(3));
                }

                $paypal = new \App\Services\PayPalService();
                $returnUrl = url('/api/paypal/return');
                $cancelUrl = url('/api/paypal/cancel');
                $result = $paypal->createOrder($calculation['total'], $sessionId, $returnUrl, $cancelUrl);

                if (!$result['success'] || empty($result['approval_url'])) {
                    return $this->error($result['message'] ?? 'Failed to start PayPal payment.', 422);
                }

                Cache::put($sessionId . '_paypal_order_id', $result['paypal_order_id'], now()->addHours(3));

                return response()->json([
                    'success' => true,
                    'message' => 'Approve the payment with PayPal to complete your order.',
                    'payment_method' => 'paypal',
                    'approval_url' => $result['approval_url'],
                    'session_id' => $sessionId,
                    'amount' => $calculation['total'],
                    'code' => 200,
                ], 200);
            } catch (\Exception $e) {
                \Log::error('PayPal order start error', ['session_id' => $sessionId, 'error' => $e->getMessage()]);
                return $this->error('PayPal error: ' . $e->getMessage(), 500);
            }
        }

        if ($request->filled('source_id')) {
            try {
                $userEmail = $user ? $user->email : ($cachedOrderData['user_email'] ?? 'guest@example.com');
                $userId = $user ? $user->id : ($cachedOrderData['user_id'] ?? null);
                $userName = $user ? $user->name : ($cachedOrderData['user_name'] ?? null);

                // Build a clear Square dashboard note that distinguishes app
                // orders from WooCommerce orders. WooCommerce shows
                // "JJ's Firewood Supplies - Order 10249"; we mirror that
                // format but use "App" so the source is obvious at a glance.
                //
                // We "peek" the next order number by looking at the last Order
                // and incrementing — the real order_number is only generated
                // when the order saves, which happens after the payment
                // succeeds. There's a tiny race window if two orders are
                // placed simultaneously, but the predicted number stays
                // searchable in admin even when 1-off.
                $predictedOrderNumber = null;
                try {
                    $lastOrder = \App\Models\Order::orderBy('id', 'DESC')->first();
                    if ($lastOrder && preg_match('/JJS(\d+)/', $lastOrder->order_number ?? '', $m)) {
                        $predictedOrderNumber = 'JJS' . str_pad(((int) $m[1]) + 1, 5, '0', STR_PAD_LEFT);
                    } else {
                        $predictedOrderNumber = 'JJS00001';
                    }
                } catch (\Throwable $e) {
                    \Log::warning('Square note: could not predict order number', [
                        'error' => $e->getMessage(),
                    ]);
                }

                // Format: "JJ's Firewood App — Order #JJS00199 — Peter Keisler"
                $noteParts = ["JJ's Firewood App"];

                if ($predictedOrderNumber) {
                    $noteParts[] = 'Order #' . $predictedOrderNumber;
                }

                if (!empty($userName)) {
                    $noteParts[] = $userName;
                } elseif (!empty($userEmail)) {
                    $noteParts[] = $userEmail;
                }

                // Square caps the note field at 500 chars; truncate defensively.
                $squareNote = mb_substr(implode(' — ', $noteParts), 0, 500);

                \Log::info('Processing direct payment for new order', [
                    'session_id' => $sessionId,
                    'user_id' => $userId,
                    'user_email' => $userEmail,
                    'total' => $calculation['total'],
                    'square_note' => $squareNote,
                    'predicted_order_number' => $predictedOrderNumber,
                ]);

                $paymentResult = $this->squareService->processPayment(
                    $request->source_id,
                    $calculation['total'],
                    null,
                    $squareNote,
                    $sessionId,
                    $userEmail,
                    $userName,
                    $request->input('verification_token')
                );

                if ($paymentResult['success']) {
                    $orderService = new \App\Services\OrderCompletionService();
                    $order = $orderService->completeOrder(
                        $sessionId,
                        null, 
                        $paymentResult['payment_id'],
                        $paymentResult['status'],
                        $paymentResult['amount'],
                        $paymentResult['currency'],
                        [
                            'receipt_url' => $paymentResult['receipt_url'] ?? null,
                            'source_id' => $request->source_id,
                        ]
                    );

                    if ($order->payment_status !== 'paid') {
                        $order->makeHidden(['loyalty_points']);
                    }

                    return $this->success([
                        'success' => true,
                        'message' => 'Order placed successfully.',
                        'order' => $order,
                        'payment_status' => 'paid',
                        'code' => 200
                    ], 200);
                } else {
                    return $this->error($paymentResult['message'] ?? 'Payment failed.', 422);
                }
            } catch (\Exception $e) {
                \Log::error('Direct payment processing error', [
                    'session_id' => $sessionId,
                    'error' => $e->getMessage()
                ]);
                return $this->error('Payment failed: ' . $e->getMessage(), 500);
            }
        }


        $checkoutUrl = route('checkout.show', ['session_id' => $sessionId]);
        
        \Log::info('Order data cached from newstore, returning custom checkout link', [
            'session_id' => $sessionId,
            'user_id' => $user ? $user->id : ($cachedOrderData['user_id'] ?? null),
            'checkout_url' => $checkoutUrl
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Redirect to checkout to complete order.',
            'payment_link' => $checkoutUrl, 
            'provider' => 'local_square',
            'session_id' => $sessionId,
            'amount' => $calculation['total'],
            'delivery_info' => $calculation['delivery_info'],
            'promo' => [
                'code_used' => $couponCode,
                'discount' => $calculation['total_discount'],
            ],
            'code' => 200
        ], 200);
    }


    public function show(Request $request, $id)
    {
        try {
            $order = Order::with(['orderItems.product.category', 'orderItems.giveaway', 'address'])
                ->where('user_id', $request->user()->id)
                ->findOrFail($id);

            $address = $order->address ? [
                'full_name' => $order->address->full_name,
                'phone'     => $order->address->phone,
                'email'     => $order->address->email,
                'address'   => $order->address->address,
                'suburb'    => $order->address->suburb,
                'city'      => $order->address->city,
                'state'     => $order->address->state,
                'country'   => $order->address->country,
                'zip_code'  => $order->address->zip_code,
            ] : null;

            $products = $order->orderItems->map(function ($item) {
                $image = null;
                if ($item->product && $item->product->image) {
                    $image = $item->product->image;
                } elseif ($item->giveaway && $item->giveaway->image) {
                    $image = $item->giveaway->image;
                }

                return [
                    'product_id' => $item->product_id,
                    'name'       => $item->product_name ?? ($item->product->name ?? 'Deleted Product'),
                    'price'      => $item->price,
                    'quantity'   => $item->quantity,
                    'subtotal'   => $item->subtotal,
                    'category'   => $item->product->category->name ?? ($item->giveaway ? 'Giveaway' : null),
                    'image'      => $image,
                ];
            });

            $data = [
                'order_id'     => $order->id,
                'order_number' => $order->order_number,
                'order_status' => $order->order_status,
                'payment_status' => $order->payment_status,
                'total_amount' => $order->total,
                'payment_link' => $order->payment_link,
                'payment_method' => $order->payment_method,
                'payment_link_expired' => $order->payment_link_expires_at && now()->gt($order->payment_link_expires_at),
                'address'      => $address,
                'preferred_delivery_date' => $order->preferred_delivery_date,
                'products'     => $products,
                'created_at'   => $order->created_at,
                'updated_at'   => $order->updated_at,
                'bank_transfer_details' => $order->payment_method === 'bank_transfer' ? BankTransfer::where('status', 1)->first() : null,
            ];

            return $this->success($data, 'Order retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve order: ' . $e->getMessage(), 500);
        }
    }


    public function checkPaymentStatus(Request $request, $id)
    {
        try {
            $order = Order::where('id', $id)
                ->where('user_id', $request->user()->id)
                ->with('items')
                ->firstOrFail();

            $isExpired = $order->payment_link_expires_at && now()->gt($order->payment_link_expires_at);

            return $this->success([
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'payment_status' => $order->payment_status,
                'order_status' => $order->order_status,
                'payment_link' => $order->payment_link,
                'payment_link_expired' => $isExpired,
                'total' => $order->total,
                'transaction_id' => $order->transaction_id,
            ], 'Payment status retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve payment status: ' . $e->getMessage(), 500);
        }
    }

    

    public function cancel(Request $request, $id)
    {
        try {
            $order = Order::where('id', $id)
                ->where('user_id', $request->user()->id)
                ->with('orderItems.product', 'orderItems.bundle.baseProduct', 'orderItems.bundleExtras.product', 'coupon')
                ->firstOrFail();

            if (in_array($order->order_status, ['delivered', 'complete'])) {
                return $this->error('Delivered or completed orders cannot be cancelled.', 400);
            }

            DB::transaction(function () use ($order) {
                $order->order_status = 'cancelled';
                $order->cancellation_requested_at = now();
                $order->save();

                foreach ($order->orderItems as $item) {
                    if ($item->product) {
                        $item->product->increment('quantity', $item->quantity);
                    }

                    if ($item->bundle_id) {
                        if ($item->bundle && $item->bundle->baseProduct) {
                            $item->bundle->baseProduct->increment('quantity', $item->quantity);
                        }
                        foreach ($item->bundleExtras as $bundleExtra) {
                            if ($bundleExtra->product) {
                                $bundleExtra->product->increment('quantity', $bundleExtra->quantity);
                            }
                        }
                    }
                }

                if ($order->coupon) {
                    $order->coupon->decrement('used_count'); 
                }

                event(new \App\Events\OrderCancelled($order));
            });

            return $this->success([
                'order' => $order,
                'message' => 'Order cancelled. Refund will be processed by admin.'
            ], 'Order cancelled successfully. Refund pending admin approval.');
        } catch (\Exception $e) {
            return $this->error('Failed to cancel order: ' . $e->getMessage(), 500);
        }
    }

    public function updateCustomerDetails(Request $request, $id)
    {
        try {
            $order = \App\Models\Order::findOrFail($id);

            // check ownership
            if ($order->user_id !== request()->user()->id) {
                return $this->error('Unauthorized access', 403);
            }

            // check status
            $allowedStatuses = ['pending', 'processing'];
            if (!in_array(strtolower($order->order_status), $allowedStatuses)) {
                return $this->error('Order cannot be updated at this stage', 400);
            }

            $request->validate([
                'notes' => 'nullable|string',
                'preferred_delivery_date' => 'nullable|date',
                'address' => 'nullable|string',
                'suburb' => 'nullable|string',
                'city' => 'nullable|string',
                'zip_code' => 'nullable|string',
            ]);
            
            DB::transaction(function () use ($order, $request) {
                if ($request->has('notes')) {
                    $order->notes = $request->input('notes');
                }
                
                if ($request->has('preferred_delivery_date')) {
                    $order->preferred_delivery_date = $request->input('preferred_delivery_date');
                }
                
                // If zip_code is provided, validate it
                if ($request->has('zip_code') && $request->input('zip_code')) {
                    $zipCodeActive = \App\Models\ZipCode::where('zip_code', $request->input('zip_code'))
                        ->isDeliverable()
                        ->exists();

                    if (!$zipCodeActive) {
                        // Rollback transaction by throwing exception or returning response
                        throw new \Exception('Sorry, we do not deliver to this Zip Code.');
                    }
                }
                
                // If any address fields are provided, create a new ShippingAddress for this order
                if ($request->has('address') || $request->has('suburb') || $request->has('city') || $request->has('zip_code')) {
                    // Try to use existing address as template or create empty
                    $oldAddress = $order->address;
                    
                    $newAddress = \App\Models\ShippingAddress::create([
                        'user_id' => $order->user_id,
                        'label' => 'Order ' . $order->order_number . ' Address',
                        'full_name' => $oldAddress ? $oldAddress->full_name : $request->user()->name,
                        'phone' => $oldAddress ? $oldAddress->phone : $request->user()->phone,
                        'email' => $oldAddress ? $oldAddress->email : $request->user()->email,
                        'address' => $request->input('address', $oldAddress ? $oldAddress->address : null),
                        'suburb' => $request->input('suburb', $oldAddress ? $oldAddress->suburb : null),
                        'city' => $request->input('city', $oldAddress ? $oldAddress->city : null),
                        'state' => $oldAddress ? $oldAddress->state : null,
                        'country' => $oldAddress ? $oldAddress->country : 'Australia',
                        'zip_code' => $request->input('zip_code', $oldAddress ? $oldAddress->zip_code : null),
                        'is_default' => 0
                    ]);
                    
                    $order->shipping_address_id = $newAddress->id;
                }
                
                $order->save();
            });

            return $this->success([
                'order' => $order->fresh(['address', 'orderItems.product']),
                'message' => 'Order details updated successfully.'
            ], 'Order details updated successfully.');

        } catch (\Exception $e) {
            return $this->error('Failed to update order details: ' . $e->getMessage(), 500);
        }
    }


}