<?php

namespace App\Http\Controllers\Web\Backend\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use App\Models\TipCategory;

class TipCategoryController extends Controller
{
    public function index()
    {
        try {
            return view('backend.layouts.tip_categories.index');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while loading the Tip Categories page: ' . $e->getMessage());
        }
    }

    public function getData()
    {
        try {
            $data = TipCategory::orderBy('sort_order')->get();

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('status', function ($row) {
                    return (int) $row->status;
                })
                ->rawColumns(['status'])
                ->make(true);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while fetching Tip Category data: ' . $e->getMessage()], 500);
        }
    }

    public function create()
    {
        try {
            return view('backend.layouts.tip_categories.create');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while loading the Tip Category creation page: ' . $e->getMessage());
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'sort_order' => 'nullable|integer',
                'status' => 'nullable|integer|in:0,1',
            ]);

            $category = new TipCategory();
            $category->name = $request->name;
            $category->sort_order = $request->sort_order ?? 0;
            $category->status = $request->status ?? 1;
            $category->save();

            return redirect()->route('tip-categories.index')->with('success', 'Tip Category created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while creating the Tip Category: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        try {
            $category = TipCategory::findOrFail($id);
            return view('backend.layouts.tip_categories.edit', compact('category'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while loading the Tip Category edit page: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'sort_order' => 'nullable|integer',
                'status' => 'nullable|integer|in:0,1',
            ]);

            $category = TipCategory::findOrFail($id);
            $category->name = $request->name;
            $category->sort_order = $request->sort_order ?? 0;
            $category->status = $request->status ?? 1;
            $category->save();

            return redirect()->route('tip-categories.index')->with('success', 'Tip Category updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while updating the Tip Category: ' . $e->getMessage());
        }
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $category = TipCategory::findOrFail($id);
            $category->status = $request->status;
            $category->save();

            return response()->json(['success' => 'Tip Category status updated successfully.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while updating the Tip Category status: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $category = TipCategory::findOrFail($id);
            $category->delete();

            return redirect()->route('tip-categories.index')->with('success', 'Tip Category deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while deleting the Tip Category: ' . $e->getMessage());
        }
    }
}
