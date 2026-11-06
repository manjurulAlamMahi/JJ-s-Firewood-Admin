<?php

namespace App\Http\Controllers\Web\Backend\Core\Services;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ServiceSubCategory;
use App\Models\ServiceCategory;
use App\Models\ServiceSubCategoryFeature;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Facades\DataTables;

class ServicesSubCategoryController extends Controller
{
    public function index()
    {
        return view('backend.layouts.our_services.sub_category.index');
    }

    public function getData()
    {
        $data = ServiceSubCategory::with('serviceCategory', 'features')->latest()->get();

        return DataTables::of($data)
            ->addIndexColumn()
            ->addColumn('category_name', fn($row) => $row->serviceCategory ? $row->serviceCategory->name : 'N/A')
            ->addColumn('inapp_name', fn($row) => $row->inapp_name ?? 'N/A')
            ->addColumn('image', fn($row) => $row->image ? '<img src="' . asset($row->image) . '" width="50" height="50">' : 'No Image')
            ->addColumn('status', fn($row) => (int)$row->status)
            ->rawColumns(['image', 'status'])
            ->make(true);
    }

    public function create()
    {
        $categories = ServiceCategory::all();
        return view('backend.layouts.our_services.sub_category.create', compact('categories'));
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'service_category_id' => 'required|exists:service_categories,id',
                'name' => 'required|string|max:255|unique:service_sub_categories,name',
                'inapp_name' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:20480',
                'features.*' => 'nullable|string|max:255',
            ]);

            $imageUrl = null;
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imageName = 'services_subcategory_' . time() . '_' . $image->getClientOriginalName();
                $image->move(public_path('uploads/services_subcategory_image'), $imageName);
                $imageUrl = 'uploads/services_subcategory_image/' . $imageName;
            }

            $subCategory = new ServiceSubCategory();
            $subCategory->service_category_id = $request->service_category_id;
            $subCategory->name = $request->name;
            $subCategory->inapp_name = $request->inapp_name;
            $subCategory->description = $request->description;
            $subCategory->image = $imageUrl;
            $subCategory->save();

            if ($request->features) {
                foreach ($request->features as $feature) {
                    if ($feature) {
                        $subCategory->features()->create(['name' => $feature]);
                    }
                }
            }

            return redirect()->route('services_sub_category.index')->with('success', 'SubCategory created successfully.');
        } catch (\Exception $e) {
            return redirect()->route('services_sub_category.index')->with('error', $e->getMessage());
        }
    }

    public function edit($id)
    {
        $data = ServiceSubCategory::with('features')->findOrFail($id);
        $categories = ServiceCategory::all();
        return view('backend.layouts.our_services.sub_category.edit', compact('data', 'categories'));
    }

    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'service_category_id' => 'required|exists:service_categories,id',
                'name' => 'required|string|max:255|unique:service_sub_categories,name,' . $id,
                'inapp_name' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:20480',
                'features.*' => 'nullable|string|max:255',
            ]);

            $subCategory = ServiceSubCategory::findOrFail($id);
            $imageUrl = $subCategory->image;

            if ($request->hasFile('image')) {
                if ($subCategory->image && file_exists(public_path($subCategory->image))) {
                    unlink(public_path($subCategory->image));
                }
                $image = $request->file('image');
                $imageName = 'services_subcategory_' . time() . '_' . $image->getClientOriginalName();
                $image->move(public_path('uploads/services_subcategory_image'), $imageName);
                $imageUrl = 'uploads/services_subcategory_image/' . $imageName;
            }

            $subCategory->update([
                'service_category_id' => $request->service_category_id,
                'name' => $request->name,
                'inapp_name' => $request->inapp_name,
                'description' => $request->description,
                'image' => $imageUrl,
            ]);

            if ($request->features) {
                $subCategory->features()->delete();
                foreach ($request->features as $feature) {
                    if ($feature) {
                        $subCategory->features()->create(['name' => $feature]);
                    }
                }
            }

            return redirect()->route('services_sub_category.index')->with('success', 'SubCategory updated successfully.');
        } catch (\Exception $e) {
            return redirect()->route('services_sub_category.index')->with('error', $e->getMessage());
        }
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $subCategory = ServiceSubCategory::findOrFail($id);
            $subCategory->status = $request->status;
            $subCategory->save();
            return response()->json(['success' => true, 'message' => 'Status updated successfully.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to update status.']);
        }
    }

    public function destroy($id)
    {
        try {
            $subCategory = ServiceSubCategory::findOrFail($id);
            if ($subCategory->image && file_exists(public_path($subCategory->image))) {
                unlink(public_path($subCategory->image));
            }
            $subCategory->features()->delete();
            $subCategory->delete();
            return redirect()->route('services_sub_category.index')->with('success', 'SubCategory deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->route('services_sub_category.index')->with('error', $e->getMessage());
        }
    }
}
