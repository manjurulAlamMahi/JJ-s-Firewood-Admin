<?php

namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use App\Models\ShippingAddress;
use App\Models\ZipCode;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Models\User;

class HomeShippingAddressController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $address = ShippingAddress::where('user_id', $request->user()->id)
            ->where('label', 'home')
            ->latest()
            ->first();

        return $this->success($address, 'Home shipping address retrieved successfully.');
    }

    public function store(Request $request)
    {
        $request->validate([
            'full_name' => 'required|string|max:255',
            'phone' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'address' => 'required|string|max:255',
            'suburb' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'zip_code' => 'nullable|string|max:255',
            'is_default' => 'nullable|boolean',
        ]);

        if ($request->zip_code) {
            $zipCodeExists = ZipCode::where('zip_code', $request->zip_code)
                ->where('status', 1)
                ->exists();

            if (!$zipCodeExists) {
                return $this->error('The provided zip code is not available for delivery.', 422);
            }
        }

        $user = Auth::user();

        if ($user && $user->is_guest) {
            if (User::where('email', $request->email)->where('id', '!=', $user->id)->exists()) {
                return $this->error('This email is already registered. Please login to continue.', 422);
            }

            $randomPassword = Str::random(10);

            $user->update([
                'name' => $request->full_name,
                'email' => $request->email,
                'is_guest' => false,
                'password' => Hash::make($randomPassword),
            ]);

            Mail::raw(
                "Hi {$user->name},\n\nYour guest account has been upgraded to a full account.\n\nLogin Email: {$user->email}\nPassword: {$randomPassword}\n\nPlease change your password after logging in.\n\nThank you!",
                function ($message) use ($user) {
                    $message->to($user->email)
                            ->subject('Your Account Has Been Activated');
                }
            );
        }

        if ($request->is_default) {
            ShippingAddress::where('user_id', $user->id)->update(['is_default' => false]);
        }

        $address = ShippingAddress::updateOrCreate(
            ['user_id' => $user->id, 'label' => 'home'],
            [
                'full_name' => $request->full_name,
                'phone' => $request->phone,
                'email' => $request->email,
                'address' => $request->address,
                'suburb' => $request->suburb,
                'city' => $request->city,
                'state' => $request->state,
                'country' => $request->country,
                'zip_code' => $request->zip_code,
                'is_default' => $request->is_default ?? false,
            ]
        );

        return $this->success($address, 'Home shipping address saved successfully.');
    }

    public function destroy(Request $request, $id)
    {
        $address = ShippingAddress::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->where('label', 'home')
            ->firstOrFail();

        $address->delete();
        return $this->success([], 'Home shipping address deleted successfully.');
    }
}
