<?php

namespace App\Http\Controllers\Web\Backend\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PushNotification;
use App\Models\Product;
use App\Models\User;
use App\Jobs\SendAdminPushNotificationJob;
use App\Mail\ManualNotificationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;

class PushNotificationController extends Controller
{
    public function index()
    {
        return view('backend.layouts.admin.index');
    }

    public function getData()
    {
        $data = PushNotification::latest()->get();

        return DataTables::of($data)
            ->addIndexColumn()
            ->addColumn('category', function ($row) {
                $badgeColor = match ($row->category) {
                    'transactional' => 'primary',
                    'helpful' => 'info',
                    'updates' => 'success',
                    default => 'secondary'
                };
                return '<span class="badge bg-' . $badgeColor . '">' . ucfirst($row->category) . '</span>';
            })
            ->addColumn('status', function ($row) {
                $checked = $row->status == 1 ? 'checked' : '';
                return '
                <label class="switch">
                    <input type="checkbox" class="toggle-status"
                           data-id="' . $row->id . '" ' . $checked . '>
                    <span class="slider"></span>
                </label>
            ';
            })
            ->addColumn('action', function ($row) {
                return '
                <a href="' . route('notifications.edit', $row->id) . '" class="btn btn-sm btn-info me-1">
                    <i class="fa fa-edit"></i>
                </a>

                <form action="' . route('notifications.destroy', $row->id) . '" method="POST" style="display:inline">
                    ' . csrf_field() . method_field('DELETE') . '
                    <button class="btn btn-sm btn-danger" onclick="return confirm(\'Delete this notification?\')">
                        <i class="fa fa-trash"></i>
                    </button>
                </form>
            ';
            })
            ->rawColumns(['category', 'status', 'action'])
            ->make(true);
    }

    public function getSentData()
    {
        $data = PushNotification::where('is_send', 1)->latest()->get();

        return DataTables::of($data)
            ->addIndexColumn()
            ->addColumn('category', function ($row) {
                $badgeColor = match ($row->category) {
                    'transactional' => 'primary',
                    'helpful' => 'info',
                    'updates' => 'success',
                    default => 'secondary'
                };
                return '<span class="badge bg-' . $badgeColor . '">' . ucfirst($row->category) . '</span>';
            })
            ->addColumn('status', function ($row) {
                return $row->status == 1 ? '<span class="badge bg-success">Send</span>' : '<span class="badge bg-success">Send</span>';
            })
            ->addColumn('action', function ($row) {
                return '
                <a href="' . route('notifications.edit', $row->id) . '" class="btn btn-sm btn-info me-1">
                    <i class="fa fa-edit"></i>
                </a>

                <form action="' . route('notifications.destroy', $row->id) . '" method="POST" style="display:inline">
                    ' . csrf_field() . method_field('DELETE') . '
                    <button class="btn btn-sm btn-danger" onclick="return confirm(\'Delete this notification?\')">
                        <i class="fa fa-trash"></i>
                    </button>
                </form>
            ';
            })
            ->rawColumns(['category', 'status', 'action'])
            ->make(true);
    }

    public function create()
    {
        $products = Product::where('status', 1)->orderBy('name')->get();
        return view('backend.layouts.admin.create', compact('products'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'             => 'required|string|max:255',
            'message'           => 'required|string',
            'category'          => 'required|in:transactional,helpful,updates',
            'product_id'        => 'nullable|exists:products,id',
        ]);

        PushNotification::create([
            'title'             => $request->title,
            'message'           => $request->message,
            'category'          => $request->category,
            'product_id'        => $request->product_id ?: null,
            'use_preferences'   => true,
            'status'            => 0,
            'is_send'           => 0,
        ]);

        return redirect()->route('notifications.index')->with('success', 'Notification created successfully');
    }

    public function edit($id)
    {
        $notification = PushNotification::findOrFail($id);
        $products = Product::where('status', 1)->orderBy('name')->get();
        return view('backend.layouts.admin.edit', compact('notification', 'products'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'title'             => 'required|string|max:255',
            'message'           => 'required|string',
            'category'          => 'required|in:transactional,helpful,updates',
            'product_id'        => 'nullable|exists:products,id',
        ]);

        $notification = PushNotification::findOrFail($id);
        $notification->update([
            'title'             => $request->title,
            'message'           => $request->message,
            'category'          => $request->category,
            'product_id'        => $request->product_id ?: null,
            'use_preferences'   => true,
        ]);

        return redirect()->route('notifications.index')->with('success', 'Notification updated successfully');
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            \Log::info('updateStatus called', ['id' => $id, 'status' => $request->status]);

            $notification = PushNotification::find($id);
            if (!$notification) {
                \Log::warning('Notification not found', ['id' => $id]);
                return response()->json(['error' => 'Notification not found.'], 404);
            }

            $request->validate([
                'status' => 'required|integer|in:0,1',
            ]);

            $notification->status = $request->status;
            $notification->save();

            \Log::info('Notification status updated', ['id' => $id, 'new_status' => $notification->status]);
            return response()->json(['success' => 'Status updated successfully.']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation error', ['errors' => $e->errors()]);
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            \Log::error('Error updating status', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }


    public function destroy($id)
    {
        PushNotification::findOrFail($id)->delete();
        return back()->with('success', 'Notification deleted');
    }

    public function send($id)
    {
        try {
            $notification = PushNotification::findOrFail($id);

            SendAdminPushNotificationJob::dispatch($notification);

            Log::info("Push notification send job dispatched", [
                'notification_id' => $id,
                'title' => $notification->title,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notification queued for sending. It will respect user preferences.',
            ]);
        } catch (\Exception $e) {
            Log::error("Error dispatching notification job", [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }
}
