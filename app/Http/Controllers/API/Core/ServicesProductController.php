<?php

namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use App\Models\ServiceProduct;
use Illuminate\Http\Request;
use App\Models\Info;
use Illuminate\Support\Facades\Mail;
use App\Mail\RequestQuoteMail;
use App\Traits\ApiResponse;

class ServicesProductController extends Controller
{
    use ApiResponse;

    public function index()
    {
        try {
            $products = ServiceProduct::where('status', 1)->with([
                'serviceCategory',
                'serviceSubCategory',
                'features'
            ])->get();
            $transformedProducts = $products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'service_category_id' => $product->service_category_id,
                    'service_sub_category_id' => $product->service_sub_category_id,
                    'name' => $product->name,
                    'price' => $product->price,
                    'installation_included' => $product->installation_included,
                    'image' => $product->image,
                    'status' => $product->status,
                    'created_at' => $product->created_at,
                    'updated_at' => $product->updated_at,
                    'features' => $product->features->map(function ($feature) {
                        return ['name' => $feature->name];
                    })->values()->toArray(),
                    'service_category' => $product->serviceCategory ? [
                        'id' => $product->serviceCategory->id,
                        'name' => $product->serviceCategory->name,
                    ] : null,
                    'service_sub_category' => $product->serviceSubCategory ? [
                        'id' => $product->serviceSubCategory->id,
                        'service_category_id' => $product->serviceSubCategory->service_category_id,
                        'name' => $product->serviceSubCategory->name,
                    ] : null,
                ];
            });

            return $this->success($transformedProducts, 'Products retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve products. Error: ' . $e->getMessage(), 500);
        }
    }

    public function show($id)
    {
        try {
            $product = ServiceProduct::with([
                'serviceCategory',
                'serviceSubCategory',
                'features'
            ])->findOrFail($id);

            $transformedProduct = [
                'id' => $product->id,
                'service_category_id' => $product->service_category_id,
                'service_sub_category_id' => $product->service_sub_category_id,
                'name' => $product->name,
                'price' => $product->price,
                'installation_included' => $product->installation_included,
                'image' => $product->image,
                'status' => $product->status,
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
                'features' => $product->features->map(function ($feature) {
                    return ['name' => $feature->name];
                })->values()->toArray(),
                'service_category' => $product->serviceCategory ? [
                    'id' => $product->serviceCategory->id,
                    'name' => $product->serviceCategory->name,
                ] : null,
                'service_sub_category' => $product->serviceSubCategory ? [
                    'id' => $product->serviceSubCategory->id,
                    'service_category_id' => $product->serviceSubCategory->service_category_id,
                    'name' => $product->serviceSubCategory->name,
                ] : null,
            ];

            return $this->success($transformedProduct, 'Product retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve product. Error: ' . $e->getMessage(), 500);
        }
    }

    public function RequestQuote(Request $request)
    {
        if (!$request->user() || !$request->user()->email) {
            return response()->json([
                'success' => false,
                'message' => 'Please login first.',
                'is_guest' => 1
            ], 401);
        }

        $request->validate([
            'product_id' => 'required|exists:service_products,id',
        ]);

        $user = $request->user();
        $userData = [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone ?? 'N/A',
            'address' => $user->address ?? 'N/A',
        ];

        $product = ServiceProduct::with('features')->findOrFail($request->product_id);
        $productData = [
            'name' => $product->name,
            'price' => $product->price,
            'features' => $product->features->pluck('name')->toArray(),
        ];

        $infos = Info::whereIn('id', [1, 2])->get();
        $admin = $infos->firstWhere('id', 1);
        $serviceProvider = $infos->firstWhere('id', 2);

        $mailData = [
            'user' => $userData,
            'product' => $productData,
        ];

        Mail::to($admin->email)->send(new RequestQuoteMail($mailData));
        Mail::to($serviceProvider->email)->send(new RequestQuoteMail($mailData));

        return response()->json([
            'success' => true,
            'message' => 'Quote request sent successfully.'
        ]);
    }




}
