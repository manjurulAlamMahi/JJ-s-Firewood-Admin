<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PushNotification;
use App\Jobs\SendAdminPushNotificationJob;
use Illuminate\Support\Facades\Log;

class AdminPushNotificationController extends Controller
{
    
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'     => 'required|string|max:255',
            'message'   => 'required|string',
            'category'  => 'required|in:transactional,helpful,updates',
        ]);

        try {
            $notification = PushNotification::create([
                'title'             => $validated['title'],
                'message'           => $validated['message'],
                'category'          => $validated['category'],
                'use_preferences'   => true,
                'status'            => 1,
                'is_send'           => 0,
            ]);

            Log::info("Push notification created via API", [
                'notification_id' => $notification->id,
                'category' => $notification->category,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notification created successfully',
                'data' => $notification,
            ], 201);
        } catch (\Exception $e) {
            Log::error("Error creating push notification", [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create notification',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function index()
    {
        try {
            $notifications = PushNotification::latest()->get();

            return response()->json([
                'success' => true,
                'data' => $notifications,
                'total' => $notifications->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notifications',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function show($id)
    {
        try {
            $notification = PushNotification::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $notification,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }


    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'title'     => 'sometimes|string|max:255',
            'message'   => 'sometimes|string',
            'category'  => 'sometimes|in:transactional,helpful,updates',
        ]);

        try {
            $notification = PushNotification::findOrFail($id);
            $notification->update($validated);

            Log::info("Push notification updated via API", [
                'notification_id' => $id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notification updated successfully',
                'data' => $notification,
            ]);
        } catch (\Exception $e) {
            Log::error("Error updating push notification", [
                'notification_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update notification',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function send($id)
    {
        try {
            $notification = PushNotification::findOrFail($id);

            SendAdminPushNotificationJob::dispatch($notification);

            Log::info("Push notification send job dispatched via API", [
                'notification_id' => $id,
                'title' => $notification->title,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notification queued for sending',
                'data' => $notification,
            ]);
        } catch (\Exception $e) {
            Log::error("Error sending push notification", [
                'notification_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send notification',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function destroy($id)
    {
        try {
            $notification = PushNotification::findOrFail($id);
            $notification->delete();

            Log::info("Push notification deleted via API", [
                'notification_id' => $id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error("Error deleting push notification", [
                'notification_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete notification',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
