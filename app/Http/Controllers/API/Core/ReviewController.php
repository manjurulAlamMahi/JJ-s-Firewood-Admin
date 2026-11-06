<?php

namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Review;
use App\Traits\ApiResponse;

class ReviewController extends Controller
{
    use ApiResponse;


    public function index(Request $request)
    {
        $query = Review::where('status', 'approved')
            ->with(['user:id,name,avatar', 'product:id,name']);

        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        $reviews = $query->latest()->get(['id', 'user_id', 'product_id', 'rating', 'comment', 'status']);

        return $this->success($reviews, 'Approved reviews retrieved successfully');
    }



    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'rating' => 'required|numeric|min:1|max:5',
            'comment' => 'nullable|string',
        ]);

        $user = $request->user();

        $existingReview = Review::where('user_id', $user->id)
            ->where('product_id', $request->product_id)
            ->first();

        if ($existingReview) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reviewed this product.'
            ], 400);
        }
        $review = Review::create([
            'user_id' => $user->id,
            'product_id' => $request->product_id,
            'rating' => $request->rating,
            'comment' => $request->comment,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Review submitted successfully.',
            'data' => $review
        ]);
    }
}
