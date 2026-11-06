<?php

namespace App\Http\Controllers\Web\Backend\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SellingPoint;
use Yajra\DataTables\Facades\DataTables;

class SellingPointController extends Controller
{
    public function index()
    {
        return view('backend.layouts.selling_point.index');
    }

    public function getData()
    {
        $data = SellingPoint::latest()->get();

        return DataTables::of($data)
            ->addIndexColumn()
            ->addColumn('image', function ($row) {
                return $row->image ?: asset('no_image/no_image.jpg');
            })
            ->addColumn('status', function ($row) {
                return (int) $row->status;
            })
            ->rawColumns(['image'])
            ->make(true);
    }

    public function create()
    {
        return view('backend.layouts.selling_point.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'description' => 'nullable',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:20480',
        ]);

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = 'selling_point_' . time() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('uploads/selling_point_image'), $imageName);
            $imageUrl = 'uploads/selling_point_image/' . $imageName;
        }

        $selling_point = new SellingPoint();
        $selling_point->name = $request->name;
        $selling_point->description = $request->description;
        $selling_point->image = $imageUrl;
        $selling_point->save();

        return redirect()->route('selling-point.index')->with('success', 'Selling Point created successfully.');
    }

    public function edit($id)
    {
        $selling_point = SellingPoint::find($id);
        return view('backend.layouts.selling_point.edit', compact('selling_point'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required',
            'description' => 'nullable',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:20480',
        ]);

        $selling_point = SellingPoint::find($id);
        $imageUrl = $selling_point->image;

        if ($request->hasFile('image')) {
            if ($selling_point->image && file_exists(public_path($selling_point->image))) {
                unlink(public_path($selling_point->image));
            }
            $image = $request->file('image');
            $imageName = 'selling_point_' . time() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('uploads/selling_point_image'), $imageName);
            $imageUrl = 'uploads/selling_point_image/' . $imageName;
        }

        $selling_point->name = $request->name;
        $selling_point->description = $request->description;
        $selling_point->image = $imageUrl;
        $selling_point->save();

        return redirect()->route('selling-point.index')->with('success', 'Selling Point updated successfully.');
    }

    public function updateStatus(Request $request, $id)
    {
        $selling_point = SellingPoint::findOrFail($id);
        $selling_point->status = $request->status;
        $selling_point->save();

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully!',
        ]);
    }

    public function destroy($id)
    {
        $selling_point = SellingPoint::find($id);
        if ($selling_point->image && file_exists(public_path($selling_point->image))) {
            unlink(public_path($selling_point->image));
        }
        $selling_point->delete();
        return redirect()->route('selling-point.index')->with('success', 'Selling Point deleted successfully.');
    }
}
