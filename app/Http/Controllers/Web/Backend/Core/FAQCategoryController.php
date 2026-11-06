<?php

namespace App\Http\Controllers\Web\Backend\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use App\Models\FAQCategory;

class FAQCategoryController extends Controller
{
    public function index()
    {
        try {
            return view('backend.layouts.faq_categories.index');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while loading the FAQ Categories page: ' . $e->getMessage());
        }
    }

    public function getData()
    {
        try {
            $data = FAQCategory::orderBy('sort_order')->get();

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('status', function ($row) {
                    return (int) $row->status;
                })
                ->rawColumns(['status'])
                ->make(true);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while fetching FAQ Category data: ' . $e->getMessage()], 500);
        }
    }

    public function create()
    {
        try {
            return view('backend.layouts.faq_categories.create');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while loading the FAQ Category creation page: ' . $e->getMessage());
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

            $category = new FAQCategory();
            $category->name = $request->name;
            $category->sort_order = $request->sort_order ?? 0;
            $category->status = $request->status ?? 1;
            $category->save();

            return redirect()->route('faq-categories.index')->with('success', 'FAQ Category created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while creating the FAQ Category: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        try {
            $category = FAQCategory::findOrFail($id);
            return view('backend.layouts.faq_categories.edit', compact('category'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while loading the FAQ Category edit page: ' . $e->getMessage());
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

            $category = FAQCategory::findOrFail($id);
            $category->name = $request->name;
            $category->sort_order = $request->sort_order ?? 0;
            $category->status = $request->status ?? 1;
            $category->save();

            return redirect()->route('faq-categories.index')->with('success', 'FAQ Category updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while updating the FAQ Category: ' . $e->getMessage());
        }
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $category = FAQCategory::findOrFail($id);
            $category->status = $request->status;
            $category->save();

            return response()->json(['success' => 'FAQ Category status updated successfully.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while updating the FAQ Category status: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $category = FAQCategory::findOrFail($id);
            $category->delete();

            return redirect()->route('faq-categories.index')->with('success', 'FAQ Category deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while deleting the FAQ Category: ' . $e->getMessage());
        }
    }
}
