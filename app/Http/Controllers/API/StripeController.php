<?php

namespace App\Http\Controllers\API;

use Exception;
use App\Models\Cart;
use App\Models\Order;
use Stripe\StripeClient;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class StripeController extends Controller
{

    use ApiResponse;

    public function intent(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'payment_method' => 'required|string|max:50',
        ]);

        $user = Auth::guard('api')->user();

        $order = Order::find($request->order_id);
        $orderTotal = $order->total;

        $stripe = new StripeClient(env('STRIPE_SECRET_KEY'));
        $paymentIntent = $stripe->paymentIntents->create([
            'amount' => $orderTotal * 100,
            'currency' => 'usd',
            'metadata' => [
                'order_id' => $order->id,
                'user_id'  => $user->id,
            ],
        ]);
        $order->transaction_id = $paymentIntent->id;
        $order->payment_method = $request->payment_method;
        $order->payment_status = 'pending';
        $order->save();
        Cart::where('user_id', $user->id)->delete();

        return response()->json([
            'client_intent' => $paymentIntent->client_secret,
        ], 200);
    }


    public function handleWebhook(Request $request)
    {
        $user = Auth::guard('api')->user();
        Log::info('✅ Stripe Webhook HIT', [
            'time' => now()->toDateTimeString(),
            'headers' => $request->headers->all(),
        ]);
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret = config('services.stripe.webhook_secret');

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $object = $event->data->object;

        switch ($event->type) {
            case 'payment_intent.succeeded':
                $order = Order::where('transaction_id', $object->id)->first();
                if ($order && $order->payment_status !== 'paid') {
                    $chargeId = $object->charges->data[0]->id ?? null;
                    Cart::where('user_id', $user->id)->delete();
                    $order->update([
                        'payment_status' => 'paid',
                        'charge_id' => $chargeId,
                        'order_status' => 'processing',
                    ]);

                    (new \App\Services\OrderCompletionService())->processLoyaltyPoints($order);
                }
                break;

            case 'payment_intent.payment_failed':
                $order = Order::where('transaction_id', $object->id)->first();
                if ($order) {
                    $order->update(['payment_status' => 'failed']);
                }
                break;

            case 'charge.refunded':
                $charge = $object;
                $order = Order::with('payment')->where('charge_id', $charge->id)->first();
                if ($order) {
                    $refundStatus = $charge->amount_refunded >= $charge->amount ? 'full' : 'partial';
                    
                    $order->update([
                        'payment_status' => $refundStatus === 'full' ? 'refunded' : 'partially_refunded'
                    ]);

                    $payment = $order->payment->sortByDesc('created_at')->first();
                    if ($payment) {
                        $payment->update([
                            'refund_id' => $charge->id, 
                            'refund_amount' => $charge->amount_refunded / 100,
                            'refund_status' => $refundStatus,
                            'refunded_at' => now()
                        ]);
                        
                        event(new \App\Events\PaymentRefunded($payment));
                    }
                }
                break;
        }

        return response()->json(['ok' => true]);
    }
}
