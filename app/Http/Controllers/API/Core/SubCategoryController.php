<?php

namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;
use App\Models\SubCategory;

class SubCategoryController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $subCategories = SubCategory::where('status', 1)->orderBy('position')->orderBy('id')->get()->map(function ($subCategory) {
            return [
                'category_id' => $subCategory->category_id,
                'category_name' => $subCategory->category ? $subCategory->category->name : null,
                'subcategory_id' => $subCategory->id,
                'subcategory_name' => $subCategory->name,
                'subcategory_description' => $subCategory->description,
                // The model accessor already turns the stored path into a public URL.
                'subcategory_image' => $subCategory->getRawOriginal('image') ? $subCategory->image : null,
            ];
        });

        return $this->success($subCategories, 'SubCategories retrieved successfully.');
    }
}
