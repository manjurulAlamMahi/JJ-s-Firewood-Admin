<?php

namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;

class ServicesCategoryController extends Controller
{
    use ApiResponse;

    public function index()
    {
        try {
            $categories = ServiceCategory::where('status', 1)->latest()
                ->get()
                ->map(function ($category) {
                    // ServiceCategory::image and image_url are supplied by model accessors.
                    // Do not pass that full URL through asset() a second time.
                    $category->name = $category->inapp_name;
                    unset($category->inapp_name);

                    return $category;
                });

            return $this->success($categories, 'Categories retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve categories.', 500);
        }
    }
}
