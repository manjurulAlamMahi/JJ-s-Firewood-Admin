<?php

namespace App\Http\Controllers\Web\Backend\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EntryPackage;
use Yajra\DataTables\Facades\DataTables;

class EntryPackageController extends Controller
{

    public function getData()
    {
        $data = EntryPackage::latest()->get();

        return DataTables::of($data)
            ->addIndexColumn()
            ->addColumn('name', function ($row) {
                return $row->name;
            })
            ->addColumn('price', function ($row) {
                return number_format($row->price, 2);
            })
            ->addColumn('status', function ($row) {
                return (int) $row->status;
            })
            ->rawColumns(['status'])
            ->make(true);
    }

    public function index()
    {
        return view('backend.layouts.entry_package.index');
    }

    public function create()
    {
        return view('backend.layouts.entry_package.create');
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'price' => 'required|numeric|min:0',
                'entry_quantity' => 'required|integer|min:1',
                'description' => 'nullable|string',
            ]);

            $entryPackage = new EntryPackage();
            $entryPackage->name = $request->name;
            $entryPackage->price = $request->price;
            $entryPackage->entry_quantity = $request->input('entry_quantity', 1);
            $entryPackage->status = $request->input('status', 1);
            $entryPackage->description = $request->description;
            $entryPackage->save();

            return redirect()->route('entry-package.index')->with('success', 'Entry Package created successfully.');
        } catch (\Exception $e) {
            return redirect()->route('entry-package.index')->with('error', 'Failed to create Entry Package. Please try again.');
        }
    }

    public function edit($id)
    {
        $entryPackage = EntryPackage::findOrFail($id);
        return view('backend.layouts.entry_package.edit', compact('entryPackage'));
    }

    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'price' => 'required|numeric|min:0',
                'entry_quantity' => 'required|integer|min:1',
                'description' => 'nullable|string',
            ]);

            $entryPackage = EntryPackage::findOrFail($id);
            $entryPackage->name = $request->name;
            $entryPackage->price = $request->price;
            $entryPackage->status = $request->input('status', 1);
            $entryPackage->entry_quantity = $request->input('entry_quantity', 1);
            $entryPackage->description = $request->description;
            $entryPackage->save();

            return redirect()->route('entry-package.index')->with('success', 'Entry Package updated successfully.');
        } catch (\Exception $e) {
            return redirect()->route('entry-package.index')->with('error', 'Failed to update Entry Package. Please try again.');
        }
    }



    public function updateStatus(Request $request, $id)
    {
        $entryPackage = EntryPackage::findOrFail($id);
        $entryPackage->status = $request->status;
        $entryPackage->save();

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully!',
        ]);
    }

    public function destroy($id)
    {
        try {
            $entryPackage = EntryPackage::findOrFail($id);
            $entryPackage->delete();
            return redirect()->route('entry-package.index')->with('success', 'Entry Package deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->route('entry-package.index')->with('error', 'Failed to delete Entry Package. Please try again.');
        }
    }
}
