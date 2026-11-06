<?php
namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use App\Services\PayPalService;
use App\Services\OrderCompletionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PayPalController extends Controller
{
    use ApiResponse;

    public function capture(Request $request)
    {
        $request->validate(['session_id' => 'required|string']);
        $sessionId = $request->session_id;

        $paypalOrderId = Cache::get($sessionId . '_paypal_order_id');
        if (!$paypalOrderId || !Cache::has($sessionId)) {
            return $this->error('Payment session expired. Please try again.', 410);
        }

        $result = (new PayPalService())->captureOrder($paypalOrderId);
        if (!$result['success']) {
            return $this->error($result['message'] ?? 'PayPal payment was not completed.', 422);
        }

        $cached = Cache::get($sessionId);
        $order = (new OrderCompletionService())->completeOrder(
            $sessionId,
            $paypalOrderId,
            $result['capture_id'],
            'COMPLETED',
            $cached['total'],
            'AUD',
            ['source_id' => 'paypal']
        );

        Cache::forget($sessionId . '_paypal_order_id');
        Log::info('PayPal order captured', ['order_id' => $order->id, 'capture_id' => $result['capture_id']]);

        return $this->success(['order' => $order, 'payment_status' => 'paid'], 'Payment successful!');
    }
}
