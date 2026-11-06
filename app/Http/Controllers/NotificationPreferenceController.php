<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\NotificationPreference;

class NotificationPreferenceController extends Controller
{
    
    public function index()
    {
        $user = Auth::user();
        
        $categories = ['transactional', 'helpful', 'updates'];
        $defaults = [
            'transactional' => ['email' => true, 'push' => true],
            'helpful' => ['email' => true, 'push' => true],
            'updates' => ['email' => true, 'push' => true],
        ];

        $prefs = $user->notificationPreferences()
            ->get()
            ->keyBy('category')
            ->map(function ($pref) {
                return [
                    'email' => (bool) $pref->email,
                    'push' => (bool) $pref->push,
                ];
            })
            ->toArray();

        foreach ($defaults as $category => $defaultValues) {
            if (!isset($prefs[$category])) {
                $prefs[$category] = $defaultValues;
            }
        }

        return response()->json($prefs);
    }

    
    public function update(Request $request, $category)
    {
        $validated = $request->validate([
            'email' => 'sometimes|boolean',
            'push' => 'sometimes|boolean',
        ]);

        $user = Auth::user();

        $preference = NotificationPreference::updateOrCreate(
            ['user_id' => $user->id, 'category' => $category],
            [
                'email' => $validated['email'] ?? true,
                'push' => $validated['push'] ?? true,
            ]
        );

        return response()->json([
            'message' => "Notification preferences for {$category} updated successfully.",
            'data' => $preference,
        ]);
    }

    
    public function show($category)
    {
        $user = Auth::user();
        $preference = $user->preferenceFor($category);

        if (!$preference) {
            return response()->json([
                'category' => $category,
                'email' => true,
                'push' => true,
            ]);
        }

        return response()->json($preference);
    }
}
