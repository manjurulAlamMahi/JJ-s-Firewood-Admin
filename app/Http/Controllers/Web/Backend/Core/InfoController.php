<?php

namespace App\Http\Controllers\Web\Backend\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Info;
use Illuminate\Support\Facades\Log;

class InfoController extends Controller
{
    public function index()
    {
        return view('backend.layouts.info.index');
    }

    public function getData()
    {
        $infos = Info::latest()->get();
        return datatables()->of($infos)
            ->addIndexColumn()
            ->addColumn('website', function ($row) {
                return $row->website ?? 'N/A';
            })
            ->make(true);
    }

    public function create()
    {
        return view('backend.layouts.info.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'phone' => 'required|string',
            'email' => 'required|email',
            'website' => 'nullable|url',
        ]);

        try {
            Info::create([
                'title' => $request->title,
                'phone' => $request->phone,
                'email' => $request->email,
                'website' => $request->website,
            ]);

            return redirect()->route('info.index')->with('success', 'Info created successfully.');
        } catch (\Exception $e) {
            Log::error('Info store error: ' . $e->getMessage() . ' on line ' . $e->getLine());
            return redirect()->back()->with('error', 'An error occurred while creating the info: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $info = Info::findOrFail($id);
        return view('backend.layouts.info.edit', compact('info'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'phone' => 'required|string',
            'email' => 'required|email',
            'website' => 'nullable|url',
        ]);

        try {
            $info = Info::findOrFail($id);
            $info->update([
                'title' => $request->title,
                'phone' => $request->phone,
                'email' => $request->email,
                'website' => $request->website,
            ]);

            return redirect()->route('info.index')->with('success', 'Info updated successfully.');
        } catch (\Exception $e) {
            Log::error('Info update error: ' . $e->getMessage() . ' on line ' . $e->getLine());
            return redirect()->back()->with('error', 'An error occurred while updating the info: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $info = Info::findOrFail($id);
            $info->delete();

            return redirect()->route('info.index')->with('success', 'Info deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Info delete error: ' . $e->getMessage() . ' on line ' . $e->getLine());
            return redirect()->back()->with('error', 'An error occurred while deleting the info: ' . $e->getMessage());
        }
    }
}
