<?php

namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Traits\ApiResponse;
use App\Models\Wishlist;

class WishlistController extends Controller
{
    use ApiResponse;
    public function index()
    {
        $wishlists = auth()->user()->wishlists()
            ->with([
                'product' => function ($q) {
                    $q->withAvg('reviews', 'rating')   
                    ->withCount('reviews');
                }
            ])
            ->get();

        return $this->success($wishlists, 'Wishlist retrieved successfully.');
    }

      public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $user = auth()->user();
        $product = Product::findOrFail($request->product_id);
        $exists = Wishlist::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->exists();

        if ($exists) {
            return $this->error('Product already in wishlist.', 409);
        }

        $wishlist = $user->wishlists()->create([
            'product_id' => $product->id,
        ]);

        return $this->success($wishlist, 'Product added to wishlist successfully.');
    }



    public function destroy($product_id)
    {
        $wishlist = auth()->user()->wishlists()->where('product_id', $product_id)->first();

        if (!$wishlist) {
            return $this->error('Wishlist item not found for this product.', 404);
        }

        $wishlist->delete();

        return $this->success(null, 'Product removed from wishlist successfully.');
    }

}
