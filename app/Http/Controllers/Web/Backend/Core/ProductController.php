<?php

namespace App\Http\Controllers\Web\Backend\Core;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\SubCategory;
use Yajra\DataTables\Facades\DataTables;
use App\Models\ProductOtherImage;
use App\Models\ProductUpsell;

class ProductController extends Controller
{
    public function index()
    {
        return view('backend.layouts.products.index');
    }

    public function getData()
    {
        $data = Product::with(['category', 'subcategory'])->latest();
        return DataTables::of($data)
            ->addIndexColumn()
            ->addColumn('category_name', function ($row) {
                return $row->category ? $row->category->name : 'N/A';
            })
            ->addColumn('subcategory_name', function ($row) {
                return $row->subcategory ? $row->subcategory->name : 'N/A';
            })
            ->addColumn('image', function ($row) {
                return $row->image ?:  asset('no_image/no_image.jpg');
            })
            ->addColumn('status', function ($row) {
                return (int) $row->status;
            })
            ->rawColumns(['image'])
            ->make(true);
    }

    public function create()
    {
        $categories = Category::all();
        $subcategories = SubCategory::all();
        $allProducts = Product::where('status', 1)->orderBy('name')->get();
        $extraUpsellIds = [];
        $upgradeUpsellIds = [];
        return view('backend.layouts.products.create', compact('categories', 'subcategories', 'allProducts', 'extraUpsellIds', 'upgradeUpsellIds'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'subcategory_id' => 'required|exists:sub_categories,id',
            'name' => 'required|string|max:255',
            'short_title' => 'nullable|string|max:255',
            'price' => 'required|string',
            'quantity' => 'nullable|integer',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:20480',
            'other_image.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:20480',
            'size_info' => 'nullable|string',
            'burn_quantity_info' => 'nullable|string',
            'delivery_info' => 'nullable|string',
            'extra_upsells' => 'nullable|array',
            'extra_upsells.*' => 'exists:products,id',
            'upgrade_upsells' => 'nullable|array',
            'upgrade_upsells.*' => 'exists:products,id',
        ]);

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = 'product_' . time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('uploads/product_image'), $imageName);
            $imageUrl = 'uploads/product_image/' . $imageName;
        }

        $product = new Product();
        $product->category_id = $request->category_id;
        $product->subcategory_id = $request->subcategory_id;
        $product->name = $request->name;
        $product->short_title = $request->short_title;
        $product->price = $request->price;
        $product->quantity = $request->quantity;
        $product->description = $request->description;
        $product->image = $imageUrl;
        $product->size_info = $request->size_info;
        $product->burn_quantity_info = $request->burn_quantity_info;
        $product->delivery_info = $request->delivery_info;
        $product->save();

        if ($request->hasFile('other_image')) {
            $otherImages = $request->file('other_image');
            foreach ($otherImages as $otherImage) {
                $otherImageName = 'product_other_' . time() . '_' . $otherImage->getClientOriginalName();
                $otherImage->move(public_path('uploads/product_other_images'), $otherImageName);
                $otherImageUrl = 'uploads/product_other_images/' . $otherImageName;

                ProductOtherImage::create([
                    'product_id' => $product->id,
                    'other_image' => $otherImageUrl,
                ]);
            }
        }

        foreach ((array) $request->input('extra_upsells', []) as $index => $upsellProductId) {
            ProductUpsell::create([
                'product_id' => $product->id,
                'upsell_product_id' => $upsellProductId,
                'type' => 'extra',
                'sort_order' => $index,
                'status' => 1,
            ]);
        }
        foreach ((array) $request->input('upgrade_upsells', []) as $index => $upsellProductId) {
            ProductUpsell::create([
                'product_id' => $product->id,
                'upsell_product_id' => $upsellProductId,
                'type' => 'upgrade',
                'sort_order' => $index,
                'status' => 1,
            ]);
        }


        return redirect()->route('products.index')->with('success', 'Product created successfully.');
    }

    public function edit($id)
    {
        $product = Product::findOrFail($id);
        $categories = Category::all();
        $subcategories = Subcategory::where('category_id', $product->category_id)->get();
        $otherImages = ProductOtherImage::where('product_id', $id)->get();
        $allProducts = Product::where('id', '!=', $id)->where('status', 1)->orderBy('name')->get();
        $extraUpsellIds = $product->upsells()->where('type', 'extra')->pluck('upsell_product_id')->toArray();
        $upgradeUpsellIds = $product->upsells()->where('type', 'upgrade')->pluck('upsell_product_id')->toArray();
        return view('backend.layouts.products.edit', compact(
            'product', 'categories', 'subcategories', 'otherImages',
            'allProducts', 'extraUpsellIds', 'upgradeUpsellIds'
        ));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'subcategory_id' => 'required|exists:sub_categories,id',
            'name' => 'required|string|max:255',
            'short_title' => 'nullable|string|max:255',
            'price' => 'required|string',
            'quantity' => 'nullable|integer',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:20480',
            'other_image.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:20480',
            'size_info' => 'nullable|string',
            'burn_quantity_info' => 'nullable|string',
            'delivery_info' => 'nullable|string',
            'extra_upsells' => 'nullable|array',
            'extra_upsells.*' => 'exists:products,id',
            'upgrade_upsells' => 'nullable|array',
            'upgrade_upsells.*' => 'exists:products,id',
        ]);

        $product = Product::findOrFail($id);

        $imageUrl = $product->getRawOriginal('image');
        if ($request->hasFile('image')) {
            if ($product->getRawOriginal('image') && file_exists(public_path($product->getRawOriginal('image')))) {
                unlink(public_path($product->getRawOriginal('image')));
            }

            $image = $request->file('image');
            $imageName = 'product_' . time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('uploads/product_image'), $imageName);
            $imageUrl = 'uploads/product_image/' . $imageName;
        }

        $product->category_id = $request->category_id;
        $product->subcategory_id = $request->subcategory_id;
        $product->name = $request->name;
        $product->short_title = $request->short_title;
        $product->price = $request->price;
        $product->quantity = $request->quantity;
        $product->description = $request->description;
        $product->image = $imageUrl;
        $product->size_info = $request->size_info;
        $product->burn_quantity_info = $request->burn_quantity_info;
        $product->delivery_info = $request->delivery_info;
        $product->save();

        if ($request->has('deleted_other_images')) {
            foreach ($request->deleted_other_images as $imageId) {
                $oldImage = ProductOtherImage::find($imageId);
                if ($oldImage) {
                    if ($oldImage->other_image && file_exists(public_path($oldImage->other_image))) {
                        unlink(public_path($oldImage->other_image));
                    }
                    $oldImage->delete();
                }
            }
        }

        if ($request->hasFile('other_image')) {
            $otherImages = $request->file('other_image');
            foreach ($otherImages as $otherImage) {
                if ($otherImage->isValid()) {
                    $otherImageName = 'product_other_' . time() . '_' . uniqid() . '_' . $otherImage->getClientOriginalName();
                    $otherImage->move(public_path('uploads/product_other_images'), $otherImageName);
                    $otherImageUrl = 'uploads/product_other_images/' . $otherImageName;

                    ProductOtherImage::create([
                        'product_id' => $product->id,
                        'other_image' => $otherImageUrl,
                    ]);
                }
            }
        }

        // Replace the upsell set wholesale — simplest correct sync, no diffing needed
        // (same pattern used for Bundle extras in Phase 3).
        ProductUpsell::where('product_id', $product->id)->delete();
        foreach ((array) $request->input('extra_upsells', []) as $index => $upsellProductId) {
            ProductUpsell::create([
                'product_id' => $product->id,
                'upsell_product_id' => $upsellProductId,
                'type' => 'extra',
                'sort_order' => $index,
                'status' => 1,
            ]);
        }
        foreach ((array) $request->input('upgrade_upsells', []) as $index => $upsellProductId) {
            ProductUpsell::create([
                'product_id' => $product->id,
                'upsell_product_id' => $upsellProductId,
                'type' => 'upgrade',
                'sort_order' => $index,
                'status' => 1,
            ]);
        }

        return redirect()->route('products.index')->with('success', 'Product updated successfully.');
    }

    public function updateStatus(Request $request, $id)
    {
        $product = Product::find($id);
        $product->status = $request->status;
        $product->save();

        return response()->json(['success' => true, 'message' => 'Status updated successfully.']);
    }


    public function getSubcategories($category_id)
    {
        $subcategories = Subcategory::where('category_id', $category_id)->get();

        return response()->json($subcategories);
    }

    public function getProductsForUpsell(Request $request)
    {
        $query = Product::where('status', 1);

        if ($request->has('exclude_id') && $request->exclude_id != '') {
            $query->where('id', '!=', $request->exclude_id);
        }

        if ($request->has('category_id') && $request->category_id != '') {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('subcategory_id') && $request->subcategory_id != '') {
            $query->where('subcategory_id', $request->subcategory_id);
        }

        if ($request->has('search') && $request->search != '') {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $products = $query->orderBy('name')->get();

        return response()->json($products);
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        if ($product) {
            if ($product->image && file_exists(public_path($product->image))) {
                unlink(public_path($product->image));
            }

            $otherImages = ProductOtherImage::where('product_id', $product->id)->get();
            foreach ($otherImages as $otherImage) {
                if ($otherImage->other_image && file_exists(public_path($otherImage->other_image))) {
                    unlink(public_path($otherImage->other_image));
                }
                $otherImage->delete();
            }

            $product->delete();
            return redirect()->route('products.index')->with('success', 'Product deleted successfully.');
        }
        return redirect()->route('products.index')->with('error', 'Product not found.');
    }


    public function duplicate($id)
    {
        $originalProduct = Product::findOrFail($id);
        $newProduct = $originalProduct->replicate();

        $baseName = preg_replace('/\s\((Copy|\d+)\)$/', '', $originalProduct->name);

        $counter = 1;
        $newName = $baseName . ' (' . $counter . ')';

        while (Product::where('name', $newName)->exists()) {
            $counter++;
            $newName = $baseName . ' (' . $counter . ')';
        }
        $newProduct->name = $newName;

        if ($originalProduct->image) {
            $rawImage = $originalProduct->getRawOriginal('image');

            if ($rawImage && file_exists(public_path($rawImage))) {
                $extension = pathinfo($rawImage, PATHINFO_EXTENSION);
                $newImageName = 'product_' . time() . '_' . uniqid() . '.' . $extension;
                $newImagePath = 'uploads/product_image/' . $newImageName;
                copy(public_path($rawImage), public_path($newImagePath));
                $newProduct->image = $newImagePath;
            } else {
                $newProduct->image = null;
            }
        }

        $newProduct->created_at = now();
        $newProduct->updated_at = now();
        $newProduct->save();

        $otherImages = ProductOtherImage::where('product_id', $originalProduct->id)->get();
        foreach ($otherImages as $other) {
            if ($other->other_image && file_exists(public_path($other->other_image))) {
                $extension = pathinfo($other->other_image, PATHINFO_EXTENSION);
                $newOtherImageName = 'product_other_' . time() . '_' . uniqid() . '.' . $extension;
                $newOtherImagePath = 'uploads/product_other_images/' . $newOtherImageName;

                copy(public_path($other->other_image), public_path($newOtherImagePath));

                ProductOtherImage::create([
                    'product_id' => $newProduct->id,
                    'other_image' => $newOtherImagePath
                ]);
            }
        }

        return redirect()->route('products.index')->with('success', 'Product duplicated successfully.');
    }
}
