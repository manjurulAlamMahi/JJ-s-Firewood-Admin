<?php

namespace App\Http\Controllers\Web\Backend\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ContactUs;
use Yajra\DataTables\Facades\DataTables;

class ContactUsController extends Controller
{
    public function index()
    {
        try {
            return view('backend.layouts.contact-us.index');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while loading the Contact Us page: ' . $e->getMessage());
        }
    }

    public function getData()
    {
        try {
            $contactUsEntries = ContactUs::latest()->get();
            return DataTables::of($contactUsEntries)
                ->addIndexColumn()
                ->editColumn('message', function ($row) {
                    return \Illuminate\Support\Str::limit(strip_tags($row->message), 50);
                })
                ->addColumn('attachment', function ($row) {
                    if ($row->attachment) {
                        return '<a href="' . asset($row->attachment) . '" target="_blank">View</a>';
                    }
                    return '-';
                })
                ->rawColumns(['attachment', 'action'])
                ->make(true);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while fetching Contact Us data: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $contactUs = ContactUs::findOrFail($id);
            return view('backend.layouts.contact-us.show', compact('contactUs'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while loading the details page: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try 
        {
            $contactUs = ContactUs::findOrFail($id);
            $contactUs->delete();
            return response()->json(['success' => 'Contact Us entry deleted successfully.']);
            } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while deleting the Contact Us entry: ' . $e->getMessage()], 500);
        }
    }


}
