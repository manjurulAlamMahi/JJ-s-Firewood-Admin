<?php

namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Traits\ApiResponse;

class CouponController extends Controller
{
    use ApiResponse;


    public function activeCoupons()
    {
        $coupons = Coupon::active()->get();

        $result = $coupons->map(function($coupon){
            return [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'type' => $coupon->type,
                'value' => $coupon->value,
                'min_order_amount' => $coupon->min_order_amount,
                'max_discount' => $coupon->max_discount,
                'usage_limit' => $coupon->usage_limit,
                'used_count' => $coupon->used_count,
                'start_date' => $coupon->start_date,
                'end_date' => $coupon->end_date,
                'is_active' => $coupon->is_active,
            ];
        });

        return $this->success($result, 'Active coupons fetched successfully.');
    }


    public function applyCoupon(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'coupon_code' => 'required|string',
        ]);

        $coupon = Coupon::where('code', $request->coupon_code)->first();

        if (!$coupon || !$coupon->isValid($user->id)) {
            return $this->error('Coupon is invalid, already used, or cannot be applied.');
        }

        if ($coupon->type === 'percentage') {
            $discount = $coupon->value;
        } else {
            $discount = $coupon->value;
        }

        return $this->success([
            'id' => $coupon->id,
            'code' => $coupon->code,
            'type' => $coupon->type,
            'value' => $coupon->value,
            'discount_amount' => $discount,
            'min_order_amount' => $coupon->min_order_amount,
            'max_discount' => $coupon->max_discount,
            'usage_limit' => $coupon->usage_limit,
            'used_count' => $coupon->used_count,
            'start_date' => $coupon->start_date,
            'end_date' => $coupon->end_date,
            'is_active' => $coupon->is_active,
        ], 'Coupon applied successfully.');
    }






    public function generateCode()
    {
        do {
            $code = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8));
        } while (Coupon::where('code', $code)->exists());

        return $this->success(['code' => $code], 'Coupon code generated successfully.');
    }
}
