<?php

namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReorderSuggestionController extends Controller
{
    use ApiResponse;

    /**
     * "Buy it again" suggestions: this customer's most-ordered products
     * across their order history, falling back to their last order's
     * items when they don't have enough orders (2+) for "most-ordered"
     * to mean anything beyond "their one order".
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $distinctOrderCount = Order::where('user_id', $user->id)
            ->where('order_status', '!=', 'cancelled')
            ->count();

        if ($distinctOrderCount === 0) {
            return $this->success([], 'No order history yet.');
        }

        if ($distinctOrderCount < 2) {
            $lastOrder = Order::where('user_id', $user->id)
                ->where('order_status', '!=', 'cancelled')
                ->latest()
                ->first();

            $productIds = $lastOrder->orderItems()
                ->whereNotNull('product_id')
                ->orderByDesc('quantity')
                ->limit(5)
                ->pluck('product_id')
                ->all();
        } else {
            $productIds = OrderItem::query()
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.user_id', $user->id)
                ->where('orders.order_status', '!=', 'cancelled')
                ->whereNotNull('order_items.product_id')
                ->select('order_items.product_id', DB::raw('SUM(order_items.quantity) as total_quantity'))
                ->groupBy('order_items.product_id')
                ->orderByDesc('total_quantity')
                ->limit(5)
                ->pluck('order_items.product_id')
                ->all();
        }

        if (empty($productIds)) {
            return $this->success([], 'No order history yet.');
        }

        $quantityByProduct = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.user_id', $user->id)
            ->where('orders.order_status', '!=', 'cancelled')
            ->whereIn('order_items.product_id', $productIds)
            ->select('order_items.product_id', DB::raw('SUM(order_items.quantity) as total_quantity'))
            ->groupBy('order_items.product_id')
            ->pluck('total_quantity', 'order_items.product_id');

        $products = Product::whereIn('id', $productIds)
            ->where('status', 1)
            ->where('quantity', '>', 0)
            ->get()
            ->keyBy('id');

        $data = collect($productIds)
            ->map(fn ($id) => $products->get($id))
            ->filter()
            ->map(fn (Product $product) => [
                'product_id' => $product->id,
                'name' => $product->name,
                'image' => $product->getRawOriginal('image') ? $product->image : null,
                'price' => $product->price,
                'total_quantity_ordered' => (float) ($quantityByProduct[$product->id] ?? 0),
            ])
            ->values();

        return $this->success($data, 'Reorder suggestions retrieved successfully.');
    }
}
