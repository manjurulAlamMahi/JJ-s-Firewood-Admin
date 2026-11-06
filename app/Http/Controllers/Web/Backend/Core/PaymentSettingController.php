<?php
namespace App\Http\Controllers\Web\Backend\Core;

use App\Http\Controllers\Controller;
use App\Models\PaymentSetting;
use Illuminate\Http\Request;

class PaymentSettingController extends Controller
{
    public function edit()
    {
        $setting = PaymentSetting::current();
        return view('backend.layouts.payment_setting.edit', compact('setting'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'square_application_id' => 'nullable|string|max:255',
            'square_location_id'    => 'nullable|string|max:255',
            'square_environment'    => 'required|in:sandbox,production',
            'paypal_client_id'      => 'nullable|string|max:255',
            'paypal_environment'    => 'required|in:sandbox,production',
        ]);

        $setting = PaymentSetting::current();
        $setting->square_application_id = $request->square_application_id;
        $setting->square_location_id    = $request->square_location_id;
        $setting->square_environment    = $request->square_environment;
        $setting->paypal_client_id      = $request->paypal_client_id;
        $setting->paypal_environment    = $request->paypal_environment;

        // Secrets: only overwrite when a new non-empty value is supplied.
        if ($request->filled('square_access_token'))   { $setting->square_access_token   = $request->square_access_token; }
        if ($request->filled('paypal_secret'))         { $setting->paypal_secret         = $request->paypal_secret; }
        if ($request->filled('webhook_signature_key')) { $setting->webhook_signature_key = $request->webhook_signature_key; }

        $setting->save();
        return back()->with('success', 'Payment settings updated.');
    }
}
