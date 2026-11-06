<?php

namespace App\Http\Controllers\Web\Backend\Core\Services;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ServiceSubCategory;
use App\Models\ServiceCategory;
use App\Models\ServiceProduct;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Facades\DataTables;

class ServicesProductController extends Controller
{
    public function index()
    {
        return view('backend.layouts.our_services.product.index');
    }

  public function getData()
{
    $data = ServiceProduct::with('serviceCategory', 'serviceSubCategory')->latest()->get();

    return DataTables::of($data)
        ->addIndexColumn()
        ->addColumn('category_name', fn($row) => $row->serviceCategory ? $row->serviceCategory->name : 'N/A')
        ->addColumn('sub_category_name', fn($row) => $row->serviceSubCategory ? $row->serviceSubCategory->name : 'N/A')
        ->addColumn('installation_included', fn($row) => $row->installation_included ? 1 : 0)
        ->addColumn('status', fn($row) => (int)$row->status)
        ->rawColumns(['status'])
        ->make(true);
}



    public function create()
    {
        $categories = ServiceCategory::all();
        $subcategories = ServiceSubCategory::all();
        return view('backend.layouts.our_services.product.create', compact('categories', 'subcategories'));
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'service_category_id' => 'required|exists:service_categories,id',
                'service_sub_category_id' => 'required|exists:service_sub_categories,id',
                'name' => 'required|string|max:255|unique:service_products,name',
                'price' => 'required|string|max:255',
                'installation_included' => 'nullable|boolean',
                'features.*' => 'nullable|string|max:255',
            ]);

            $imageUrl = null;
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imageName = 'services_product_' . time() . '_' . $image->getClientOriginalName();
                $image->move(public_path('uploads/services_product_image'), $imageName);
                $imageUrl = 'uploads/services_product_image/' . $imageName;
            }

            $product = new ServiceProduct();
            $product->service_category_id = $request->service_category_id;
            $product->service_sub_category_id = $request->service_sub_category_id;
            $product->name = $request->name;
            $product->price = $request->price;
            $product->installation_included = $request->installation_included;
            $product->image = $imageUrl;
            $product->save();

            if ($request->features) {
                foreach ($request->features as $feature) {
                    if ($feature) {
                        $product->features()->create(['name' => $feature]);
                    }
                }
            }
            return redirect()->route('services.product.index')->with('success', 'Product created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create product.');
        }

    }


    public function edit($id)
    {
        $data = ServiceProduct::with('features')->findOrFail($id);
        $categories = ServiceCategory::all();
        $subcategories = ServiceSubCategory::all();
        return view('backend.layouts.our_services.product.edit', compact('data', 'categories', 'subcategories'));
    }


    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'service_category_id' => 'required|exists:service_categories,id',
                'service_sub_category_id' => 'required|exists:service_sub_categories,id',
                'name' => 'required|string|max:255|unique:service_products,name,' . $id,
                'price' => 'required|string|max:255',
                'installation_included' => 'nullable|boolean',
                'features.*' => 'nullable|string|max:255',
            ]);

            $product = ServiceProduct::findOrFail($id);

            $imageUrl = $product->getRawOriginal('image');
            if ($request->hasFile('image')) {
                if ($product->image && file_exists(public_path($product->image))) {
                    unlink(public_path($product->image));
                }
                $image = $request->file('image');
                $imageName = 'services_product_' . time() . '_' . $image->getClientOriginalName();
                $image->move(public_path('uploads/services_product_image'), $imageName);
                $imageUrl = 'uploads/services_product_image/' . $imageName;
            }

            $product->service_category_id = $request->service_category_id;
            $product->service_sub_category_id = $request->service_sub_category_id;
            $product->name = $request->name;
            $product->price = $request->price;
            $product->installation_included = $request->installation_included;
            $product->image = $imageUrl;
            $product->save();



            if ($request->features) {
                $product->features()->delete();
                foreach ($request->features as $feature) {
                    if ($feature) {
                        $product->features()->create(['name' => $feature]);
                    }
                }
            }

            return redirect()->route('services.product.index')->with('success', 'Product updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update product.');
        }
    }


    public function updateStatus(Request $request, $id)
    {
        try {
            $product = ServiceProduct::findOrFail($id);
            $product->status = $request->status;
            $product->save();

            return response()->json(['success' => true, 'message' => 'Status updated successfully.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to update status.']);
        }
    }

    public function destroy($id)
    {
        try {
            $product = ServiceProduct::findOrFail($id);
            if ($product->image && file_exists(public_path($product->image))) {
                unlink(public_path($product->image));
            }
            $product->features()->delete();
            $product->delete();

            return redirect()->route('services.product.index')->with('success', 'Product deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->route('services.product.index')->with('error', 'Failed to delete product.');
        }
    }





}
