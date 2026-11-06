<?php

namespace App\Http\Controllers\Web\Backend\Core;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Exception;
use App\Traits\ApiResponse;
use App\Models\CouponUsage;

class CouponController extends Controller
{
    use ApiResponse;

    public function getData()
    {
        $coupons = Coupon::withCount('usages')->latest();

        return datatables()->eloquent($coupons)
            ->addIndexColumn()
            ->addColumn('usage_info', function ($coupon) {
                return $coupon->used_count . '/' . ($coupon->usage_limit ?? '∞');
            })
            ->addColumn('total_usage', function ($coupon) {
                return $coupon->usages_count;
            })
            ->addColumn('is_active', function ($coupon) {
                $checked = $coupon->is_active ? 'checked' : '';
                return '<label class="switch">
                        <input type="checkbox" class="toggle-status" data-id="' . $coupon->id . '" ' . $checked . '>
                        <span class="slider"></span>
                    </label>';
            })
            ->rawColumns(['usage_info', 'is_active'])
            ->toJson();
    }



    public function applyCoupon(Request $request)
    {
        $request->validate([
            'coupon_code' => 'required|string',
            'user_id' => 'required|integer|exists:users,id',
            'order_total' => 'required|numeric|min:0'
        ]);

        $coupon = Coupon::where('code', $request->coupon_code)->first();

        if (!$coupon) {
            return back()->with('error', 'Invalid coupon code.');
        }

        if (!$coupon->is_active) {
            return back()->with('error', 'Coupon is inactive.');
        }

        if ($coupon->usage_limit && $coupon->used_count >= $coupon->usage_limit) {
            return back()->with('error', 'Coupon usage limit exceeded.');
        }

        if ($coupon->type == 'percentage') {
            $discount = ($request->order_total * $coupon->value) / 100;
            if ($coupon->max_discount && $discount > $coupon->max_discount) {
                $discount = $coupon->max_discount;
            }
        } else {
            $discount = $coupon->value;
        }


        $couponUsage = new CouponUsage();
        $couponUsage->user_id = $request->user_id;
        $couponUsage->coupon_id = $coupon->id;
        $couponUsage->order_id = $request->order_id ?? null;
        $couponUsage->discount_amount = $discount;
        $couponUsage->order_total = $request->order_total;
        $couponUsage->save();

        $coupon->increment('used_count');

        return redirect()->back()->with('success', 'Coupon applied successfully.');
    }


    public function index()
    {
        $coupons = Coupon::all();
        return view('backend.layouts.coupon.index', compact('coupons'));
    }

    public function create()
    {
        return view('backend.layouts.coupon.create');
    }


    public function store(Request $request)
    {
        try {
            $request->validate([
                'code' => 'required|string|max:50|unique:coupons,code',
                'type' => 'required|in:percentage,fixed',
                'value' => 'required|numeric|min:0',
                'min_order_amount' => 'nullable|numeric|min:0',
                'max_discount' => 'nullable|numeric|min:0',
                'usage_limit' => 'nullable|integer|min:1',
                'start_date' =>  'nullable|date',
                'end_date' => 'nullable|date|after:start_date',
                'is_active' => 'boolean'
            ]);

            if ($request->type === 'percentage' && $request->value > 100) {
                return back()->with('error', 'Percentage value should be less than or equal to 100.');
            }

            $coupon = new Coupon();
            $coupon->code = strtoupper($request->code);
            $coupon->type = $request->type;
            $coupon->value = $request->value;
            $coupon->min_order_amount = $request->min_order_amount ?? 0;
            $coupon->max_discount = $request->max_discount;
            $coupon->usage_limit = $request->usage_limit;
            $coupon->used_count = 0;
            $coupon->start_date = $request->start_date;
            $coupon->end_date = $request->end_date;
            $coupon->is_active = $request->is_active ?? true;
            $coupon->save();

            return redirect()->route('coupon.index')->with('success', 'Coupon created successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function edit($id)
    {
        $coupon = Coupon::findOrFail($id);
        return view('backend.layouts.coupon.edit', compact('coupon'));
    }

    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'code' => 'required|string|max:50|unique:coupons,code,' . $id,
                'type' => 'required|in:percentage,fixed',
                'value' => 'required|numeric|min:0',
                'min_order_amount' => 'nullable|numeric|min:0',
                'max_discount' => 'nullable|numeric|min:0',
                'usage_limit' => 'nullable|integer|min:1',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'is_active' => 'boolean'
            ]);

            if ($request->type === 'percentage' && $request->value > 100) {
                return back()->with('error', 'Percentage value should be less than or equal to 100.');
            }

            $coupon = Coupon::findOrFail($id);
            $coupon->code = strtoupper($request->code);
            $coupon->type = $request->type;
            $coupon->value = $request->value;
            $coupon->min_order_amount = $request->min_order_amount ?? 0;
            $coupon->max_discount = $request->max_discount;
            $coupon->usage_limit = $request->usage_limit;
            $coupon->start_date = $request->start_date;
            $coupon->end_date = $request->end_date;
            $coupon->is_active = $request->is_active ?? true;
            $coupon->save();

            return redirect()->route('coupon.index')->with('success', 'Coupon updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $coupon = Coupon::findOrFail($id);

            if ($coupon->used_count > 0) {
                return  back()->with('error', 'Cannot delete a coupon that has been used.');
            }

            $coupon->delete();

            return redirect()->route('coupon.index')->with('success', 'Coupon deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }




    public function updateStatus(Request $request, $id)
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->is_active = $request->status;
        $coupon->save();

        return $this->success([], 'Coupon status updated successfully.');
    }



    public function generateCode()
    {
        try {
            do {
                $code = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8));
            } while (Coupon::where('code', $code)->exists());

            return $this->success(['code' => $code], 'Coupon code generated successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|exists:coupons,id'
        ]);

        $usedCoupons = Coupon::whereIn('id', $request->ids)
            ->where('used_count', '>', 0)
            ->count();

        if ($usedCoupons > 0) {
            return back()->with('error', 'Some coupons have been used and cannot be deleted');
        }

        Coupon::whereIn('id', $request->ids)->delete();

        return back()->with('success', 'Selected coupons deleted successfully');
    }
}
