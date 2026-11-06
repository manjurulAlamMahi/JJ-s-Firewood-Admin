<?php

namespace App\Http\Controllers\Web\Backend\Core;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\SubCategory;
use App\Models\WoodEducationContent;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class WoodEducationController extends Controller
{
    public function index()
    {
        try {
            return view('backend.layouts.wood_education.index');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while loading the Wood Education page: ' . $e->getMessage());
        }
    }

    public function getData()
    {
        try {
            $items = WoodEducationContent::with('category')->latest()->get();
            return DataTables::of($items)
                ->addIndexColumn()
                ->editColumn('short_description', function ($row) {
                    return \Illuminate\Support\Str::limit(strip_tags($row->short_description), 50);
                })
                ->addColumn('category', function ($row) {
                    return $row->category->name ?? '-';
                })
                ->addColumn('image', function ($row) {
                    if ($row->image) {
                        return '<img src="' . asset('uploads/wood_education/' . $row->image) . '" alt="Wood Education Image" width="50">';
                    }
                    return '-';
                })
                ->addColumn('status', function ($row) {
                    return (int) $row->status;
                })
                ->addColumn('action', function ($row) {
                    return "
                        <a href='" . route('wood-education.edit', $row->id) . "' class='btn btn-sm btn-icon btn-warning me-1' title='Edit'>
                            <i class='fa fa-edit'></i>
                        </a>
                        <button class='btn btn-sm btn-icon btn-danger delete-wood-education' data-id='{$row->id}' title='Delete'>
                            <i class='fa fa-trash'></i>
                        </button>
                    ";
                })
                ->rawColumns(['image', 'status', 'action'])
                ->make(true);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while fetching Wood Education data: ' . $e->getMessage()], 500);
        }
    }

    public function create()
    {
        try {
            $categories = Category::orderBy('name')->get();
            $subCategories = SubCategory::orderBy('name')->get();
            return view('backend.layouts.wood_education.create', compact('categories', 'subCategories'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while loading the Wood Education creation page: ' . $e->getMessage());
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'short_description' => 'required|string',
                'long_description' => 'nullable|string',
                'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
                'status' => 'nullable|integer|in:0,1',
                'published_at' => 'nullable|date',
                'category_id' => 'required|exists:categories,id',
                'sub_category_id' => 'nullable|exists:sub_categories,id',
            ]);

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('uploads/wood_education'), $filename);
            } else {
                $filename = null;
            }

            $item = new WoodEducationContent();
            $item->title = $request->title;
            $item->short_description = strip_tags($request->short_description);
            $item->long_description = $request->long_description;
            $item->image = $filename;
            $item->status = 1;
            $item->published_at = $request->published_at;
            $item->category_id = $request->category_id;
            $item->sub_category_id = $request->sub_category_id ?: null;
            $item->save();

            return redirect()->route('wood-education.index')->with('success', 'Wood Education content created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while creating the Wood Education content: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        try {
            $item = WoodEducationContent::findOrFail($id);
            $categories = Category::orderBy('name')->get();
            $subCategories = SubCategory::orderBy('name')->get();
            return view('backend.layouts.wood_education.edit', compact('item', 'categories', 'subCategories'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while loading the Wood Education edit page: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'short_description' => 'required|string',
                'long_description' => 'nullable|string',
                'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
                'published_at' => 'nullable|date',
                'category_id' => 'required|exists:categories,id',
                'sub_category_id' => 'nullable|exists:sub_categories,id',
            ]);

            $item = WoodEducationContent::findOrFail($id);

            if ($request->hasFile('image')) {
                if ($item->image && file_exists(public_path('uploads/wood_education/' . $item->image))) {
                    unlink(public_path('uploads/wood_education/' . $item->image));
                }
                $file = $request->file('image');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('uploads/wood_education'), $filename);
                $item->image = $filename;
            }

            $item->title = $request->title;
            $item->short_description = strip_tags($request->short_description);
            $item->long_description = $request->long_description;
            $item->published_at = $request->published_at;
            $item->category_id = $request->category_id;
            $item->sub_category_id = $request->sub_category_id ?: null;
            $item->save();

            return redirect()->route('wood-education.index')->with('success', 'Wood Education content updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while updating the Wood Education content: ' . $e->getMessage());
        }
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $item = WoodEducationContent::findOrFail($id);
            $item->status = $request->status;
            $item->save();
            return response()->json(['success' => 'Wood Education status updated successfully.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while updating the Wood Education status: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $item = WoodEducationContent::findOrFail($id);
            if ($item->image && file_exists(public_path('uploads/wood_education/' . $item->image))) {
                unlink(public_path('uploads/wood_education/' . $item->image));
            }
            $item->delete();
            return response()->json(['success' => 'Wood Education content deleted successfully.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while deleting the Wood Education content: ' . $e->getMessage()], 500);
        }
    }
}
