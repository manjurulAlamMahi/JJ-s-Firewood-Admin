<?php

namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Wishlist;

class ProductController extends Controller
{

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Product::where('status', 1)->orderBy('position')->orderBy('id')->with([
            'category:id,name',
            'subcategory:id,name',
            'otherImages:id,product_id,other_image',
            'reviews' => function ($query) {
                $query->select('id', 'product_id', 'rating')->where('status', 'approved');
            }
        ]);

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('short_title', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        $products = $query->get()->makeHidden([
            'quantity',
            'status',
            'created_at',
            'updated_at',
            'category_id',
            'subcategory_id',
            'other_images'
        ]);

        $products->map(function ($product) use ($user) {
            $product->average_rating = round($product->reviews->avg('rating') ?? 0, 1);
            $product->review_count = $product->reviews->count();
            $product->stock_status = $product->stock_status;

            $product->wishlist = $user
                ? Wishlist::where('user_id', $user->id)
                ->where('product_id', $product->id)
                ->exists()
                : false;

            unset($product->reviews);
            return $product;
        });

        return response()->json([
            'success' => true,
            'message' => 'Products retrieved successfully',
            'user_id' => $user ? $user->id : null,
            'data' => $products
        ]);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();

        $product = Product::with([
            'category:id,name',
            'subcategory:id,name',
            'otherImages:id,product_id,other_image',
            'reviews' => function ($query) {
                $query->select('id', 'product_id', 'rating')->where('status', 'approved');
            }
        ])->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        $product->average_rating = round($product->reviews->avg('rating') ?? 0, 1);
        $product->review_count = $product->reviews->count();
        $product->stock_status = $product->stock_status;

        $product->wishlist = $user
            ? Wishlist::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->exists()
            : false;

        unset($product->reviews);

        return response()->json([
            'success' => true,
            'message' => 'Product retrieved successfully',
            'user_id' => $user ? $user->id : null,
            'data' => $product
        ]);
    }

    public function cardProduct(Request $request, $id)
    {
        $user = $request->user();

        $product = Product::with([
            'subcategory:id,name',
            'otherImages:product_id,other_image',
            'reviews' => function ($query) {
                $query->select('id', 'product_id', 'rating')->where('status', 'approved');
            }
        ])->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        $cartProduct = [
            'subcategory_name' => $product->subcategory ? $product->subcategory->name : null,
            'name' => $product->name,
            'image' => $product->getRawOriginal('image') ? $product->image : null,
            'short_title' => $product->short_title,
            'price' => $product->price,
            'average_rating' => round($product->reviews->avg('rating') ?? 0, 1),
            'review_count' => $product->reviews->count(),
            'wishlist' => $user ? Wishlist::where('user_id', $user->id)
                ->where('product_id', $product->id)
                ->exists() : false,
            'other_images' => $product->otherImages->map(function ($img) {
                return $img->getRawOriginal('other_image') ? $img->other_image : null;
            }),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Product retrieved successfully',
            'user_id' => $user ? $user->id : null,
            'data' => $cartProduct
        ]);
    }
}
