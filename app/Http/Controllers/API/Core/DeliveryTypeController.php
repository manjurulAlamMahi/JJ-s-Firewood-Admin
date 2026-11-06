<?php

namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DeliveryType;
use App\Traits\ApiResponse;

class DeliveryTypeController extends Controller
{

    use ApiResponse;

    public function index(Request $request)
    {
        try {
            $deliveryTypes = DeliveryType::where('status', 1)->get()->map(function ($type) {
                return [
                    'id' => $type->id,
                    'name' => $type->name,
                    'fee' => $type->fee,
                    'days' => $type->days,
                ];
            });

            return $this->success($deliveryTypes, 'Delivery types retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('An error occurred while retrieving delivery types.', 500);
        }
    }

    public function zoneWiseDeliveryType(Request $request)
    {
        try {
            $postcode = $request->query('postcode');
            $shippingAddressId = $request->query('shipping_address_id');
            $zoneId = null;

            if ($shippingAddressId) {
                $address = \App\Models\ShippingAddress::find($shippingAddressId);
                if ($address && $address->zip_code) {
                    $postcode = $address->zip_code;
                }
            }

            if ($postcode) {
                $zipCode = \App\Models\ZipCode::where('zip_code', $postcode)->first();
                if ($zipCode) {
                    $zoneId = $zipCode->group_id;
                }
            }

            $deliveryTypes = DeliveryType::where('status', 1)->get()->map(function ($type) use ($zoneId) {
                $fee = $type->fee;

                if ($zoneId) {
                    $zoneFee = \App\Models\ZoneWiseDeliveryType::where('zip_code_group_id', $zoneId)
                        ->where('delivery_type_id', $type->id)
                        ->first();
                    
                    if ($zoneFee) {
                        $fee = $zoneFee->fee;
                    }
                }

                return [
                    'id' => $type->id,
                    'name' => $type->name,
                    'fee' => $fee,
                    'days' => $type->days,
                ];
            });

            return $this->success($deliveryTypes, 'Zone-wise delivery types retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('An error occurred while retrieving zone-wise delivery types.', 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'delivery_type_id' => 'required|exists:delivery_types,id',
        ]);

        try {
            $deliveryType = DeliveryType::find($request->delivery_type_id);

            return $this->success($deliveryType, 'Delivery type retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('An error occurred while retrieving delivery type.', 500);
        }
    }

    public function show($id)
    {
        try {
            $deliveryType = DeliveryType::findOrFail($id);

            return $this->success($deliveryType, 'Delivery type retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('An error occurred while retrieving delivery type.', 500);
        }
    }
}
