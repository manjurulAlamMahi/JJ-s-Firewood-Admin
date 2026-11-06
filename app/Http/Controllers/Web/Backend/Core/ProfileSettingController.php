<?php

namespace App\Http\Controllers\Web\Backend\Core;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Exception;
use App\Services\Service;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

class ProfileSettingController extends Controller
{
    public function index()
    {
        return view('backend.layouts.settings.profileSetting');
    }

    public function updateProfile(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name'     => 'required|string|max:255',
                'email'    => 'required|string|email|max:255|unique:users,email,' . Auth::id(),
            ],
            [
                'name.required'     => 'The name field is required.',
                'name.string'       => 'The name must be a valid string.',
                'name.max'          => 'The name must not exceed 255 characters.',

                'email.required'    => 'The email field is required.',
                'email.string'      => 'The email must be a valid string.',
                'email.email'       => 'Please enter a valid email address.',
                'email.max'         => 'The email must not exceed 255 characters.',
                'email.unique'      => 'The email address has already been taken.',
            ]
        );

        if ($validator->fails()) {
            return redirect()->back()->with([
                'error' => $validator->errors()->first(),
                'type' => 'profile'
            ]);
        }

        try {
            $data = $request->all();
            User::find(Auth::id())->update($data);

            return redirect()->back()->with([
                'success' => 'Profile updated successfully.',
                'type' => 'profile'
            ]);
        } catch (Exception $e) {
            return redirect()->back()->with([
                'error' => 'Profile update failed.',
                'type' => 'profile'
            ]);
        }
    }

    public function updatePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'old_password' => 'required|string',
            'password' => 'required|string|confirmed',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with([
                'error' => $validator->errors()->first(),
                'type'  => 'password'
            ]);
        }

        try {
            if (!Hash::check($request->old_password, Auth::user()->password)) {
                throw new Exception('Old password does not match.');
            }

            $user = User::find(Auth::id());
            $user->password = Hash::make($request->password);
            $user->save();

            Auth::setUser($user);


            return redirect()->back()->with([
                'success' => 'Password updated successfully.',
                'type' => 'password'
            ]);
        } catch (Exception $e) {
            return redirect()->back()->with([
                'error' => $e->getMessage() ?: 'Password update failed.',
                'type' => 'password'
            ]);
        }
    }

    public function updatePhoto(Request $request)
    {
        try {
            $request->validate([
                'photo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:20480',
            ]);

            $user = Auth::user();
            if ($request->hasFile('photo')) {

                if ($user->profile_photo_path    && file_exists(public_path($user->profile_photo_path))) {
                    unlink(public_path($user->profile_photo_path));
                }
                $file = $request->file('photo');
                $filename = uniqid() . '.' . $file->getClientOriginalExtension();
                $path = 'profile_photos/photos/';
                $file->move(public_path($path), $filename);
                $user->profile_photo_path = $path . $filename;
                $user->save();
            }

            return redirect()->back()->with([
                'success' => 'Profile photo updated successfully.',
                'type' => 'photo'
            ]);
        } catch (Exception $e) {
            return redirect()->back()->with([
                'error' => $e->getMessage() ?: 'Profile photo update failed.',
                'type' => 'photo'
            ]);
        }
    }

    public function removeAvatar(Request $request)
    {
        try {
            $user = Auth::user();

            if ($user->profile_photo && Storage::exists('public/profile_photos/' . $user->profile_photo)) {
                Storage::delete('public/profile_photos/' . $user->profile_photo);
            }

            $user->profile_photo = null;
            $user->save();

            return redirect()->back()->with('success', 'Avatar removed successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to remove avatar.');
        }
    }
}
