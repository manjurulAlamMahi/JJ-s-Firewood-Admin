<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use App\Models\Order;
use App\Models\BankTransfer;

class CustomGlobalAPIController extends Controller
{
    use ApiResponse;

    public function getOrderFullInfo($order_number)
    {
        try {
            $order = Order::with([
                'orderItems.product.category',
                'orderItems.giveaway',
                'address',
                'coupon',
                'deliveryType',
            ])->where('order_number', $order_number)->first();

            if (!$order) {
                return $this->error('Order not found.', 404);
            }

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
                $catName = ($item->product && $item->product->category)
                    ? $item->product->category->name
                    : ($item->giveaway ? 'Giveaway' : 'Uncategorized');
                $categoriesQty[$catName] = ($categoriesQty[$catName] ?? 0) + $item->quantity;
            }

            $categories = [];
            foreach ($categoriesQty as $name => $qty) {
                $categories[] = ['category_name' => $name, 'add_product' => $qty];
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
                    'subtotal'   => $item->subtotal,
                    'category'   => $item->product->category->name ?? ($item->giveaway ? 'Giveaway' : null),
                    'image'      => $image,
                ];
            });

            $data = [
                'order_id'                => $order->id,
                'order_number'            => $order->order_number,
                'order_status'            => $order->order_status,
                'payment_status'          => $order->payment_status,
                'payment_method'          => $order->payment_method,
                'subtotal'                => $order->subtotal,
                'delivery_fee'            => $order->delivery_fee,
                'discount'                => $order->discount,
                'tax'                     => $order->tax,
                'total_amount'            => $order->total,
                'payment_link'            => $order->payment_link,
                'payment_link_expired'    => $order->payment_link_expires_at
                    ? now()->gt($order->payment_link_expires_at)
                    : false,
                'preferred_delivery_date' => $order->preferred_delivery_date,
                'notes'                   => $order->notes,
                'delivery_type'           => $order->deliveryType ? [
                    'name' => $order->deliveryType->name,
                    'fee'  => $order->deliveryType->fee,
                    'days' => $order->deliveryType->days,
                ] : null,
                'coupon'                  => $order->coupon ? [
                    'code'     => $order->coupon->code,
                    'type'     => $order->coupon->type,
                    'value'    => $order->coupon->value,
                    'discount' => $order->discount,
                ] : null,
                'address'                 => $address,
                'categories'              => $categories,
                'products'                => $products,
                'bank_transfer_details'   => $order->payment_method === 'bank_transfer'
                    ? BankTransfer::where('status', 1)->first()
                    : null,
                'created_at'              => $order->created_at,
                'updated_at'              => $order->updated_at,
            ];

            return $this->success($data, 'Order retrieved successfully.');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve order: ' . $e->getMessage(), 500);
        }
    }
}
