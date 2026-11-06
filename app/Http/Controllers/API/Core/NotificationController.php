<?php

namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $query = Notification::where('user_id', auth()->id())->orderBy('created_at', 'desc');

        if ($type = $request->get('type')) {
            $query->ofType($type);
        }

        if ($request->get('unread') == '1') {
            $query->unread();
        }

        $notifications = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $notifications->items(),
        ]);
    }



    public function show($id)
    {
        $notification = Notification::where('user_id', auth()->id())->findOrFail($id);
        if (!$notification->read) $notification->markAsRead();
        return response()->json(['success'=>true,'data'=>$notification]);
    }

    public function markAsRead($id)
    {
        $notification = Notification::where('user_id', auth()->id())->findOrFail($id);
        $notification->markAsRead();
        return response()->json(['success'=>true,'message'=>'Marked as read','data'=>$notification]);
    }

    public function markAllAsRead()
    {
        $updated = Notification::where('user_id', auth()->id())->unread()->update(['read'=>true,'read_at'=>now()]);
        return response()->json(['success'=>true,'message'=>'All marked as read','updated'=>$updated]);
    }

    public function destroy($id)
    {
        $notification = Notification::where('user_id', auth()->id())->findOrFail($id);
        $notification->delete();
        return response()->json(['success'=>true,'message'=>'Notification deleted']);
    }

    public function destroyAll()
    {
        $deleted = Notification::where('user_id', auth()->id())->delete();
        return response()->json(['success'=>true,'message'=>'All notifications deleted','deleted'=>$deleted]);
    }

    public function unreadCount()
    {
        $count = Notification::where('user_id', auth()->id())->unread()->count();
        return response()->json(['success'=>true,'count'=>$count]);
    }

   
}
