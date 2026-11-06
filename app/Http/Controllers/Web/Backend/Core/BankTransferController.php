<?php

namespace App\Http\Controllers\Web\Backend\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BankTransfer;
use Yajra\DataTables\Facades\DataTables;

class BankTransferController extends Controller
{   

    public function getData()
    {
        $data = BankTransfer::latest()->get();

        return DataTables::of($data)
            ->addIndexColumn()
            ->addColumn('status', function ($row) {
                return (int) $row->status;
            })
            ->rawColumns(['status'])
            ->make(true);
            
    }

    public function index()
    {
        return view('backend.layouts.bank_transfer.index');
    }

    public function create()
    {
        return view('backend.layouts.bank_transfer.create');
    }


    public function store(Request $request)
    {
        try {
            $request->validate([
                'account_holder_name' => 'nullable|string|max:255',
                'account_number' => 'required|string|max:255',
                'bank_name' => 'nullable|string|max:255',
                'branch_name' => 'nullable|string|max:255',
                'note' => 'nullable|string|max:255',
            ]);

            $bankTransfer = new BankTransfer();
            $bankTransfer->account_holder_name = $request->account_holder_name;
            $bankTransfer->account_number = $request->account_number;
            $bankTransfer->bank_name = $request->bank_name;
            $bankTransfer->branch_name = $request->branch_name;
            $bankTransfer->note = $request->note;
            $bankTransfer->status = 1; 
            $bankTransfer->save();

            return redirect()->route('bank_transfer.index')->with('success', 'Bank Transfer created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while creating the Bank Transfer: ' . $e->getMessage());
        }
    }


    public function edit($id)
    {
        $bankTransfer = BankTransfer::findOrFail($id);
        return view('backend.layouts.bank_transfer.edit', compact('bankTransfer'));
    }

    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'account_holder_name' => 'nullable|string|max:255',
                'account_number' => 'required|string|max:255',
                'bank_name' => 'nullable|string|max:255',
                'branch_name' => 'nullable|string|max:255',
                'note' => 'nullable|string|max:255',
            ]);

            $bankTransfer = BankTransfer::findOrFail($id);
            $bankTransfer->account_holder_name = $request->account_holder_name;
            $bankTransfer->account_number = $request->account_number;
            $bankTransfer->bank_name = $request->bank_name;
            $bankTransfer->branch_name = $request->branch_name;
            $bankTransfer->note = $request->note;
            $bankTransfer->save();

            return redirect()->route('bank_transfer.index')->with('success', 'Bank Transfer updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while updating the Bank Transfer: ' . $e->getMessage());
        }
    }

    public function UpdateStatus(Request $request){
        try {
            $bankTransfer = BankTransfer::findOrFail($request->id);
            $bankTransfer->status = $request->status;
            $bankTransfer->save();

            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $bankTransfer = BankTransfer::findOrFail($id);
            $bankTransfer->delete();

            return redirect()->route('bank_transfer.index')->with('success', 'Bank Transfer deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while deleting the Bank Transfer: ' . $e->getMessage());
        }
    }
}
