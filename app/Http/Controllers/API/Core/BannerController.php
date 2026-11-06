<?php

namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Banner;
use App\Traits\ApiResponse;

class BannerController extends Controller
{
    use ApiResponse;
    

    public function index()
    {
        $banners = Banner::where('status', 1)->get()->map(function ($banner) {
            return [
                'id' => $banner->id,
                'title' => $banner->title,
                'sub_title' => $banner->sub_title,
                'image' => $banner->getRawOriginal('image') ? $banner->image : null,
            ];
        });

        return $this->success($banners, 'Banners retrieved successfully.');
    }
}
