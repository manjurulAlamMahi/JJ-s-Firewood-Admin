<?php

namespace App\Http\Controllers\Web\Backend\Core;

use App\Http\Controllers\Controller;
use App\Models\DeliveryType;
use App\Models\ZipCode;
use App\Services\OrderCalculationService;
use Illuminate\Http\Request;

class DeliveryCalculatorController extends Controller
{
    public function index()
    {
        $deliveryTypes = DeliveryType::all();
        return view('backend.layouts.delivery_calculator.index', compact('deliveryTypes'));
    }

    public function calculate(Request $request)
    {
        $request->validate([
            'postcode' => 'required|string',
            'delivery_type_id' => 'required|exists:delivery_types,id',
        ]);

        $deliverable = ZipCode::where('zip_code', $request->postcode)
            ->isDeliverable()
            ->exists();

        $resolved = OrderCalculationService::resolveDeliveryFee(
            $request->postcode,
            (int) $request->delivery_type_id
        );

        return response()->json([
            'deliverable' => $deliverable,
            'fee' => $resolved['fee'],
            'zone_matched' => $resolved['zone_matched'],
            'delivery_type' => $resolved['delivery_type'],
        ]);
    }
}
