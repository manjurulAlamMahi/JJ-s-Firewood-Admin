<?php

namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use App\Models\ServiceSubCategory;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;

class ServicesSubCategoryController extends Controller
{
    use ApiResponse;
    public function index()
    {
        try {
            $subCategories = ServiceSubCategory::where('status', 1)->with('features')->get();

            $subCategories->transform(function ($sub) {
                // ServiceSubCategory::image is already a public URL.
                $sub->name = $sub->inapp_name;
                unset($sub->inapp_name);

                return $sub;
            });

            return $this->success($subCategories, 'Subcategories retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve subcategories.', 500);
        }
    }

    public function show($id)
    {
        try {
            $subCategory = ServiceSubCategory::with('features')->findOrFail($id);

            if ($subCategory->inapp_name) {
                $subCategory->name = $subCategory->inapp_name;
            }

            return $this->success($subCategory, 'Subcategory retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve subcategory.', 500);
        }
    }
}
