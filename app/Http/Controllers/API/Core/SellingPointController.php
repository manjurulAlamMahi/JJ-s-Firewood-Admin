<?php

namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SellingPoint;
use App\Traits\ApiResponse;

class SellingPointController extends Controller
{
    use ApiResponse;
    public function index()
    {
        $selling_points = SellingPoint::where('status', 1)->get()->map(function ($selling_point) {
            return [
                'id' => $selling_point->id,
                'name' => $selling_point->name,
                'description' => $selling_point->description,
                'image' => $selling_point->getRawOriginal('image') ? $selling_point->image : null,
            ];
        });

        return $this->success($selling_points, 'Selling Points retrieved successfully.');
    }
}
