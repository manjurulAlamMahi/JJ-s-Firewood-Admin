<?php

namespace App\Http\Controllers\Web\Backend\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Review;
use Yajra\DataTables\Facades\DataTables;

class ReviewController extends Controller
{
    public function index()
    {
        return view('backend.layouts.review.index');
    }

    public function getData()
    {
        $data = Review::with('user:id,name,email', 'product:id,name')->latest();

        return DataTables::of($data)
            ->addIndexColumn()
            ->editColumn('status', function ($row) {
                $statusColors = [
                    'pending' => 'warning',
                    'approved' => 'success',
                    'rejected' => 'danger'
                ];

                $status = $row->status ?? 'pending';

                $options = '';
                foreach ($statusColors as $key => $color) {
                    $selected = ($key === $status) ? 'selected' : '';
                    $options .= "<option value='$key' $selected class='bg-$color text-white'>$key</option>";
                }

                return "<select class='form-select update-status' data-id='{$row->id}'>$options</select>";
            })
            ->addColumn('action', function ($row) {
                return "
                    <a href='" . route('review.show', $row->id) . "' class='btn btn-sm btn-info text-white me-1'>
                        <i class='fa fa-eye'></i>
                    </a>
                    <button class='btn btn-sm btn-danger delete-review' data-id='{$row->id}'>
                        <i class='fa fa-trash'></i> 
                    </button>
                ";
            })
            ->rawColumns(['status', 'action'])
            ->make(true);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,approved,rejected'
        ]);

        $review = Review::findOrFail($id);
        $review->status = $request->status;
        $review->save();

        return response()->json(['status' => true, 'message' => 'Status updated successfully']);
    }

    public function destroy($id)
    {
        $review = Review::findOrFail($id);
        $review->delete();

        return response()->json(['status' => true, 'message' => 'Review deleted successfully']);
    }
    public function show($id)
    {
        $review = Review::with('user', 'product')->findOrFail($id);
        return view('backend.layouts.review.show', compact('review'));
    }
}
