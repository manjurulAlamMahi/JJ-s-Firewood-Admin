<?php

namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use App\Models\WoodEducationContent;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class WoodEducationController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $request->validate([
            'category_id' => 'required|integer|exists:categories,id',
        ]);

        try {
            $item = WoodEducationContent::where('category_id', $request->category_id)
                ->where('status', 1)
                ->first();

            if (!$item) {
                return $this->success(null, 'No Wood Education content for this category.');
            }

            return $this->success([
                'id' => $item->id,
                'title' => $item->title,
                'short_description' => $item->short_description,
                'long_description' => $item->long_description,
                'image_url' => image_asset($item->image ? 'uploads/wood_education/' . $item->image : null),
                'published_at' => $item->published_at ? $item->published_at->toDateTimeString() : null,
            ], 'Wood Education content retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('An error occurred while fetching Wood Education content: ' . $e->getMessage(), 500);
        }
    }
}
