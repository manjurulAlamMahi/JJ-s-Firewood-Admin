<?php

namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Blog;
use App\Traits\ApiResponse;

class BlogController extends Controller
{
    use ApiResponse;

    public function index()
    {
        try {
            $blogs = Blog::where('status', 1)->latest()->get();
            $data = $blogs->map(function ($item) {
                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'short_description' => $item->short_description,
                    'long_description' => $item->long_description,
                    'image_url' => image_asset($item->image ? 'uploads/blogs/' . $item->image : null),
                    'published_at' => $item->published_at ? $item->published_at->toDateTimeString() : null,
                ];
            });
            return $this->success($data, 'Blogs retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('An error occurred while fetching blogs: ' . $e->getMessage(), 500);
        }
    }
}
