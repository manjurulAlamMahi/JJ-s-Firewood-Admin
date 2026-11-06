<?php

namespace App\Http\Controllers\Web\Backend\Core;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class PaymentMethodController extends Controller
{

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = PaymentMethod::query();
            return DataTables::of($data)
                ->addIndexColumn()
                ->make(true);
        }
        return view('backend.layouts.payment_method.index');
    }


    public function updateStatus(Request $request, $id)
    {
        try {
            $paymentMethod = PaymentMethod::findOrFail($id);
            \Log::info('Updating payment method status', ['id' => $id, 'old_status' => $paymentMethod->is_active, 'request_status' => $request->is_active]);

            if ($request->has('is_active')) {
                $paymentMethod->is_active = $request->is_active == 1;
            } else {
                $paymentMethod->is_active = !$paymentMethod->is_active;
            }

            $paymentMethod->save();
            \Log::info('Payment method status updated', ['id' => $id, 'new_status' => $paymentMethod->is_active]);

            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully',
                'is_active' => $paymentMethod->is_active
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status: ' . $e->getMessage()
            ], 500);
        }
    }
}
