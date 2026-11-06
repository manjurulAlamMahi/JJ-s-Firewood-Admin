<?php

namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;

class CategoryController extends Controller
{
    use ApiResponse;
    public function index()
    {
        $categories = Category::where('status', 1)->latest()->get()->map(function ($category) {
            return [
                'category_id' => $category->id,
                'category_name' => $category->inapp_name,
                // Category::image is already a complete, public URL via its accessor.
                // Wrapping it in url() created URLs such as
                // https://host/https://host/uploads/category_image/example.jpg.
                'category_image' => $category->getRawOriginal('image') ? $category->image : null,
            ];
        });

        return $this->success($categories, 'Categories retrieved successfully.');
    }


}
