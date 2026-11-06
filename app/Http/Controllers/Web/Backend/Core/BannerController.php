<?php

namespace App\Http\Controllers\Web\Backend\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Banner;
use Yajra\DataTables\Facades\DataTables;


class BannerController extends Controller
{
    public function index(){
        return view('backend.layouts.banner.index');
    }

    public function getData()
    {
        $data = Banner::latest()->get();

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

    public function create(){
        return view('backend.layouts.banner.create');
    }

    public function store(Request $request){
        $request->validate([
            'title' => 'required|string|max:255',
            'sub_title' => 'required|string',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:20480',
        ]);

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = 'banner_' . time() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('uploads/banner_image'), $imageName);
            $imageUrl = 'uploads/banner_image/' . $imageName;
        }
        $banner = new Banner();
        $banner->title = $request->title;
        $banner->sub_title = $request->sub_title;
        $banner->image = $imageUrl;
        $banner->save();

        return redirect()->route('banner.index')->with('success', 'Banner created successfully.');
    }

    public function edit($id){
        $banner = Banner::find($id);
        return view('backend.layouts.banner.edit', compact('banner'));
    }


    public function update(Request $request, $id){
        $request->validate([
            'title' => 'required|string|max:255',
            'sub_title' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:20480',
        ]);

        $banner = Banner::findOrFail($id);
        $imageUrl = $banner->getRawOriginal('image');

        if ($request->hasFile('image')) {
            if ($banner->image && file_exists(public_path($banner->image))) {
                unlink(public_path($banner->image));
            }
            $image = $request->file('image');
            $imageName = 'banner_' . time() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('uploads/banner_image'), $imageName);
            $imageUrl = 'uploads/banner_image/' . $imageName;
        }

        $banner->title = $request->title;
        $banner->sub_title = $request->sub_title;
        $banner->image = $imageUrl;
        $banner->save();

        return redirect()->route('banner.index')->with('success', 'Banner updated successfully.');
    }

    public function UpdateStatus(Request $request){
        $banner = Banner::find($request->id);
        $banner->status = $request->status;
        $banner->save();

        return response()->json(['success' => 'Status updated successfully.']);
    }

    public function destroy($id){
        $banner = Banner::findOrFail($id);

        if ($banner->image && file_exists(public_path($banner->image))) {
            unlink(public_path($banner->image));
        }

        $banner->delete();

        return redirect()->route('banner.index')->with('success', 'Banner deleted successfully.');
    }

}
