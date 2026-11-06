<?php

namespace App\Http\Controllers\Web\Backend\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ZipCode;
use App\Models\ZipCodeGroup;
use App\Models\DeliveryType;
use App\Models\ZoneWiseDeliveryType;
use Yajra\DataTables\Facades\DataTables;

class ZipCodeController extends Controller
{

    public function index()
    {
        return view('backend.layouts.zip_code.index');
    }


    public function getGroupsData()
    {
        $data = ZipCodeGroup::withCount('zipCodes')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return DataTables::of($data)
            ->addIndexColumn()
            ->addColumn('zip_preview', function ($row) {
                return $row->zip_code_preview;
            })
            ->addColumn('zip_count', function ($row) {
                return $row->zip_codes_count;
            })
            ->addColumn('status', function ($row) {
                return (int) $row->status;
            })
            ->make(true);
    }


    public function createGroup()
    {
        $deliveryTypes = DeliveryType::where('status', 1)->get();
        return view('backend.layouts.zip_code.create', compact('deliveryTypes'));
    }


    public function storeGroup(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'zip_codes_input' => 'nullable|string',
            'delivery_fees' => 'required|array',
            'delivery_fees.*' => 'required|numeric|min:0',
        ]);

        $group = ZipCodeGroup::create([
            'name' => $request->name,
            'status' => 1,
        ]);

        // Save zone-wise delivery fees
        foreach ($request->delivery_fees as $typeId => $fee) {
            ZoneWiseDeliveryType::create([
                'zip_code_group_id' => $group->id,
                'delivery_type_id' => $typeId,
                'fee' => $fee,
            ]);
        }

        if ($request->filled('zip_codes_input')) {
            $result = $this->parseAndAddZipCodes($group->id, $request->zip_codes_input);
            return redirect()->route('zip-code.show', $group->id)
                ->with('success', "Group \"{$group->name}\" created with {$result['added']} zip codes." .
                    ($result['duplicates'] > 0 ? " {$result['duplicates']} duplicates skipped." : ''));
        }

        return redirect()->route('zip-code.show', $group->id)
            ->with('success', "Group \"{$group->name}\" created successfully.");
    }


    public function showGroup($id)
    {
        $group = ZipCodeGroup::findOrFail($id);
        return view('backend.layouts.zip_code.show', compact('group'));
    }


    public function getZipCodesData($groupId)
    {
        $data = ZipCode::where('group_id', $groupId)->orderBy('zip_code')->get();

        return DataTables::of($data)
            ->addIndexColumn()
            ->addColumn('status', function ($row) {
                return (int) $row->status;
            })
            ->make(true);
    }


    public function editGroup($id)
    {
        $group = ZipCodeGroup::with('zoneWiseDeliveryTypes')->findOrFail($id);
        $deliveryTypes = DeliveryType::where('status', 1)->get();

        // Map existing fees for easy access in view
        $existingFees = $group->zoneWiseDeliveryTypes->pluck('fee', 'delivery_type_id')->toArray();

        // Prepare existing zip codes as a string for the textarea
        $zipCodesString = $group->zipCodes()->orderBy('zip_code')->pluck('zip_code')->implode(', ');

        return view('backend.layouts.zip_code.edit', compact('group', 'deliveryTypes', 'existingFees', 'zipCodesString'));
    }


    public function updateGroup(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'sort_order' => 'nullable|integer',
            'status' => 'nullable|boolean',
            'zip_codes_input' => 'nullable|string',
            'delivery_fees' => 'required|array',
            'delivery_fees.*' => 'required|numeric|min:0',
        ]);

        $group = ZipCodeGroup::findOrFail($id);
        $group->name = $request->name;
        $group->sort_order = $request->sort_order;
        $group->status = $request->has('status') ? 1 : 0;
        $group->save();

        // Update zone-wise delivery fees
        foreach ($request->delivery_fees as $typeId => $fee) {
            ZoneWiseDeliveryType::updateOrCreate(
                ['zip_code_group_id' => $group->id, 'delivery_type_id' => $typeId],
                ['fee' => $fee]
            );
        }

        // Handle bulk postcode update if changed
        if ($request->filled('zip_codes_input')) {
            $this->parseAndAddZipCodes($group->id, $request->zip_codes_input);
        }

        return redirect()->route('zip-code.show', $group->id)
            ->with('success', "Group \"{$group->name}\" updated successfully.");
    }


    public function updateGroupStatus(Request $request, $id)
    {
        $group = ZipCodeGroup::findOrFail($id);
        $group->status = $request->status;
        $group->save();

        return response()->json([
            'success' => true,
            'message' => "Group \"{$group->name}\" " . ($request->status ? 'enabled' : 'disabled') . ' successfully!',
        ]);
    }


    public function destroyGroup($id)
    {
        $group = ZipCodeGroup::findOrFail($id);
        $name = $group->name;
        $group->delete();

        return response()->json(['success' => "Group \"{$name}\" deleted successfully."]);
    }


    public function addZipCodes(Request $request, $groupId)
    {
        $request->validate([
            'zip_codes_input' => 'required|string',
        ]);

        $group = ZipCodeGroup::findOrFail($groupId);
        $result = $this->parseAndAddZipCodes($groupId, $request->zip_codes_input);

        return response()->json([
            'success' => true,
            'message' => "{$result['added']} zip codes added to \"{$group->name}\"." .
                ($result['duplicates'] > 0 ? " {$result['duplicates']} duplicates skipped." : ''),
            'added' => $result['added'],
            'duplicates' => $result['duplicates'],
        ]);
    }


    public function updateZipCodeStatus(Request $request, $groupId, $zipId)
    {
        $zipCode = ZipCode::where('group_id', $groupId)->findOrFail($zipId);
        $zipCode->status = $request->status;
        $zipCode->save();

        return response()->json([
            'success' => true,
            'message' => "Zip code {$zipCode->zip_code} " . ($request->status ? 'enabled' : 'disabled') . ' successfully!',
        ]);
    }


    public function destroyZipCode($groupId, $zipId)
    {
        $zipCode = ZipCode::where('group_id', $groupId)->findOrFail($zipId);
        $code = $zipCode->zip_code;
        $zipCode->delete();

        return response()->json(['success' => "Zip code {$code} deleted successfully."]);
    }


    public function bulkDeleteZipCodes(Request $request, $groupId)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        $count = ZipCode::where('group_id', $groupId)
            ->whereIn('id', $request->ids)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "{$count} zip codes deleted successfully.",
        ]);
    }


    public function searchZipCodes(Request $request)
    {
        $query = $request->get('q', '');

        if (strlen($query) < 1) {
            return response()->json(['results' => []]);
        }

        $results = ZipCode::with('group')
            ->where('zip_code', 'like', "%{$query}%")
            ->orderBy('zip_code')
            ->limit(50)
            ->get()
            ->map(function ($zip) {
                return [
                    'id' => $zip->id,
                    'zip_code' => $zip->zip_code,
                    'status' => (int) $zip->status,
                    'group_id' => $zip->group_id,
                    'group_name' => $zip->group ? $zip->group->name : 'Ungrouped',
                    'group_status' => $zip->group ? (int) $zip->group->status : 1,
                ];
            });

        return response()->json(['results' => $results]);
    }


    public function getAllExisting()
    {
        $zipCodes = ZipCode::with('group')->get()->map(function ($zip) {
            return [
                'zip_code'   => $zip->zip_code,
                'status'     => (int) $zip->status,
                'group_id'   => $zip->group_id,
                'group_name' => $zip->group ? $zip->group->name : 'Ungrouped',
            ];
        });

        return response()->json($zipCodes);
    }


    public function getGroupsList()
    {
        return response()->json(
            ZipCodeGroup::orderBy('name')
                ->select('id', 'name', 'status')
                ->get()
        );
    }


    public function storeDeliveryFee(Request $request, $groupId)
    {
        return response()->json(['success' => true, 'message' => 'Delivery fee saved.']);
    }

    public function updateDeliveryFee(Request $request, $groupId, $pivotId)
    {
        return response()->json(['success' => true, 'message' => 'Delivery fee updated.']);
    }

    public function destroyDeliveryFee($groupId, $pivotId)
    {
        return response()->json(['success' => true, 'message' => 'Delivery fee removed.']);
    }


    private function parseAndAddZipCodes($groupId, $rawInput)
    {
        $codes = $this->parseZipCodeInput($rawInput);
        $existing = ZipCode::where('group_id', $groupId)
            ->whereIn('zip_code', $codes)
            ->pluck('zip_code')
            ->toArray();

        $newCodes = array_diff($codes, $existing);
        $added = 0;

        foreach ($newCodes as $code) {
            ZipCode::create([
                'zip_code' => $code,
                'status' => 1,
                'group_id' => $groupId,
            ]);
            $added++;
        }

        return [
            'added' => $added,
            'duplicates' => count($existing),
        ];
    }

    private function parseZipCodeInput($input)
    {
        $codes = [];

        $parts = preg_split('/[\s,]+/', trim($input));

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) continue;

            if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $part, $matches)) {
                $start = intval($matches[1]);
                $end = intval($matches[2]);

                if ($start > $end) {
                    list($start, $end) = [$end, $start];
                }

                if (($end - $start) > 500) {
                    $end = $start + 500;
                }

                for ($i = $start; $i <= $end; $i++) {
                    $codes[] = (string) $i;
                }
            } else {
                $cleaned = preg_replace('/[^a-zA-Z0-9]/', '', $part);
                if (!empty($cleaned)) {
                    $codes[] = $cleaned;
                }
            }
        }

        return array_unique($codes);
    }
}
