<?php

namespace App\Http\Controllers\Web\Backend\Core;

use App\Http\Controllers\Controller;
use App\Models\DynamicPages;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Exception;
use Yajra\DataTables\Facades\DataTables;

class DynamicPagesController extends Controller
{
    public function index()
    {

        return view('backend.layouts.dynamicpages.index');
    }

    public function getData()
    {
        $pages = DynamicPages::select(['id', 'title', 'page_content', 'status']);

        return DataTables::of($pages)
            ->addIndexColumn()
            ->addColumn('action', function ($row) {
                return '
                    <a href="' . route('dynamicpage.edit', $row->id) . '" class="btn btn-sm btn-warning">Edit</a>
                    <form action="' . route('dynamicpage.destroy', $row->id) . '" method="POST" style="display:inline-block;">
                        ' . csrf_field() . method_field('DELETE') . '
                        <button class="btn btn-sm btn-danger" onclick="return confirm(\'Delete this user?\')">Delete</button>
                    </form>
                ';
            })
            ->addColumn('status', function ($row) {
                if ($row->status == 'active') {
                    return '<span class="badge bg-success">Active</span>';
                } else {
                    return '<span class="badge bg-danger">Inactive</span>';
                }
            })
            ->rawColumns(['page_content', 'status', 'action'])
            ->make(true);
    }



    public function create()
    {
        return view('backend.layouts.dynamicpages.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'page_content' => 'required|string',
        ]);


        try {
            $dynamicpage                = new DynamicPages();
            $dynamicpage->title         = $request->input('title');
            $dynamicpage->page_content  = $request->input('page_content');
            $dynamicpage->slug          = Str::slug($request->input('title'));
            $dynamicpage->status        = 'active';
            $dynamicpage->save();

            return redirect()->route('dynamicpage.index')->with('success', 'Page created successfully.');
        } catch (Exception $e) {

            return redirect()->route('dynamicpage.index')->with('error', 'Page creation failed.');
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'page_content' => 'required|string',
        ]);

        try {
            $dynamicpage                = DynamicPages::findOrFail($id);
            $dynamicpage->title         = $request->input('title');
            $dynamicpage->page_content  = $request->input('page_content');
            $dynamicpage->slug          = Str::slug($request->input('title'));
            $dynamicpage->status        = 'active';
            $dynamicpage->save();

            return redirect()->route('dynamicpage.index')->with('success', 'Page updated successfully.');
        } catch (Exception $e) {

            return redirect()->route('dynamicpage.index')->with('error', 'Page update failed.');
        }
    }

    public function edit($id)
    {
        $dynamicpage = DynamicPages::findOrFail($id);
        return view('backend.layouts.dynamicpages.edit', compact('dynamicpage'));
    }

    public function show() {}

    public function destroy($id)
    {
        $dynamicpage = DynamicPages::findOrFail($id);
        $dynamicpage->delete();
        return redirect()->route('dynamicpage.index')->with('success', 'Page deleted successfully.');
    }
}
