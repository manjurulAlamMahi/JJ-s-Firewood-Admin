<?php

namespace App\Http\Controllers\Web\Backend\Core\Services;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ServiceCategory;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;

class ServicesCategoryController extends Controller
{

    public function getData()
    {
        try {
            $data = ServiceCategory::latest()->get();

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('image', fn($row) => $row->image ?: asset('no_image/no_image.jpg'))
                ->addColumn('inapp_name', fn($row) => $row->inapp_name ?? 'N/A')
                ->addColumn('url', fn($row) => $row->url ?? 'N/A')
                ->addColumn('status', fn($row) => (int) $row->status)
                ->rawColumns(['image', 'status'])
                ->make(true);

        } catch (\Exception $e) {
            Log::error('Service Category Data Load Error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load data'], 500);
        }
    }


    public function index()
    {
        return view('backend.layouts.our_services.category.index');
    }


    public function create()
    {
        return view('backend.layouts.our_services.category.create');
    }


    public function store(Request $request)
    {
        try {
            $request->validate([
                'name'  => 'nullable|string|max:255',
                'inapp_name' => 'nullable|string|max:255',
                'url' => 'nullable|string|max:255',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:20480',
            ]);

            $imageUrl = null;

            if ($request->hasFile('image')) {
                $image     = $request->file('image');
                $imageName = 'services_category_' . time() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('uploads/services_category_image'), $imageName);

                $imageUrl = 'uploads/services_category_image/' . $imageName;
            }

            ServiceCategory::create([
                'name'  => $request->name,
                'inapp_name' => $request->inapp_name,
                'url' => $request->url,
                'image' => $imageUrl,
                'status' => 1,
            ]);

            return redirect()
                ->route('services.category.index')
                ->with('success', 'Service Category created successfully.');

        } catch (\Exception $e) {
            Log::error('Service Category Store Error: ' . $e->getMessage());
            return back()->with('error', 'Failed to create category.')->withInput();
        }
    }


    public function edit($id)
    {
        try {
            $category = ServiceCategory::findOrFail($id);
            return view('backend.layouts.our_services.category.edit', compact('category'));

        } catch (\Exception $e) {
            Log::error('Service Category Edit Error: ' . $e->getMessage());
            return back()->with('error', 'Category not found.');
        }
    }


    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'name'  => 'nullable|string|max:255',
                'inapp_name' => 'nullable|string|max:255',
                'url' => 'nullable|string|max:255',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:20480',
            ]);

            $category = ServiceCategory::findOrFail($id);
            $imageUrl = $category->getRawOriginal('image');

            if ($request->hasFile('image')) {
                if ($category->image && file_exists(public_path($category->image))) {
                    unlink(public_path($category->image));
                }

                $image     = $request->file('image');
                $imageName = 'services_category_' . time() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('uploads/services_category_image'), $imageName);

                $imageUrl = 'uploads/services_category_image/' . $imageName;
            }

            $category->update([
                'name'  => $request->name,
                'inapp_name' => $request->inapp_name,
                'url' => $request->url,
                'image' => $imageUrl,
            ]);

            return redirect()
                ->route('services.category.index')
                ->with('success', 'Service Category updated successfully.');

        } catch (\Exception $e) {
            Log::error('Service Category Update Error: ' . $e->getMessage());
            return back()->with('error', 'Failed to update category.')->withInput();
        }
    }


    public function updateStatus(Request $request, $id)
    {
        try {
            $category = ServiceCategory::findOrFail($id);
            $category->status = $request->status;
            $category->save();

            return response()->json(['success' => true, 'message' => 'Status updated successfully.']);

        } catch (\Exception $e) {
            Log::error('Service Category Status Update Error: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to update status'], 500);
        }
    }


    public function destroy($id)
    {
        try {
            $category = ServiceCategory::findOrFail($id);

            if ($category->image && file_exists(public_path($category->image))) {
                unlink(public_path($category->image));
            }

            $category->delete();

            return redirect()
                ->route('services.category.index')
                ->with('success', 'Service Category deleted successfully.');

        } catch (\Exception $e) {
            Log::error('Service Category Delete Error: ' . $e->getMessage());
            return back()->with('error', 'Failed to delete category.');
        }
    }
}
