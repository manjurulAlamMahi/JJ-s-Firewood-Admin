<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PaymentSetting;
use App\Models\PaymentMethod;
use App\Models\BankTransfer;

class PaymentConfigController extends Controller
{
    public function show()
    {
        $s = PaymentSetting::current();
        $active = PaymentMethod::where('is_active', true)->pluck('slug')->all();
        $has = fn ($slug) => in_array($slug, $active, true);

        // Admin-panel values win; fall back to .env like SquarePaymentService / PayPalService do.
        $squareAppId    = $s->square_application_id ?: config('square.application_id');
        $squareLocation = $s->square_location_id ?: config('square.location_id');
        $squareEnv      = $s->square_application_id ? $s->square_environment : config('square.environment');
        $paypalClientId = $s->paypal_client_id ?: config('paypal.client_id');
        $paypalSecret   = $s->paypal_secret ?: config('paypal.secret');
        $paypalEnv      = $s->paypal_client_id ? $s->paypal_environment : config('paypal.environment');

        $squareReady = $has('square') && $squareAppId && $squareLocation;
        $paypalReady = $has('paypal') && $paypalClientId && $paypalSecret;

        // Bank transfer instructions shown in-app when the buyer picks Bank.
        // Non-secret, admin-managed details from the active BankTransfer row.
        $bank = $has('bank_transfer') ? BankTransfer::where('status', 1)->first() : null;

        return response()->json([
            'success' => true,
            'data' => [
                'square' => [
                    'application_id' => $squareAppId,
                    'location_id'    => $squareLocation,
                    'environment'    => strtolower($squareEnv ?: 'sandbox'),
                ],
                'paypal' => [
                    'enabled'     => (bool) $paypalReady,
                    'environment' => strtolower($paypalEnv ?: 'sandbox'),
                ],
                'methods' => [
                    'card'             => (bool) $squareReady,
                    'apple_pay'        => (bool) $squareReady,
                    'google_pay'       => (bool) $squareReady,
                    'paypal'           => (bool) $paypalReady,
                    'bank_transfer'    => $has('bank_transfer'),
                    'cash_on_delivery' => $has('cash_on_delivery'),
                ],
                'bank' => $bank ? [
                    'account_holder_name' => $bank->account_holder_name,
                    'account_number'      => $bank->account_number,
                    'bank_name'           => $bank->bank_name,
                    'branch_name'         => $bank->branch_name,
                    'note'                => $bank->note,
                ] : null,
            ],
        ]);
    }
}
