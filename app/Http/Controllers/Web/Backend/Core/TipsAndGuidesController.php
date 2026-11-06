<?php

namespace App\Http\Controllers\Web\Backend\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TipsAndGuides;
use Yajra\DataTables\Facades\DataTables;


class TipsAndGuidesController extends Controller
{
    public function index()
    {
        try {
            return view('backend.layouts.tips_and_guides.index');

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while loading the Tips and Guides page: ' . $e->getMessage());
        }
    }

    public function getData()
    {
        try {
            $data = TipsAndGuides::latest()->get();

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('status', function ($row) {
                    return (int) $row->status;
                })
                ->rawColumns(['status'])
                ->make(true);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while fetching Tips and Guides data: ' . $e->getMessage()], 500);
        }
    }

    public function create()
    {
        try {
            $categories = [
                'Fireplace & Heater Care',
                'Burning Tips',
                'Storage & Stacking',
                'Delivery Info',
                'App & Rewards'
            ];
            return view('backend.layouts.tips_and_guides.create', compact('categories'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while loading the Tips and Guides creation page: ' . $e->getMessage());
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'short_description' => 'required|string',
                'long_description' => 'nullable|string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:40960',
                'status' => 'nullable|integer|in:0,1',
                'is_featured' => 'nullable|integer|in:0,1',
                'category_id' => 'nullable|exists:tip_categories,id',
            ]);

            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('uploads/tips_and_guides'), $imageName);
            }
            if ($request->is_featured == 1) {
                TipsAndGuides::query()->update(['is_featured' => 0]);
            }

            $tipsAndGuide = new TipsAndGuides();
            $tipsAndGuide->title = $request->title;
            $tipsAndGuide->short_description = strip_tags($request->short_description);
            $tipsAndGuide->long_description = strip_tags($request->long_description);
            $tipsAndGuide->image = $imageName ?? null;
            $tipsAndGuide->status = $request->status ?? 1;
            $tipsAndGuide->is_featured = $request->is_featured ?? 0;
            $tipsAndGuide->category = $request->category ?: null;
            $tipsAndGuide->save();

            return redirect()->route('tips-and-guides.index')->with('success', 'Tips and Guide created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while creating the Tips and Guide: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        try {
            $tipsAndGuide = TipsAndGuides::findOrFail($id);
            $categories = [
                'Fireplace & Heater Care',
                'Burning Tips',
                'Storage & Stacking',
                'Delivery Info',
                'App & Rewards'
            ];
            return view('backend.layouts.tips_and_guides.edit', compact('tipsAndGuide', 'categories'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while loading the Tips and Guide edit page: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'short_description' => 'required|string',
                'long_description' => 'nullable|string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:40960',
                'status' => 'nullable|integer|in:0,1',
                'is_featured' => 'nullable|integer|in:0,1',
                'category' => 'nullable|string|max:255',
            ]);

            if ($request->is_featured == 1) {
                TipsAndGuides::where('id', '!=', $id)->update(['is_featured' => 0]);
            }

            $tipsAndGuide = TipsAndGuides::findOrFail($id);

            if ($request->hasFile('image')) {
                if ($tipsAndGuide->image && file_exists(public_path('uploads/tips_and_guides/' . $tipsAndGuide->image))) {
                    unlink(public_path('uploads/tips_and_guides/' . $tipsAndGuide->image));
                }
                $image = $request->file('image');
                $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('uploads/tips_and_guides'), $imageName);
                $tipsAndGuide->image = $imageName;
            }

            $tipsAndGuide->title = $request->title;
            $tipsAndGuide->short_description = strip_tags($request->short_description);
            $tipsAndGuide->long_description = strip_tags($request->long_description);
            $tipsAndGuide->status = $request->status ?? 1;
            $tipsAndGuide->is_featured = $request->is_featured ?? 0;
            $tipsAndGuide->category = $request->category ?: null;
            $tipsAndGuide->save();

            return redirect()->route('tips-and-guides.index')->with('success', 'Tips and Guide updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while updating the Tips and Guide: ' . $e->getMessage());
        }

    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $tipsAndGuide = TipsAndGuides::findOrFail($id);
            $tipsAndGuide->status = $request->status;
            $tipsAndGuide->save();

            return response()->json(['success' => 'Status updated successfully.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while updating the status: ' . $e->getMessage()], 500);
        }
    }

    public function updateFeaturedStatus(Request $request, $id)
    {
        try {
            if ($request->is_featured == 1) {
                TipsAndGuides::where('id', '!=', $id)->update(['is_featured' => 0]);
            }
            
            $tipsAndGuide = TipsAndGuides::findOrFail($id);
            $tipsAndGuide->is_featured = $request->is_featured;
            $tipsAndGuide->save();

            return response()->json(['success' => 'Featured status updated successfully.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while updating the featured status: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $tipsAndGuide = TipsAndGuides::findOrFail($id);
            if ($tipsAndGuide->image && file_exists(public_path('uploads/tips_and_guides/' . $tipsAndGuide->image))) {
                unlink(public_path('uploads/tips_and_guides/' . $tipsAndGuide->image));
            }
            $tipsAndGuide->delete();

            return response()->json(['success' => 'Tips and Guide deleted successfully.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while deleting the Tips and Guide: ' . $e->getMessage()], 500);
        }
    }
        
}
