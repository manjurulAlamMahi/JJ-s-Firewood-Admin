<?php

namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class ProductUpsellController extends Controller
{
    use ApiResponse;

    public function index(Request $request, $productId)
    {
        try {
            $product = Product::find($productId);

            if (!$product) {
                return $this->error('Product not found.', 404);
            }

            $upsells = $product->upsells()
                ->where('status', 1)
                ->with('upsellProduct')
                ->orderBy('sort_order')
                ->get();

            $data = $upsells->map(function ($upsell) {
                return [
                    'id' => $upsell->id,
                    'product_id' => $upsell->upsell_product_id,
                    'type' => $upsell->type,
                    'name' => $upsell->upsellProduct->name,
                    'image' => $upsell->upsellProduct->image,
                    'price' => $upsell->upsellProduct->numeric_price,
                    'in_stock' => $upsell->upsellProduct->isInStock(1),
                ];
            });

            return $this->success($data, 'Upsells retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('An error occurred while fetching upsells: ' . $e->getMessage(), 500);
        }
    }
}
