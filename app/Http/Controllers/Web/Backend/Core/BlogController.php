<?php

namespace App\Http\Controllers\Web\Backend\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Blog;
use Yajra\DataTables\Facades\DataTables;

class BlogController extends Controller
{
    public function index()
    {
        try {
            return view('backend.layouts.blog.index');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while loading the Blog page: ' . $e->getMessage());
        }
    }

    public function getData()
    {
        try {
            $blogs = Blog::latest()->get();
            return DataTables::of($blogs)
                ->addIndexColumn()
                ->editColumn('short_description', function ($row) {
                    return \Illuminate\Support\Str::limit(strip_tags($row->short_description), 50);
                })
                ->addColumn('image', function ($row) {
                    if ($row->image) {
                        return '<img src="' . asset('uploads/blogs/' . $row->image) . '" alt="Blog Image" width="50">';
                    }
                    return '-';
                })
                ->addColumn('featured', function ($row) {
                    return (int) $row->featured;
                })
                ->addColumn('action', function ($row) {
                    return "
                        <a href='" . route('blog.edit', $row->id) . "' class='btn btn-sm btn-icon btn-warning me-1' title='Edit'>
                            <i class='fa fa-edit'></i> 
                        </a>
                        <button class='btn btn-sm btn-icon btn-danger delete-blog' data-id='{$row->id}' title='Delete'>
                            <i class='fa fa-trash'></i> 
                        </button>
                    ";
                })
                ->rawColumns(['image', 'featured', 'action'])
                ->make(true);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while fetching Blog data: ' . $e->getMessage()], 500);
        }
    }

    public function create()
    {
        try {
            return view('backend.layouts.blog.create');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while loading the Blog creation page: ' . $e->getMessage());
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
            ]);

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('uploads/blogs'), $filename);
            } else {
                $filename = null;
            }

            $blog = new Blog();
            $blog->title = $request->title;
            $blog->short_description = strip_tags($request->short_description);
            $blog->long_description = strip_tags($request->long_description);
            $blog->image = $filename;
            $blog->status = 1; // Default active; controlled from index page
            $blog->published_at = $request->published_at;
            $blog->save();

            return redirect()->route('blog.index')->with('success', 'Blog created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while creating the Blog: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        try {
            $blog = Blog::findOrFail($id);
            return view('backend.layouts.blog.edit', compact('blog'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while loading the Blog edit page: ' . $e->getMessage());
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
                'status' => 'nullable|integer|in:0,1',
                'published_at' => 'nullable|date',
            ]);

            $blog = Blog::findOrFail($id);

            if ($request->hasFile('image')) {
                if ($blog->image && file_exists(public_path('uploads/blogs/' . $blog->image))) {
                    unlink(public_path('uploads/blogs/' . $blog->image));
                }
                $file = $request->file('image');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('uploads/blogs'), $filename);
                $blog->image = $filename;
            }

            $blog->title = $request->title;
            $blog->short_description = strip_tags($request->short_description);
            $blog->long_description = strip_tags($request->long_description);
            // status is NOT updated here — controlled via index page toggle
            $blog->published_at = $request->published_at;
            $blog->save();

            return redirect()->route('blog.index')->with('success', 'Blog updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while updating the Blog: ' . $e->getMessage());
        }
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $blog = Blog::findOrFail($id);
            $blog->status = $request->status;
            $blog->save();
            return response()->json(['success' => 'Blog status updated successfully.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while updating the Blog status: ' . $e->getMessage()], 500);
        }
    }

    public function updateFeatured(Request $request, $id)
    {
        try {
            $blog = Blog::findOrFail($id);
            $featured = (bool) $request->featured;

            \Illuminate\Support\Facades\DB::transaction(function () use ($blog, $featured) {
                if ($featured) {
                    Blog::where('id', '!=', $blog->id)->update(['featured' => false]);
                }
                $blog->featured = $featured;
                $blog->save();
            }, 3);

            return response()->json(['success' => 'Featured status updated successfully.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while updating the featured status: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $blog = Blog::findOrFail($id);
            if ($blog->image && file_exists(public_path('uploads/blogs/' . $blog->image))) {
                unlink(public_path('uploads/blogs/' . $blog->image));
            }
            $blog->delete();
            return response()->json(['success' => 'Blog deleted successfully.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while deleting the Blog: ' . $e->getMessage()], 500);
        }
    }

}
