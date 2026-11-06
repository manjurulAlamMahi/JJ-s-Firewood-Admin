<?php

namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use App\Models\Bundle;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class BundleController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        try {
            $bundles = Bundle::with('baseProduct')
                ->where('status', 1)
                ->orderBy('position')
                ->orderBy('id')
                ->get();

            $data = $bundles->map(function ($bundle) {
                return [
                    'id' => $bundle->id,
                    'name' => $bundle->name,
                    'image_url' => image_asset($bundle->image ? 'uploads/bundles/' . $bundle->image : null),
                    'effective_price' => $bundle->applyDiscount($bundle->effective_price),
                    'original_price' => $bundle->effective_price,
                    'discount_percentage' => (float) $bundle->discount_percentage,
                ];
            });

            return $this->success($data, 'Bundles retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('An error occurred while fetching bundles: ' . $e->getMessage(), 500);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $bundle = Bundle::with(['baseProduct', 'extras' => function ($query) {
                $query->where('status', 1)->orderBy('sort_order');
            }, 'extras.product'])
                ->where('status', 1)
                ->find($id);

            if (!$bundle) {
                return $this->error('Bundle not found.', 404);
            }

            return $this->success([
                'id' => $bundle->id,
                'name' => $bundle->name,
                'description' => $bundle->description,
                'image_url' => image_asset($bundle->image ? 'uploads/bundles/' . $bundle->image : null),
                // Deliberately raw (pre-discount) here, unlike index()'s teaser
                // price — the discount applies to (base + selected extras)
                // combined, which only the builder screen knows once the
                // customer has toggled extras. It replicates the server's
                // formula client-side using these raw values + discount_percentage
                // for the live running total; the actual charge is still always
                // recomputed server-side at add-to-cart time.
                'effective_price' => $bundle->effective_price,
                'discount_percentage' => (float) $bundle->discount_percentage,
                'base_product' => [
                    'id' => $bundle->baseProduct->id,
                    'name' => $bundle->baseProduct->name,
                    'image' => $bundle->baseProduct->image,
                    'in_stock' => $bundle->baseProduct->isInStock(1),
                ],
                'extras' => $bundle->extras->map(function ($extra) {
                    return [
                        'id' => $extra->id,
                        'product_id' => $extra->product_id,
                        'name' => $extra->product->name,
                        'image' => $extra->product->image,
                        'price' => $extra->product->numeric_price,
                        'in_stock' => $extra->product->isInStock(1),
                        'is_required' => (bool) $extra->is_required,
                    ];
                }),
            ], 'Bundle retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('An error occurred while fetching the bundle: ' . $e->getMessage(), 500);
        }
    }
}
