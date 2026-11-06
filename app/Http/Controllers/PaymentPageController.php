<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\BankTransfer;
use App\Models\PaymentMethod;

class PaymentPageController extends Controller
{
    /**
     * Show the custom checkout page.
     * Accessible without authentication - uses session_id for validation
     *
     * @param string $session_id
     * @return \Illuminate\View\View
     */
    public function checkout($session_id)
    {
        $orderData = Cache::get($session_id);

        if (!$orderData) {
            Log::warning('PaymentPageController: Order data not found in cache', ['session_id' => $session_id]);
            return abort(404, 'Order session expired or not found.');
        }

        // Get user if authenticated (optional)
        $user = auth()->user();
        $cachedUserId = $orderData['user_id'];

        Log::info('PaymentPageController: checkout view', [
            'session_id' => $session_id,
            'cached_user_id' => $cachedUserId,
            'web_user_id' => $user ? $user->id : 'guest',
            'is_authenticated' => $user ? 'yes' : 'no',
        ]);

        // If user is authenticated, verify they own this order
        // If not authenticated (guest), that's fine - session_id validates them
        // Note: We don't logout the web user because checkout uses session_id for validation
        // The web auth and API auth are separate concerns
        if ($user && $user->id != $cachedUserId) {
             Log::warning('PaymentPageController: Web user mismatch with order owner - proceeding anyway as session_id is the primary validation', [
                 'web_user' => $user->id,
                 'order_owner' => $cachedUserId
             ]);
             // Don't logout - web auth and checkout session are separate concerns
             // The checkout session_id is what validates the order ownership
        }

        // Get active bank transfer details
        $bankTransfer = BankTransfer::where('status', 1)->first();

        // Get active payment methods
        $paymentMethods = PaymentMethod::where('is_active', true)->get()->pluck('slug')->toArray();

        return view('payment.checkout', [
            'session_id' => $session_id,
            'order_data' => $orderData,
            'application_id' => config('square.application_id'),
            'location_id' => config('square.location_id'),
            'bank_transfer' => $bankTransfer,
            'payment_methods' => $paymentMethods,
        ]);
    }

    /**
     * Process the checkout submission via web route.
     * Accessible without authentication - validates session_id
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function processCheckout(Request $request)
    {
        $sessionId = $request->input('session_id');
        
        // Validate session exists
        $sessionData = Cache::get($sessionId);
        if (!$sessionData) {
            Log::warning('PaymentPageController: Session not found for checkout', ['session_id' => $sessionId]);
            return response()->json(['success' => false, 'message' => 'Session expired or not found.'], 404);
        }
        
        Log::info('PaymentPageController: Processing checkout', [
            'session_id' => $sessionId,
            'user_id' => $sessionData['user_id'],
            'is_authenticated' => auth()->check() ? 'yes' : 'no'
        ]);

        if (str_starts_with($sessionId, 'pending_entry_pkg_')) {
            $purchaseData = Cache::get($sessionId);
            if (!$purchaseData) {
                return response()->json(['success' => false, 'message' => 'Purchase session expired.'], 404);
            }
            
            $packageId = $purchaseData['package_id'];
            $controller = app(\App\Http\Controllers\API\Core\EntryPackageController::class);
            return $controller->buyWithMoney($request, $packageId);
        }
        
    // Proxy the request to OrderController@newstore (default for pending_order_*)
        $orderController = app(\App\Http\Controllers\API\Core\OrderController::class);
        return $orderController->newstore($request);
    }

    /**
     * Display order details for public access (from email links).
     * This page is accessible without authentication.
     *
     * @param int $id
     * @return \Illuminate\View\View
     */
    public function showOrder($id)
    {
        $order = \App\Models\Order::with(['orderItems.product', 'orderItems.giveaway', 'address', 'user'])
            ->find($id);

        if (!$order) {
            \Log::warning('PaymentPageController: Order not found', ['order_id' => $id]);
            abort(404, 'Order not found.');
        }

        \Log::info('PaymentPageController: Order details viewed', [
            'order_id' => $order->id,
            'order_number' => $order->order_number
        ]);

        return view('payment.order-details', compact('order'));
    }
}
