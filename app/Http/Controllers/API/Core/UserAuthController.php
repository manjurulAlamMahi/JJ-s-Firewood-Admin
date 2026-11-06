<?php

namespace App\Http\Controllers\API\Core;

use Carbon\Carbon;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\ReferralUsage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Laravel\Socialite\Facades\Socialite;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Illuminate\Support\Facades\Validator;


class UserAuthController extends Controller
{
    use ApiResponse;


    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|string|email',
            'password' => 'required|string',
            'referral_code' => 'nullable|string|exists:users,referral_code',
        ]);

        if ($validator->fails()) {
            return $this->validationError(
                $validator->errors()->toArray(),
                'Validation failed',
                422
            );
        }

        $credentials = $request->only('email', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return $this->unauthorized('Wrong email or password.', 401);
        }

        $user = auth()->user();
        if ($user->email_verified_at === null) {
            $this->sendOtp($user);

            return $this->badRequest('Email not verified. A new OTP has been sent to your email.', [
                'token' => $token,
                'user'  => $this->formatUser($user),
                'referral_bonus' => 0,
                'referrer_name' => null,
            ], 200);
        }


        $referrerBonus = 0;
        $referrerUser = null;

        if ($request->referral_code) {
            $referrer = User::where('referral_code', $request->referral_code)->first();

            if ($referrer && $referrer->id !== $user->id) {
                $alreadyUsed = ReferralUsage::where('referred_user_id', $user->id)->exists();

                if (!$alreadyUsed) {
                    $bonus = 100;

                    $referrer->loyalty_points += $bonus;
                    $referrer->save();

                    $referrerBonus = $bonus;
                    $referrerUser = $referrer->name;

                    ReferralUsage::create([
                        'referrer_user_id' => $referrer->id,
                        'referred_user_id' => $user->id,
                        'referral_code' => $request->referral_code,
                        'bonus_given' => true,
                        'referral_bonus' => $bonus,
                        'used_at' => now(),
                    ]);
                }
            }
        }

        return $this->success([
            'token' => $token,
            'user' => $user,
            'referral_bonus' => $referrerBonus,
            'referrer_name' => $referrerUser,
        ], 'Login Successful');
    }



    public function UserRegister(Request $request)
    {
        return $this->registerUser($request, 'user');
    }

    protected function registerUser(Request $request, $role)
    {
        if (strtolower($role) === 'admin') {
            return $this->badRequest('Admin registration is not allowed.', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:6',
            'referral_code' => 'nullable|string|exists:users,referral_code',
            'phone'    => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray(), 'Validation failed', 422);
        }

        DB::beginTransaction();
        try {
            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => $request->password,
                'phone'    => $request->phone,
            ]);

            $user->assignRole($role);
            
            $referrerBonus = 0;
            $referrerUser = null;

            if ($request->referral_code) {
                $referrer = User::where('referral_code', $request->referral_code)->first();

                if ($referrer && $referrer->id !== $user->id) {
                    $alreadyUsed = ReferralUsage::where('referred_user_id', $user->id)->exists();

                    if (!$alreadyUsed) {
                        $bonus = 100;

                        $referrer->loyalty_points += $bonus;
                        $referrer->save();

                        $referrerBonus = $bonus;
                        $referrerUser = $referrer->name;

                        ReferralUsage::create([
                            'referrer_user_id' => $referrer->id,
                            'referred_user_id' => $user->id,
                            'referral_code' => $request->referral_code,
                            'bonus_given' => true,
                            'referral_bonus' => $bonus,
                            'used_at' => now(),
                        ]);
                    }
                }
            }
            
            $this->sendOtp($user);
            
            \App\Services\WooCommerceSyncService::syncCustomerToAppUser($user);
            
            DB::commit();

            $token = JWTAuth::fromUser($user);

            return $this->success([
                'token' => $token,
                'user'  => $user,
                'referral_bonus' => $referrerBonus,
                'referrer_name' => $referrerUser,
            ], 'Registration successful. Please verify your email with the OTP sent to your email.', 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Registration failed: ' . $e->getMessage(), 500);
        }
    }

    public function verifyEmail(Request $request)
    {
        $user = auth()->user();
        if (!$user) return $this->unauthorized();

        $validator = Validator::make($request->all(), [
            'otp'   => 'required|numeric|digits:4',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray(), 'Validation failed', 422);
        }

        try {
            if ($user->otp != $request->otp) {
                return $this->badRequest('Invalid OTP.', [], 400);
            }

            if ($user->otp_created_at && now()->gt(Carbon::parse($user->otp_created_at)->addMinutes(15))) {
                return $this->badRequest('OTP has expired.', [], 400);
            }

            $user->email_verified_at = now();
            $user->otp = null;
            $user->otp_created_at = null;
            $user->save();

            $token = JWTAuth::fromUser($user);

            return $this->success([
                'token' => $token,
                'user' => $user,
                'referral_bonus' => 0, 
                'referrer_name' => null,
            ], 'Email Verified Successfully');
        } catch (\Throwable $e) {
            return $this->error('Email verification failed: ' . $e->getMessage(), 500);
        }
    }


    public function guestLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray(), 'Validation failed', 422);
        }

        try {
            $deviceId = trim((string) $request->device_id);

            // SECURITY: Reject obviously-bad device_id values. Real device
            // identifiers (iOS IDFV is 36 chars, Android ANDROID_ID is 16
            // hex chars, Firebase Installation ID is 22 chars) are long
            // and high-entropy. If a buggy mobile-app build sends a
            // constant placeholder ("unknown", "00000000-...", empty,
            // etc.) the FIRST user with that value becomes a shared
            // guest account that every subsequent fresh install logs into
            // — exposing that user's name/phone/address/orders to
            // strangers. This guard stops that at the source.
            // $lower = strtolower($deviceId);
            // $knownBadDeviceIds = [
            //     '',
            //     '0',
            //     '00000000-0000-0000-0000-000000000000',
            //     'null',
            //     'undefined',
            //     'unknown',
            //     'default',
            //     'none',
            //     '02:00:00:00:00:00',  // Android 6+ MAC fallback
            //     '9774d56d682e549c',   // famously-duplicated ANDROID_ID
            //     'ffffffffffffffff',
            //     '0000000000000000',
            //     'android',
            //     'ios',
            //     'simulator',
            //     'emulator',
            //     'generic',
            //     'test',
            //     '1234567890123456',
            // ];
            // if (strlen($deviceId) < 1 || in_array($lower, $knownBadDeviceIds, true)) {
            //     \Log::warning('Guest login rejected: suspicious device_id', [
            //         'device_id_preview' => substr($deviceId, 0, 32),
            //         'device_id_length'  => strlen($deviceId),
            //         'ip'                => $request->ip(),
            //         'agent'             => $request->userAgent(),
            //     ]);
            //     return $this->badRequest(
            //         'Could not identify this device. Please update the app to the latest version and try again.',
            //         [],
            //         400
            //     );
            // }

            $user = User::where('device_id', $deviceId)
                ->where('is_guest', true)
                ->first();

            if (!$user) {
                $registeredUser = User::where('device_id', $deviceId)
                    ->where('is_guest', false)
                    ->first();
                    
                if ($registeredUser) {
                    $registeredUser->device_id = null;
                    $registeredUser->save();
                }
                
                $lastGuest = User::where('name', 'like', 'Guest_%')
                    ->orderBy('id', 'desc')
                    ->first();

                $guestNumber = $lastGuest ? ((int) str_replace('Guest_', '', $lastGuest->name)) + 1 : 1;
                $guestName = 'Guest_' . $guestNumber;

                $user = User::create([
                    'name' => $guestName,
                    'device_id' => $deviceId,
                    'is_guest' => true,
                ]);
            }

            $token = JWTAuth::fromUser($user);

            return $this->success([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'is_guest' => $user->is_guest,
                    'device_id' => $user->device_id,
                ],
                'token' => $token,
            ], 'Guest login successful.', 200);
        } catch (\Throwable $e) {
            return $this->error('Something went wrong: ' . $e->getMessage(), 500);
        }
    }


    public function forgetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray(), 'Validation failed', 422);
        }

        try {
            $user = User::where('email', $request->email)->first();
            $this->clearPasswordResetCache();
            $this->sendOtp($user);

            Cache::put('password_reset_user_id', $user->id, now()->addMinutes(15));
            Cache::put('password_reset_otp', $user->otp, now()->addMinutes(15));
            Cache::put('password_reset_email', $user->email, now()->addMinutes(15));

            return $this->success([], 'OTP sent successfully. Please check your email.', 200);
        } catch (\Throwable $e) {
            return $this->error('Failed to send OTP: ' . $e->getMessage(), 500);
        }
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'otp' => 'required|numeric|digits:4',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray(), 'Validation failed', 422);
        }

        $userId = Cache::get('password_reset_user_id');
        $cachedOtp = Cache::get('password_reset_otp');

        if (!$userId || !$cachedOtp) {
            return $this->badRequest('Please request OTP first.', [], 400);
        }

        if ($request->otp != $cachedOtp) {
            return $this->badRequest('Invalid OTP.', [], 400);
        }

        $user = User::find($userId);
        if (!$user) return $this->error('User not found.', 404);

        if ($user->otp_created_at && now()->gt(Carbon::parse($user->otp_created_at)->addMinutes(15))) {
            return $this->badRequest('OTP has expired.', [], 400);
        }

        Cache::put('password_reset_verified', true, now()->addMinutes(2));
        Cache::put('verified_user_id', $userId, now()->addMinutes(2));

        return $this->success([], 'OTP verified successfully. You can now reset your password.', 200);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray(), 'Validation failed', 422);
        }

        $userId = Cache::get('verified_user_id');
        $isVerified = Cache::get('password_reset_verified');

        if (!$userId || !$isVerified) {
            return $this->badRequest('Please verify OTP first.', [], 400);
        }

        $user = User::find($userId);
        if (!$user) return $this->error('User not found.', 404);

        $user->password = $request->password;
        $user->otp = null;
        $user->otp_created_at = null;
        $user->save();

        $this->clearPasswordResetCache();

        return $this->success([], 'Password reset successfully. Please login with your new password.', 200);
    }

    public function resendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray(), 'Validation failed', 422);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user) return $this->error('User not found.', 404);

        try {
            $this->sendOtp($user);
            Cache::put('password_reset_otp', $user->otp, now()->addMinutes(15));

            return $this->success([], 'OTP resent successfully. Please check your email.', 200);
        } catch (\Throwable $e) {
            return $this->error('Failed to resend OTP: ' . $e->getMessage(), 500);
        }
    }


    public function logout(Request $request)
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return $this->success([], 'Successfully logged out.', 200);
        } catch (\Exception $e) {
            return $this->error('Logout failed: ' . $e->getMessage(), 500);
        }
    }


    public function showUser()
    {
        $user = auth()->user();
        if (!$user) return $this->unauthorized();

        return $this->success(['user' => $user], 'User found', 200);
    }




    public function updateProfile(Request $request)
    {
        $user = auth()->user();
        if (!$user) return $this->unauthorized();

        $booleanFields = ['messages', 'notification'];
        foreach ($booleanFields as $field) {
            if ($request->has($field)) {
                $request->merge([
                    $field => filter_var($request->input($field), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                ]);
            }
        }

        $validator = Validator::make($request->all(), [
            'name'   => 'nullable|string|max:255',
            'email'  => ['nullable', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone'  => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($user->id)],
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray(), 'Validation failed', 422);
        }

        try {
            if ($request->hasFile('avatar')) {
                $file = $request->file('avatar');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $path = public_path('uploads/avatars');

                if (!file_exists($path)) mkdir($path, 0755, true);
                $file->move($path, $filename);

                if ($user->avatar && file_exists($path . '/' . $user->avatar)) {
                    @unlink($path . '/' . $user->avatar);
                }

                $user->avatar = $filename;
            }

            if ($user->is_guest) {
                $randomPassword = Str::random(10);

                if ($request->filled('name'))  $user->name  = $request->name;
                if ($request->filled('email')) $user->email = $request->email;
                if ($request->filled('phone')) $user->phone = $request->phone;

                $user->is_guest = false;
                $user->password = $randomPassword; 
                
                $user->device_id = null; 

                Mail::raw(
                    "Hi {$user->name},\n\nYour guest account has been upgraded to a full account.\n\nLogin Email: {$user->email}\nPassword: {$randomPassword}\n\nPlease change your password after logging in.\n\nThank you!",
                    function ($message) use ($user) {
                        $message->to($user->email)
                            ->subject('Your Account Has Been Activated');
                    }
                );

                $message = 'Guest account upgraded successfully. Login details sent to your email.';
            } else {
                foreach (['name', 'email', 'phone', 'messages', 'notification'] as $field) {
                    if ($request->has($field)) {
                        $user->$field = $request->input($field);
                    }
                }

                $message = 'Profile updated successfully.';
            }

            $user->save();

            return $this->success(
                ['user' => $user],
                $message,
                200
            );
        } catch (\Throwable $e) {
            return $this->error('Profile update failed: ' . $e->getMessage(), 500);
        }
    }

    public function changePassword(Request $request)
    {
        $user = auth()->user();
        if (!$user) return $this->unauthorized();

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray(), 'Validation failed', 422);
        }

        if (!Hash::check($request->current_password, $user->password)) {
            return $this->badRequest('Current password is incorrect.', [], 400);
        }

        try {
            $user->password = $request->new_password;
            $user->save();

            return $this->success([], 'Password changed successfully.', 200);
        } catch (\Throwable $e) {
            return $this->error('Password change failed: ' . $e->getMessage(), 500);
        }
    }


    protected function sendOtp(User $user)
    {
        $otp = rand(1000, 9999);

        $user->update([
            'otp' => $otp,
            'otp_created_at' => now(),
        ]);

        Mail::to($user->email)->send(new \App\Mail\OtpMail($otp, $user->name));
    }

    protected function clearPasswordResetCache()
    {
        $keys = [
            'password_reset_user_id',
            'password_reset_otp',
            'password_reset_email',
            'password_reset_verified',
            'verified_user_id',
        ];
        foreach ($keys as $key) Cache::forget($key);
    }



    public function fmstoreFcmToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 'Error in Validation', 422);
        }

        $user = Auth::guard('api')->user();

        if (!$user) {
            return $this->unauthorized('Authentication required to store FCM token.', 401);
        }

        User::where('fcm_token', $request->token)
            ->where('id', '!=', $user->id)
            ->update(['fcm_token' => null]);

        $user->update([
            'fcm_token' => $request->token,
        ]);

        $response = [
            'token' => $user->fcm_token,
        ];

        return $this->success($response, 'FCM token stored successfully', 200);
    }


    public function fmdeleteFcmToken(Request $request)
    {
        $user = Auth::guard('api')->user();

        if (!$user) {
            return $this->unauthorized('Authentication required to clear FCM token.', 401);
        }

        $user->update([
            'fcm_token' => null,
        ]);

        return $this->success([], 'Notification stop successfully', 200);
    }

    public function googleLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'referral_code' => 'nullable|string|exists:users,referral_code',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray(), 'Validation failed', 422);
        }

        try {
            if (str_starts_with($request->token, 'ya29.')) {
                $response = \Illuminate\Support\Facades\Http::get('https://www.googleapis.com/oauth2/v3/userinfo', [
                    'access_token' => $request->token,
                ]);

                if ($response->failed()) {
                    return $this->error('Google Access Token verification failed: ' . ($response->json()['error_description'] ?? 'Invalid token'), 401);
                }

                $userData = $response->json();
                $email = $userData['email'] ?? null;
                $name = $userData['name'] ?? null;
            } else {
                $auth = Firebase::auth();
                $verifiedIdToken = $auth->verifyIdToken($request->token);
                $claims = $verifiedIdToken->claims();
                $email = $claims->get('email');
                $name = $claims->get('name');
            }

            if (!$email) {
                return $this->error('Email not found in Google response', 400);
            }

            return $this->handleFirebaseSocialLogin($email, $name, 'google', $request->referral_code);
        } catch (\Throwable $e) {
            return $this->error('Google login failed: ' . $e->getMessage(), 500);
        }
    }
    

    public function appleLogin(Request $request)
    {
        // ── Normalize token field: accept token / id_token / identityToken / identity_token ──
        $rawToken = $request->input('token')
            ?? $request->input('id_token')
            ?? $request->input('identityToken')
            ?? $request->input('identity_token');

        if (!$rawToken) {
            return $this->validationError(
                ['token' => ['The token field is required. Accepted parameter names: token, id_token, identityToken, identity_token.']],
                'Validation failed',
                422
            );
        }

        $validator = Validator::make(
            ['name' => $request->input('name'), 'referral_code' => $request->input('referral_code')],
            [
                'name'          => 'nullable|string|max:255',
                'referral_code' => 'nullable|string|exists:users,referral_code',
            ]
        );

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray(), 'Validation failed', 422);
        }

        try {
            $name = $request->input('name');

            // ── Detect token issuer from JWT payload (no signature check yet) ──
            $parts = explode('.', $rawToken);
            if (count($parts) !== 3) {
                return $this->unauthorized('Apple sign-in token is malformed.', 401);
            }

            $payloadJson = base64_decode(strtr($parts[1], '-_', '+/') . str_repeat('=', (4 - strlen($parts[1]) % 4) % 4));
            $payload     = json_decode($payloadJson, true);
            $issuer      = $payload['iss'] ?? '';

            if ($issuer === 'https://appleid.apple.com') {
                // ── Path A: Raw Apple Identity Token ──
                // Verify the JWT signature using Apple's public JWK set.
                $appleVerified = $this->verifyAppleToken($rawToken, $parts, $payload);

                if (!$appleVerified['ok']) {
                    \Log::warning('Apple login token verification failed (native Apple token)', [
                        'reason' => $appleVerified['error'],
                        'ip'     => $request->ip(),
                    ]);
                    return $this->unauthorized('Apple sign-in could not be verified. Please try again.', 401);
                }

                $email = $payload['email'] ?? null;
                $sub   = $payload['sub']   ?? null;

                if (!$email && $sub) {
                    $email = 'apple_' . $sub . '@appleid.com';
                }

                if (!$email) {
                    return $this->error('Email or Identifier not found in Apple token', 400);
                }

                return $this->handleFirebaseSocialLogin($email, $name, 'apple', $request->input('referral_code'), $sub);
            } else {
                // ── Path B: Firebase ID Token (existing flow) ──
                $auth = Firebase::auth();

                try {
                    $verifiedIdToken = $auth->verifyIdToken($rawToken);
                } catch (\Throwable $e) {
                    \Log::warning('Apple login token verification failed (Firebase token)', [
                        'reason' => $e->getMessage(),
                        'ip'     => $request->ip(),
                    ]);
                    return $this->unauthorized('Apple sign-in could not be verified. Please try again.', 401);
                }

                $claims = $verifiedIdToken->claims();
                $email  = $claims->get('email');
                $name   = $name ?? $claims->get('name');

                if (!$email && $claims->get('sub')) {
                    $email = 'apple_' . $claims->get('sub') . '@appleid.com';
                }

                if (!$email) {
                    return $this->error('Email or Identifier not found in Apple token', 400);
                }

                return $this->handleFirebaseSocialLogin($email, $name, 'apple', $request->input('referral_code'), $claims->get('sub'));
            }
        } catch (\Throwable $e) {
            return $this->error('Apple login failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Verify a raw Apple Identity Token (JWT) by:
     *   1. Fetching Apple's public JWK set from https://appleid.apple.com/auth/keys
     *   2. Finding the key whose `kid` matches the token header
     *   3. Converting the JWK to a PEM public key using openssl/BCMath
     *   4. Verifying the RS256 signature with openssl_verify()
     *
     * Returns ['ok' => true] on success, or ['ok' => false, 'error' => '...'] on failure.
     */
    private function verifyAppleToken(string $rawToken, array $parts, array $payload): array
    {
        // 1. Decode JWT header to get kid and alg
        $headerJson = base64_decode(strtr($parts[0], '-_', '+/') . str_repeat('=', (4 - strlen($parts[0]) % 4) % 4));
        $header     = json_decode($headerJson, true);
        $kid        = $header['kid'] ?? null;
        $alg        = $header['alg'] ?? 'RS256';

        if (!$kid || $alg !== 'RS256') {
            return ['ok' => false, 'error' => 'Unsupported algorithm or missing kid in Apple token header'];
        }

        // 2. Validate standard claims before checking the signature
        $now = time();
        if (isset($payload['exp']) && $payload['exp'] < $now) {
            return ['ok' => false, 'error' => 'Apple token has expired'];
        }
        if (isset($payload['nbf']) && $payload['nbf'] > $now + 60) {
            return ['ok' => false, 'error' => 'Apple token not yet valid'];
        }
        // Audience must match your Apple Service ID (APPLE_CLIENT_ID)
        $expectedAud = config('services.apple.client_id');
        $aud         = $payload['aud'] ?? null;
        if ($expectedAud && $aud && $aud !== $expectedAud) {
            return ['ok' => false, 'error' => "Apple token audience mismatch: got '{$aud}', expected '{$expectedAud}'"];
        }

        // 3. Fetch Apple public JWKs
        try {
            $jwksResponse = \Illuminate\Support\Facades\Http::timeout(10)->get('https://appleid.apple.com/auth/keys');
            if ($jwksResponse->failed()) {
                return ['ok' => false, 'error' => 'Failed to fetch Apple public keys'];
            }
            $jwks = $jwksResponse->json();
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Network error fetching Apple public keys: ' . $e->getMessage()];
        }

        // 4. Find the matching JWK by kid
        $matchedKey = null;
        foreach ($jwks['keys'] ?? [] as $key) {
            if (($key['kid'] ?? '') === $kid) {
                $matchedKey = $key;
                break;
            }
        }

        if (!$matchedKey) {
            return ['ok' => false, 'error' => "No Apple public key found for kid: {$kid}"];
        }

        // 5. Convert JWK (n, e) to a PEM public key using openssl
        $pem = $this->jwkRsaToPem($matchedKey['n'], $matchedKey['e']);
        if (!$pem) {
            return ['ok' => false, 'error' => 'Failed to convert Apple JWK to PEM'];
        }

        // 6. Verify RS256 signature
        $signingInput = $parts[0] . '.' . $parts[1];
        $signature    = base64_decode(strtr($parts[2], '-_', '+/') . str_repeat('=', (4 - strlen($parts[2]) % 4) % 4));

        $pubKey = openssl_pkey_get_public($pem);
        if ($pubKey === false) {
            return ['ok' => false, 'error' => 'Failed to load Apple public key'];
        }

        $result = openssl_verify($signingInput, $signature, $pubKey, OPENSSL_ALGO_SHA256);

        if ($result !== 1) {
            return ['ok' => false, 'error' => 'Apple token signature verification failed'];
        }

        return ['ok' => true];
    }

    /**
     * Convert a base64url-encoded RSA JWK modulus (n) and exponent (e)
     * into a PEM-encoded RSA public key string.
     *
     * Uses openssl_pkey_get_public with an ASN.1 DER structure built manually,
     * which works on any standard PHP 8+ install without extra extensions.
     */
    private function jwkRsaToPem(string $n, string $e): ?string
    {
        $modulus  = base64_decode(strtr($n, '-_', '+/') . str_repeat('=', (4 - strlen($n) % 4) % 4));
        $exponent = base64_decode(strtr($e, '-_', '+/') . str_repeat('=', (4 - strlen($e) % 4) % 4));

        if ($modulus === false || $exponent === false) {
            return null;
        }

        // Ensure the modulus is treated as a positive integer (prepend 0x00 if high bit set)
        if (ord($modulus[0]) > 0x7f) {
            $modulus = "\x00" . $modulus;
        }
        if (ord($exponent[0]) > 0x7f) {
            $exponent = "\x00" . $exponent;
        }

        // DER-encode the RSA public key: SEQUENCE { INTEGER n, INTEGER e }
        $encodeLength = function (int $len): string {
            if ($len < 128) {
                return chr($len);
            } elseif ($len < 256) {
                return "\x81" . chr($len);
            } else {
                return "\x82" . chr($len >> 8) . chr($len & 0xff);
            }
        };

        $modulusDer  = "\x02" . $encodeLength(strlen($modulus))  . $modulus;
        $exponentDer = "\x02" . $encodeLength(strlen($exponent)) . $exponent;
        $sequenceBody = $modulusDer . $exponentDer;
        $sequence     = "\x30" . $encodeLength(strlen($sequenceBody)) . $sequenceBody;

        // Wrap in SubjectPublicKeyInfo: SEQUENCE { SEQUENCE { OID rsaEncryption, NULL }, BIT STRING { sequence } }
        // OID for rsaEncryption: 1.2.840.113549.1.1.1
        $rsaOid  = "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
        $oidSeq  = "\x30" . $encodeLength(strlen($rsaOid)) . $rsaOid;
        $bitStr  = "\x03" . $encodeLength(strlen($sequence) + 1) . "\x00" . $sequence;
        $spki    = "\x30" . $encodeLength(strlen($oidSeq) + strlen($bitStr)) . $oidSeq . $bitStr;

        $pem = "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($spki), 64, "\n")
            . "-----END PUBLIC KEY-----";

        return $pem;
    }

    protected function handleFirebaseSocialLogin($email, $name, $provider, $referralCode = null, $providerId = null)
    {
        $user = null;

        if ($provider === 'apple' && $providerId) {
            $user = User::where('apple_id', $providerId)->first();
        }

        if (!$user && $email) {
            $user = User::where('email', $email)->first();
        }

        if (!$user) {
            $user = User::create([
                'name'     => $name ?? 'User_' . Str::random(5),
                'email'    => $email,
                'password' => Str::random(16),
                'email_verified_at' => now(),
                'apple_id' => ($provider === 'apple') ? $providerId : null,
                'provider' => $provider,
            ]);
            $user->assignRole('user');
        } else {
            // Update apple_id if it's not set
            if ($provider === 'apple' && $providerId && !$user->apple_id) {
                $user->apple_id = $providerId;
                $user->save();
            }
            
            // Mark email as verified if they login via Google/Apple
            if (!$user->email_verified_at) {
                $user->email_verified_at = now();
                $user->save();
            }
        }

        $referrerBonus = 0;
        $referrerUser = null;

        if ($referralCode) {
            $referrer = User::where('referral_code', $referralCode)->first();

            if ($referrer && $referrer->id !== $user->id) {
                $alreadyUsed = \App\Models\ReferralUsage::where('referred_user_id', $user->id)->exists();

                if (!$alreadyUsed) {
                    $bonus = 100;

                    $referrer->loyalty_points += $bonus;
                    $referrer->save();

                    $referrerBonus = $bonus;
                    $referrerUser = $referrer->name;

                    \App\Models\ReferralUsage::create([
                        'referrer_user_id' => $referrer->id,
                        'referred_user_id' => $user->id,
                        'referral_code' => $referralCode,
                        'bonus_given' => true,
                        'referral_bonus' => $bonus,
                        'used_at' => now(),
                    ]);
                }
            }
        }

        \App\Services\WooCommerceSyncService::syncCustomerToAppUser($user);

        $token = JWTAuth::fromUser($user);

        return $this->success([
            'token' => $token,
            'user' => $this->formatUser($user),
            'referral_bonus' => $referrerBonus,
            'referrer_name' => $referrerUser,
        ], 'Login Successful');
    }

    public function appAccountDelete(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name'     => 'nullable|string',
                'email'    => 'required|email',
                'password' => 'required|string',
                'reason'   => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors'  => $validator->errors()
                ], 422);
            }

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email or Password is incorrect'
                ], 400);
            }

            $user->forceDelete();

            return response()->json([
                'success' => true,
                'message' => 'Profile deleted successfully',
                'code'    => 200
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function accountDelete(Request $request)
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return $this->unauthorized();
            }

            $user->forceDelete();

            return response()->json([
                'success' => true,
                'message' => 'Profile deleted successfully',
                'code'    => 200
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server Error: ' . $e->getMessage()
            ], 500);
        }
    }

    protected function formatUser($user)
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'referral_code' => $user->referral_code,
            'loyalty_points' => $user->loyalty_points,
            'avatar_url' => $user->avatar_url,
            'email_verified_at' => $user->email_verified_at,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }
}
