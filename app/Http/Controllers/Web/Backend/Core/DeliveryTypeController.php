<?php

namespace App\Http\Controllers\Web\Backend\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DeliveryType;
use Yajra\DataTables\Facades\DataTables;

class DeliveryTypeController extends Controller
{
    public function index()
    {
        return view('backend.layouts.delivery_type.index');
    }

    public function getData()
    {
        $data = DeliveryType::latest()->get();

        return DataTables::of($data)
            ->addIndexColumn()
            ->addColumn('status', function ($row) {
                return (int) $row->status;
            })
            ->rawColumns(['status'])
            ->make(true);
    }

    public function create()
    {
        return view('backend.layouts.delivery_type.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'fee'   => 'required|numeric|min:0',
            'days'  => 'nullable|string|max:255',
        ]);

        $deliveryType = new DeliveryType();
        $deliveryType->name = $request->name;
        $deliveryType->fee = $request->fee;
        $deliveryType->days = $request->days;
        $deliveryType->save();

        return redirect()->route('delivery-type.index')->with('success', 'Delivery Type created successfully.');
    }


    public function edit($id)
    {
        $deliveryType = DeliveryType::find($id);
        return view('backend.layouts.delivery_type.edit', compact('deliveryType'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'fee'   => 'required|numeric|min:0',
            'days'  => 'nullable|string|max:255',
        ]);

        $deliveryType = DeliveryType::find($id);
        $deliveryType->name = $request->name;
        $deliveryType->fee = $request->fee;
        $deliveryType->days = $request->days;
        $deliveryType->save();

        return redirect()->route('delivery-type.index')->with('success', 'Delivery Type updated successfully.');
    }

    public function show($id)
    {
        $deliveryType = DeliveryType::find($id);
        return view('backend.layouts.delivery_type.show', compact('deliveryType'));
    }

    public function updateStatus(Request $request, $id)
    {
        $deliveryType = DeliveryType::find($id);
        $deliveryType->status = $request->status;
        $deliveryType->save();

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully!',
        ]);
    }

    public function destroy($id)
    {
        $deliveryType = DeliveryType::find($id);
        if ($deliveryType) {
            $deliveryType->delete();
            return redirect()->route('delivery-type.index')->with('success', 'Delivery Type deleted successfully.');
        }
        return redirect()->route('delivery-type.index')->with('error', 'Delivery Type not found.');
    }
}
