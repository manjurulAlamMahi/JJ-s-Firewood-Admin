<?php

namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\DeliveryFollowUp;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryFollowUpController extends Controller
{
    use ApiResponse;

    public function show(Request $request, $orderId)
    {
        $followUp = $this->findFollowUp($request, $orderId);
        if ($followUp instanceof \Illuminate\Http\JsonResponse) {
            return $followUp;
        }

        return $this->success($followUp, 'Delivery follow-up retrieved successfully.');
    }

    public function open(Request $request, $orderId)
    {
        $followUp = $this->findFollowUp($request, $orderId);
        if ($followUp instanceof \Illuminate\Http\JsonResponse) {
            return $followUp;
        }

        $followUp->markOpened();

        return $this->success($followUp->fresh(), 'Delivery follow-up marked as opened.');
    }

    public function dismiss(Request $request, $orderId)
    {
        $followUp = $this->findFollowUp($request, $orderId);
        if ($followUp instanceof \Illuminate\Http\JsonResponse) {
            return $followUp;
        }

        if ($followUp->isTerminal()) {
            return $this->badRequest('This follow-up has already been handled.');
        }

        $followUp->markDismissed();

        return $this->success($followUp->fresh(), 'Delivery follow-up dismissed.');
    }

    public function feedback(Request $request, $orderId)
    {
        $followUp = $this->findFollowUp($request, $orderId);
        if ($followUp instanceof \Illuminate\Http\JsonResponse) {
            return $followUp;
        }

        if ($followUp->isTerminal()) {
            return $this->badRequest('This follow-up has already been handled.');
        }

        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ]);

        $followUp->markCompleted('feedback', [
            'feedback_rating' => $request->rating,
            'feedback_comment' => $request->comment,
        ]);

        return $this->success($followUp->fresh(), 'Thanks for your feedback.');
    }

    /**
     * Submits a product review and closes out the order's follow-up in one
     * call, so the review-popup shown after delivery only needs one request.
     */
    public function review(Request $request, $orderId)
    {
        $followUp = $this->findFollowUp($request, $orderId);
        if ($followUp instanceof \Illuminate\Http\JsonResponse) {
            return $followUp;
        }

        if ($followUp->isTerminal()) {
            return $this->badRequest('This follow-up has already been handled.');
        }

        $request->validate([
            'product_id' => 'required|exists:products,id',
            'rating' => 'required|numeric|min:1|max:5',
            'comment' => 'nullable|string',
        ]);

        $user = $request->user();

        $existingReview = Review::where('user_id', $user->id)
            ->where('product_id', $request->product_id)
            ->first();

        if ($existingReview) {
            return $this->badRequest('You have already reviewed this product.');
        }

        $review = DB::transaction(function () use ($request, $user, $followUp) {
            $review = Review::create([
                'user_id' => $user->id,
                'product_id' => $request->product_id,
                'rating' => $request->rating,
                'comment' => $request->comment,
                'status' => 'pending',
            ]);

            $followUp->markCompleted('review');

            return $review;
        });

        return $this->success([
            'review' => $review,
            'follow_up' => $followUp->fresh(),
        ], 'Review submitted successfully.');
    }

    /**
     * Repopulates the customer's cart with this order's items (skipping
     * giveaway line items, which are one-time prizes) so they land on
     * checkout pre-filled rather than a new order being placed silently.
     */
    public function reorder(Request $request, $orderId)
    {
        $followUp = $this->findFollowUp($request, $orderId);
        if ($followUp instanceof \Illuminate\Http\JsonResponse) {
            return $followUp;
        }

        if ($followUp->isTerminal()) {
            return $this->badRequest('This follow-up has already been handled.');
        }

        $user = $request->user();
        $order = $followUp->order()->with('orderItems')->first();

        $added = [];
        $skipped = [];

        DB::transaction(function () use ($order, $user, &$added, &$skipped) {
            foreach ($order->orderItems as $item) {
                if (!$item->product_id) {
                    $skipped[] = ['product_id' => null, 'reason' => 'not_reorderable'];
                    continue;
                }

                $product = Product::lockForUpdate()->find($item->product_id);
                if (!$product || (int) $product->status !== 1) {
                    $skipped[] = ['product_id' => $item->product_id, 'reason' => 'unavailable'];
                    continue;
                }

                $existing = Cart::where('user_id', $user->id)
                    ->where('product_id', $product->id)
                    ->first();

                $newQty = ($existing->quantity ?? 0) + $item->quantity;

                Cart::updateOrCreate(
                    ['user_id' => $user->id, 'product_id' => $product->id],
                    [
                        'quantity' => $newQty,
                        'price' => $product->numeric_price,
                        'subtotal' => $newQty * $product->numeric_price,
                        'cart_reminder_sent_at' => null,
                    ]
                );

                $added[] = $product->id;
            }
        });

        $followUp->markCompleted('reorder');

        return $this->success([
            'added_product_ids' => $added,
            'skipped_items' => $skipped,
            'follow_up' => $followUp->fresh(),
        ], 'Order items added to your cart.');
    }

    private function findFollowUp(Request $request, $orderId)
    {
        $user = $request->user();
        if (!$user) {
            return $this->unauthorized();
        }

        $order = Order::where('id', $orderId)->where('user_id', $user->id)->first();
        if (!$order) {
            return $this->error('Order not found.', 404);
        }

        $followUp = DeliveryFollowUp::where('order_id', $order->id)->first();
        if (!$followUp) {
            return $this->error('No delivery follow-up available for this order yet.', 404);
        }

        return $followUp;
    }
}
