<?php

namespace App\Http\Controllers\Web\Backend;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\SubCategory;
use App\Models\Product;
use App\Models\ProductOtherImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductCentreController extends Controller
{

    public function index()
    {
        $stats = [
            'categories' => Category::count(),
            'subcategories' => SubCategory::count(),
            'products' => Product::count(),
            'active_products' => Product::where('status', 1)->count(),
        ];

        return view('backend.product-centre.index', compact('stats'));
    }


    public function getCategoriesData()
    {
        $categories = Category::orderBy('position', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($cat) {
                return [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'inapp_name' => $cat->inapp_name ?? 'N/A',
                    'image' => $cat->image,
                    'status' => (int)$cat->status,
                    'subcategories_count' => SubCategory::where('category_id', $cat->id)->count(),
                    'products_count' => Product::where('category_id', $cat->id)->count(),
                ];
            });

        return response()->json(['data' => $categories]);
    }


    public function getSubCategoriesData(Request $request)
    {
        $query = SubCategory::with('category');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $subcategories = $query->orderBy('position', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($sub) {
                return [
                    'id' => $sub->id,
                    'name' => $sub->name,
                    'category_name' => $sub->category->name ?? 'N/A',
                    'category_id' => $sub->category_id,
                    'image' => $sub->image,
                    'status' => (int)$sub->status,
                    'products_count' => Product::where('subcategory_id', $sub->id)->count(),
                ];
            });

        return response()->json(['data' => $subcategories]);
    }


    public function getProductsData(Request $request)
    {
        $query = Product::with(['category', 'subcategory']);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('subcategory_id')) {
            $query->where('subcategory_id', $request->subcategory_id);
        }

        $products = $query->orderBy('position', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($prod) {
                return [
                    'id' => $prod->id,
                    'name' => $prod->name,
                    'short_title' => $prod->short_title,
                    'category_name' => $prod->category->name ?? 'N/A',
                    'subcategory_name' => $prod->subcategory->name ?? 'N/A',
                    'price' => $prod->price,
                    'quantity' => $prod->quantity,
                    'image' => $prod->getAttributes()['image'] ?? null,
                    'status' => (int)$prod->status,
                ];
            });

        return response()->json(['data' => $products]);
    }


    public function reorderCategories(Request $request)
    {
        $request->validate([
            'order' => 'required|array',
            'order.*.id' => 'required|exists:categories,id',
            'order.*.position' => 'required|integer|min:1',
        ]);

        try {
            foreach ($request->order as $item) {
                Category::where('id', $item['id'])->update(['position' => $item['position']]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Order updated successfully!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update order'
            ], 500);
        }
    }


    public function reorderSubCategories(Request $request)
    {
        $request->validate([
            'order' => 'required|array',
            'order.*.id' => 'required|exists:sub_categories,id',
            'order.*.position' => 'required|integer|min:1',
        ]);

        try {
            foreach ($request->order as $item) {
                SubCategory::where('id', $item['id'])->update(['position' => $item['position']]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Order updated successfully!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update order'
            ], 500);
        }
    }


    public function reorderProducts(Request $request)
    {
        $request->validate([
            'order' => 'required|array',
            'order.*.id' => 'required|exists:products,id',
            'order.*.position' => 'required|integer|min:1',
        ]);

        try {
            foreach ($request->order as $item) {
                Product::where('id', $item['id'])->update(['position' => $item['position']]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Order updated successfully!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update order'
            ], 500);
        }
    }


    public function updateCategoryStatus(Request $request, $id)
    {
        $category = Category::findOrFail($id);
        $category->status = $request->status;
        $category->save();

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully!'
        ]);
    }


    public function updateSubCategoryStatus(Request $request, $id)
    {
        $subcategory = SubCategory::findOrFail($id);
        $subcategory->status = $request->status;
        $subcategory->save();

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully!'
        ]);
    }


    public function updateProductStatus(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $product->status = $request->status;
        $product->save();

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully!'
        ]);
    }


    public function bulkDeleteCategories(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:categories,id'
        ]);

        try {
            foreach ($request->ids as $id) {
                $category = Category::find($id);
                if ($category) {
                    if ($category->image && file_exists(public_path($category->image))) {
                        unlink(public_path($category->image));
                    }
                    $category->delete();
                }
            }

            return response()->json([
                'success' => true,
                'message' => count($request->ids) . ' categories deleted successfully!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete categories'
            ], 500);
        }
    }


    public function bulkDeleteSubCategories(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:sub_categories,id'
        ]);

        try {
            foreach ($request->ids as $id) {
                $subcategory = SubCategory::find($id);
                if ($subcategory) {
                    if ($subcategory->image && file_exists(public_path($subcategory->image))) {
                        unlink(public_path($subcategory->image));
                    }
                    $subcategory->delete();
                }
            }

            return response()->json([
                'success' => true,
                'message' => count($request->ids) . ' subcategories deleted successfully!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete subcategories'
            ], 500);
        }
    }


    public function bulkDeleteProducts(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:products,id'
        ]);

        try {
            foreach ($request->ids as $id) {
                $product = Product::find($id);
                if ($product) {
                    if ($product->getAttributes()['image'] && file_exists(public_path($product->getAttributes()['image']))) {
                        unlink(public_path($product->getAttributes()['image']));
                    }

                    $otherImages = ProductOtherImage::where('product_id', $product->id)->get();
                    foreach ($otherImages as $img) {
                        if ($img->other_image && file_exists(public_path($img->other_image))) {
                            unlink(public_path($img->other_image));
                        }
                        $img->delete();
                    }

                    $product->delete();
                }
            }

            return response()->json([
                'success' => true,
                'message' => count($request->ids) . ' products deleted successfully!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete products'
            ], 500);
        }
    }


    public function getFilterData()
    {
        $categories = Category::where('status', 1)
            ->orderBy('position', 'asc')
            ->get(['id', 'name']);

        $subcategories = SubCategory::where('status', 1)
            ->with('category:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'category_id']);

        return response()->json([
            'categories' => $categories,
            'subcategories' => $subcategories
        ]);
    }
}
