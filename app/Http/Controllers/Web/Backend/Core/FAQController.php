<?php

namespace App\Http\Controllers\Web\Backend\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use App\Models\FAQ;

class FAQController extends Controller
{
    public function index()
    {
        try {
            return view('backend.layouts.faq.index');
        }
        catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while loading the FAQ page: ' . $e->getMessage());
        }
    }

    public function getData()
    {
        try {
            $data = FAQ::latest()->get();

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('status', function ($row) {
                    return (int) $row->status;
                })
                ->rawColumns(['status'])
                ->make(true);
        }
        catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while fetching FAQ data: ' . $e->getMessage()], 500);
        }
    }

    public function create()
    {
        try {
            $categories = [
                'Orders & Delivery',
                'Firewood & Products',
                'Payments & Pricing',
                'Account & Rewards',
                'Giveaways & Promotions'
            ];
            return view('backend.layouts.faq.create', compact('categories'));
        }
        catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while loading the FAQ creation page: ' . $e->getMessage());
        }
    }


    public function store(Request $request)
    {
        try {
            $request->validate([
                'question' => 'required|string|max:255',
                'answer' => 'required|string',
                'status' => 'nullable|integer|in:0,1',
                'category' => 'nullable|string|max:255',
                'sort_order' => 'nullable|integer',
            ]);

            $faq = new FAQ();
            $faq->question = $request->question;
            $faq->answer = strip_tags($request->answer); 
            $faq->status = $request->status ?? 1;
            $faq->category = $request->category ?: null;
            $faq->sort_order = $request->sort_order ?? 0;
            $faq->save();

            return redirect()->route('faqs.index')->with('success', 'FAQ created successfully.');
        }
        catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while creating the FAQ: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        try {
            $faq = FAQ::findOrFail($id);
            $categories = [
                'Orders & Delivery',
                'Firewood & Products',
                'Payments & Pricing',
                'Account & Rewards',
                'Giveaways & Promotions'
            ];
            return view('backend.layouts.faq.edit', compact('faq', 'categories'));
        }
        catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while loading the FAQ edit page: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'question' => 'required|string|max:255',
                'answer' => 'required|string',
                'status' => 'nullable|integer|in:0,1',
                'category' => 'nullable|string|max:255',
                'sort_order' => 'nullable|integer',
            ]);

            $faq = FAQ::findOrFail($id);
            $faq->question = $request->question;
            $faq->answer = strip_tags($request->answer); 
            $faq->status = $request->status ?? 1;
            $faq->category = $request->category ?: null;
            $faq->sort_order = $request->sort_order ?? 0;
            $faq->save();

            return redirect()->route('faqs.index')->with('success', 'FAQ updated successfully.');
        }
        catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while updating the FAQ: ' . $e->getMessage());
        }
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $faq = FAQ::findOrFail($id);
            $faq->status = $request->status;
            $faq->save();

            return response()->json(['success' => 'FAQ status updated successfully.']);
        }
        catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while updating the FAQ status: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $faq = FAQ::findOrFail($id);
            $faq->delete();

            return redirect()->route('faqs.index')->with('success', 'FAQ deleted successfully.');
        }
        catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while deleting the FAQ: ' . $e->getMessage());
        }
    }

}