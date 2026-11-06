<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use Illuminate\Support\Facades\Auth;

class UserPushNotificationController extends Controller
{
   
    public function togglePush(Request $request, $category)
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $user = Auth::user();

        $preference = NotificationPreference::updateOrCreate(
            ['user_id' => $user->id, 'category' => $category],
            ['push' => $validated['enabled']]
        );

        return response()->json([
            'success' => true,
            'message' => "Push notification for {$category} " . ($validated['enabled'] ? 'enabled' : 'disabled'),
            'data' => [
                'category' => $category,
                'push' => (bool) $preference->push,
            ]
        ]);
    }

    
    public function toggleEmail(Request $request, $category)
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $user = Auth::user();

        $preference = NotificationPreference::updateOrCreate(
            ['user_id' => $user->id, 'category' => $category],
            ['email' => $validated['enabled']]
        );

        return response()->json([
            'success' => true,
            'message' => "Email notification for {$category} " . ($validated['enabled'] ? 'enabled' : 'disabled'),
            'data' => [
                'category' => $category,
                'email' => (bool) $preference->email,
            ]
        ]);
    }


    public function getPreferences()
    {
        $user = Auth::user();
        
        $categories = ['transactional', 'helpful', 'updates'];
        $preferences = [];

        foreach ($categories as $category) {
            $pref = $user->preferenceFor($category);
            $preferences[$category] = [
                'email' => $pref ? (bool) $pref->email : true,
                'push' => $pref ? (bool) $pref->push : true,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $preferences,
        ]);
    }
}
