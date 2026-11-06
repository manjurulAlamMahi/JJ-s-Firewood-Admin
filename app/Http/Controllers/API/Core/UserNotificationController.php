<?php

namespace App\Http\Controllers\API\Core;

use Illuminate\Http\Request;
use App\Models\PushNotification;
use App\Models\UserNotification;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;

class UserNotificationController extends Controller
{

    use ApiResponse;
   
    public function index()
    {
        $user = Auth::guard('api')->user();

        $notifications = PushNotification::where('status', 1)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($notification) use ($user) {
                $userNotification = UserNotification::where('user_id', $user->id)
                    ->where('push_notification_id', $notification->id)
                    ->first();

                return [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'is_read' => $userNotification ? (bool) $userNotification->is_read : false,
                    'created_at' => $notification->created_at->format('Y-m-d H:i:s'),
                ];
            });

     

        return $this->success($notifications, 'Notifications retrieved successfully.');
    }

    public function markAllRead()
    {
        $user = Auth::guard('api')->user();

        $notificationIds = PushNotification::pluck('id');

        foreach ($notificationIds as $nid) {
            UserNotification::updateOrCreate(
                ['user_id' => $user->id, 'push_notification_id' => $nid],
                ['is_read' => true]
            );
        }



        return $this->success(null, 'All notifications marked as read successfully');
    }


    public function markAsRead($id)
    {
        $user = Auth::guard('api')->user();

        $notification = PushNotification::where('id', $id)
            ->where('status', 1)
            ->first();

        if (!$notification) {
            return response()->json([
                'status' => false,
                'message' => 'Notification not found or unpublished',
            ], 404);
        }

        UserNotification::updateOrCreate(
            [
                'user_id' => $user->id,
                'push_notification_id' => $id
            ],
            [
                'is_read' => true
            ]
        );



        return $this->success(null, 'Notification marked as read successfully');
    }
}
