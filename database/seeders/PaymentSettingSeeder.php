<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PaymentSetting;

class PaymentSettingSeeder extends Seeder
{
    public function run(): void
    {
        PaymentSetting::updateOrCreate(['id' => 1], [
            'square_application_id' => env('SQUARE_APPLICATION_ID'),
            'square_location_id'    => env('SQUARE_LOCATION_ID'),
            'square_access_token'   => env('SQUARE_ACCESS_TOKEN'),
            'square_environment'    => strtolower(env('SQUARE_ENVIRONMENT', 'sandbox')),
            'paypal_client_id'      => env('PAYPAL_CLIENT_ID'),
            'paypal_secret'         => env('PAYPAL_SECRET'),
            'paypal_environment'    => strtolower(env('PAYPAL_ENVIRONMENT', 'sandbox')),
            'webhook_signature_key' => env('SQUARE_WEBHOOK_SIGNATURE_KEY'),
        ]);
    }
}
