<?php

namespace App\Http\Controllers\Web\Backend\Core;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Facades\DataTables;
use App\Models\SubCategory;

class SubCategoryController extends Controller
{
    public function index()
    {
        $subcategories = SubCategory::latest()->paginate(8);
        return view('backend.layouts.subcategory.index', compact('subcategories'));
    }

    public function getData()
    {
        $data = SubCategory::with('category')->latest()->get();

        return DataTables::of($data)
            ->addIndexColumn()
            ->addColumn('category_name', function ($row) {
                return $row->category ? $row->category->name : 'N/A';
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
        return view('backend.layouts.subcategory.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:130',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:20480',
        ]);
        $imageUrl = null;

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = 'subcategory_' . time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('uploads/subcategory_image'), $imageName);
            $imageUrl = 'uploads/subcategory_image/' . $imageName;
        }

        $subcategory = new SubCategory();
        $subcategory->category_id = $request->category_id;
        $subcategory->name = $request->name;
        $subcategory->description = $request->description;
        $subcategory->image = $imageUrl;
        $subcategory->save();

        return redirect(route('product-centre.index') . '#subcategories')->with('success', 'SubCategory created successfully.');
    }

    public function edit($id)
    {
        $subcategory = SubCategory::findOrFail($id);
        $categories = Category::all();
        return view('backend.layouts.subcategory.edit', compact('subcategory', 'categories'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:130',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:20480',
        ]);


        $subcategory = SubCategory::findOrFail($id);
        $imageUrl = $subcategory->getRawOriginal('image');

        if ($request->hasFile('image')) {
            if ($subcategory->getRawOriginal('image') && file_exists(public_path($subcategory->getRawOriginal('image')))) {
                unlink(public_path($subcategory->getRawOriginal('image')));
            }
            $image = $request->file('image');
            $imageName = 'subcategory_' . time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('uploads/subcategory_image'), $imageName);
            $imageUrl = 'uploads/subcategory_image/' . $imageName;
        }

        $subcategory->category_id = $request->category_id;
        $subcategory->name = $request->name;
        $subcategory->description = $request->description;
        $subcategory->image = $imageUrl;
        $subcategory->save();

        return redirect(route('product-centre.index') . '#subcategories')->with('success', 'SubCategory updated successfully.');
    }

    public function updateStatus(Request $request, $id)
    {
        $subcategory = SubCategory::findOrFail($id);
        if ($subcategory) {
            $subcategory->status = $request->status;
            $subcategory->save();
            return response()->json(['success' => true, 'message' => 'Status updated successfully.']);
        }
        return response()->json(['success' => false, 'message' => 'SubCategory not found.'], 404);
    }

    public function destroy($id)
    {
        $subcategory = SubCategory::findOrFail($id);
        if ($subcategory) {
            if ($subcategory->image && file_exists(public_path($subcategory->image))) {
                unlink(public_path($subcategory->image));
            }
            $subcategory->delete();
            return response()->json(['success' => true, 'message' => 'SubCategory deleted successfully.']);
        }
        return response()->json(['success' => false, 'message' => 'SubCategory not found.'], 404);
    }

    public function duplicate($id)
    {
        $originalSubCategory = SubCategory::findOrFail($id);
        $newSubCategory = $originalSubCategory->replicate();


        $baseName = preg_replace('/\s\((Copy|\d+)\)$/', '', $originalSubCategory->name);

        $counter = 1;
        $newName = $baseName . ' (' . $counter . ')';

        while (SubCategory::where('name', $newName)->exists()) {
            $counter++;
            $newName = $baseName . ' (' . $counter . ')';
        }
        $newSubCategory->name = $newName;

        if ($originalSubCategory->image) {
            $rawImage = $originalSubCategory->getRawOriginal('image');

            if ($rawImage && file_exists(public_path($rawImage))) {
                $extension = pathinfo($rawImage, PATHINFO_EXTENSION);
                $newImageName = 'subcategory_' . time() . '_' . uniqid() . '.' . $extension;
                $newImagePath = 'uploads/subcategory_image/' . $newImageName;
                copy(public_path($rawImage), public_path($newImagePath));
                $newSubCategory->image = $newImagePath;
            } else {
                $newSubCategory->image = null;
            }
        }

        $newSubCategory->created_at = now();
        $newSubCategory->updated_at = now();
        $newSubCategory->save();

        return redirect(route('product-centre.index') . '#subcategories')->with('success', 'SubCategory duplicated successfully.');
    }
}
