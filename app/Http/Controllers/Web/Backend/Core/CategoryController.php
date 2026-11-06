<?php

namespace App\Http\Controllers\Web\Backend\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Facades\DataTables;

class CategoryController extends Controller
{

    public function index()
    {
        return view('backend.layouts.category.index');
    }


    public function getData()
    {
        $data = Category::latest()->get();

        return DataTables::of($data)
            ->addIndexColumn()
            ->addColumn('image', function ($row) {
                return $row->image ?: asset('no_image/no_image.jpg');
            })
            ->addColumn('inapp_name', function ($row) {
                return $row->inapp_name ?? 'N/A';
            })
            ->addColumn('status', function ($row) {
                return (int) $row->status;
            })
            ->rawColumns(['image'])
            ->make(true);
    }


    public function create()
    {
        return view('backend.layouts.category.create');
    }


    public function store(Request $request)
    {
        $request->validate([
            'name'  => 'nullable|string|max:255',
            'inapp_name' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:20480',
        ]);

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = 'category_' . time() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('uploads/category_image'), $imageName);
            $imageUrl = 'uploads/category_image/' . $imageName;
        }
        $category = new Category();
        $category->name = $request->name;
        $category->inapp_name = $request->inapp_name;
        $category->image = $imageUrl;
        $category->save();

        return redirect()->route('product-centre.index')->with('success', 'Category created successfully.');
    }


    public function edit($id)
    {
        $category = Category::find($id);
        return view('backend.layouts.category.edit', compact('category'));
    }


    public function update(Request $request, $id)
    {
        $request->validate([
            'name'  => 'nullable|string|max:255',
            'inapp_name' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:20480',
        ]);

        $category = Category::findOrFail($id);
        $imageUrl = $category->getRawOriginal('image');

        if ($request->hasFile('image')) {
            if ($category->getRawOriginal('image') && file_exists(public_path($category->getRawOriginal('image')))) {
                unlink(public_path($category->getRawOriginal('image')));
            }
            $image = $request->file('image');
            $imageName = 'category_' . time() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('uploads/category_image'), $imageName);
            $imageUrl = 'uploads/category_image/' . $imageName;
        }


        $category->name = $request->name;
        $category->inapp_name = $request->inapp_name;
        $category->image = $imageUrl;
        $category->save();

        return redirect()->route('product-centre.index')->with('success', 'Category updated successfully.');
    }


    public function updateStatus(Request $request, $id)
    {
        $category = Category::findOrFail($id);
        $category->status = $request->status;
        $category->save();

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully!',
        ]);
    }


    public function destroy($id)
    {
        $category = Category::findOrFail($id);

        if ($category->image && file_exists(public_path($category->image))) {
            unlink(public_path($category->image));
        }

        $category->delete();

        return redirect()->route('product-centre.index')->with('success', 'Category deleted successfully.');
    }

    public function duplicate($id)
    {
        $originalCategory = Category::findOrFail($id);
        $newCategory = $originalCategory->replicate();

        $baseName = preg_replace('/\s\((Copy|\d+)\)$/', '', $originalCategory->name);

        $counter = 1;
        $newName = $baseName . ' (' . $counter . ')';

        while (Category::where('name', $newName)->exists()) {
            $counter++;
            $newName = $baseName . ' (' . $counter . ')';
        }
        $newCategory->name = $newName;

        if ($originalCategory->image) {
            $rawImage = $originalCategory->getRawOriginal('image');
            if ($rawImage && file_exists(public_path($rawImage))) {
                $extension = pathinfo($rawImage, PATHINFO_EXTENSION);
                $newImageName = 'category_' . time() . '_' . uniqid() . '.' . $extension;
                $newImagePath = 'uploads/category_image/' . $newImageName;
                copy(public_path($rawImage), public_path($newImagePath));
                $newCategory->image = $newImagePath;
            } else {
                $newCategory->image = null;
            }
        }

        $newCategory->created_at = now();
        $newCategory->updated_at = now();
        $newCategory->save();

        return redirect()->route('product-centre.index')->with('success', 'Category duplicated successfully.');
    }
}
