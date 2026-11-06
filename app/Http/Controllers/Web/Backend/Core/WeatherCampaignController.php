<?php

namespace App\Http\Controllers\Web\Backend\Core;

use App\Http\Controllers\Controller;
use App\Models\WeatherCampaign;
use App\Models\ZipCodeGroup;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class WeatherCampaignController extends Controller
{
    public function index()
    {
        return view('backend.layouts.weather_campaign.index');
    }

    public function getData()
    {
        $data = WeatherCampaign::with('zipGroup')->latest()->get();

        return DataTables::of($data)
            ->addIndexColumn()
            ->addColumn('target_area', function ($row) {
                return $row->zipGroup->name ?? 'All areas';
            })
            ->addColumn('channels', function ($row) {
                return collect($row->channels ?? [])
                    ->map(fn ($c) => '<span class="av-badge bg-secondary me-1">' . ucfirst($c) . '</span>')
                    ->implode('');
            })
            ->addColumn('status_badge', function ($row) {
                $badges = [
                    'draft' => 'secondary',
                    'pending_approval' => 'warning',
                    'approved' => 'info',
                    'active' => 'success',
                    'completed' => 'primary',
                    'cancelled' => 'danger',
                ];
                $color = $badges[$row->status] ?? 'secondary';
                $label = ucfirst(str_replace('_', ' ', $row->status));
                return '<span class="av-badge bg-' . $color . '">' . $label . '</span>';
            })
            ->addColumn('action', function ($row) {
                $options = ['draft', 'pending_approval', 'approved', 'active', 'completed', 'cancelled'];
                $select = '<select class="form-select form-select-sm status-dropdown d-inline-block w-auto" data-id="' . $row->id . '">';
                foreach ($options as $opt) {
                    $selected = $row->status === $opt ? 'selected' : '';
                    $select .= '<option value="' . $opt . '" ' . $selected . '>' . ucfirst(str_replace('_', ' ', $opt)) . '</option>';
                }
                $select .= '</select>';

                return '<div class="d-flex align-items-center flex-wrap gap-2">' . $select . '
                <a href="' . route('weather-campaigns.edit', $row->id) . '" class="btn btn-sm btn-info" aria-label="Edit">
                    <i class="fa fa-edit"></i>
                </a>

                <form action="' . route('weather-campaigns.destroy', $row->id) . '" method="POST" style="margin:0">
                    ' . csrf_field() . method_field('DELETE') . '
                    <button class="btn btn-sm btn-danger" onclick="return confirm(\'Delete this campaign?\')" aria-label="Delete">
                        <i class="fa fa-trash"></i>
                    </button>
                </form>
                </div>';
            })
            ->rawColumns(['channels', 'status_badge', 'action'])
            ->make(true);
    }

    public function create()
    {
        $zipGroups = ZipCodeGroup::orderBy('name')->get();
        return view('backend.layouts.weather_campaign.create', compact('zipGroups'));
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        $validated['created_by'] = auth()->id();
        $validated['status'] = 'draft';

        WeatherCampaign::create($validated);

        return redirect()->route('weather-campaigns.index')->with('success', 'Weather campaign created successfully');
    }

    public function edit($id)
    {
        $campaign = WeatherCampaign::findOrFail($id);
        $zipGroups = ZipCodeGroup::orderBy('name')->get();
        return view('backend.layouts.weather_campaign.edit', compact('campaign', 'zipGroups'));
    }

    public function update(Request $request, $id)
    {
        $campaign = WeatherCampaign::findOrFail($id);
        $campaign->update($this->validated($request));

        return redirect()->route('weather-campaigns.index')->with('success', 'Weather campaign updated successfully');
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|in:draft,pending_approval,approved,active,completed,cancelled']);

        $campaign = WeatherCampaign::findOrFail($id);
        
        // Basic workflow enforcement based on requires_approval
        if ($request->status === 'active' && $campaign->requires_approval && $campaign->status !== 'approved' && $campaign->status !== 'active') {
            return response()->json(['error' => 'Campaign requires approval before activation.'], 403);
        }

        $campaign->status = $request->status;
        $campaign->save();

        return response()->json(['success' => 'Status updated successfully.']);
    }

    public function destroy($id)
    {
        WeatherCampaign::findOrFail($id)->delete();
        return back()->with('success', 'Weather campaign deleted');
    }

    public function estimate(Request $request, \App\Services\WeatherCampaignAudienceService $audienceService)
    {
        $data = $this->validated($request);
        
        $campaign = new WeatherCampaign();
        $campaign->target_segments = $data['target_segments'] ?? null;
        $campaign->exclusions = $data['exclusions'] ?? null;
        $campaign->channels = $data['channels'] ?? [];
        $campaign->respects_marketing_optout = $data['respects_marketing_optout'] ?? true;
        
        try {
            $result = $audienceService->estimate($campaign);
            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function sendTest(Request $request, $id)
    {
        $campaign = WeatherCampaign::findOrFail($id);
        // Simulated test send for now
        return response()->json(['success' => 'Test successfully sent to your account (Simulated).']);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'campaign_type' => 'required|string|in:automated,one_off',
            'requires_approval' => 'nullable|boolean',
            'channels' => 'required|array|min:1',
            'channels.*' => 'in:push,email,banner',
            
            // Legacy fields fallback (optional depending on UI support)
            'title' => 'nullable|string|max:255',
            'message' => 'nullable|string',
            'banner_image' => 'nullable|string|max:255',
            'banner_cta_text' => 'nullable|string|max:255',
            'banner_cta_link' => 'nullable|string|max:255',
            'target_zip_group_id' => 'nullable|exists:zip_code_groups,id',
            
            // New JSON Fields
            'target_segments' => 'nullable|string',
            'exclusions' => 'nullable|string',
            'capacity_thresholds' => 'nullable|string',
            
            // Weather
            'temp_max_c' => 'nullable|numeric',
            'rain_probability_min' => 'nullable|numeric|min:0|max:100',
            'expected_rainfall_min_mm' => 'nullable|numeric|min:0',
            'condition_codes' => 'nullable|array',
            'condition_logic' => 'nullable|string|in:AND,OR',
            'forecast_days_out' => 'nullable|integer|min:1',
            'required_matching_days' => 'nullable|integer|min:1',
            
            // Scheduling
            'active_days' => 'nullable|array',
            'active_days.*' => 'integer|min:1|max:7',
            'active_from' => 'nullable|date',
            'active_until' => 'nullable|date|after_or_equal:active_from',
            'lead_time_hours' => 'nullable|integer|min:0',
            'sending_time_start' => 'nullable|date_format:H:i',
            'sending_time_end' => 'nullable|date_format:H:i',
            'respects_marketing_optout' => 'nullable|boolean',
            
            // Channel content array (from UI)
            'channel_content' => 'nullable|array',
        ]);

        return [
            'name' => $data['name'],
            'campaign_type' => $data['campaign_type'],
            'requires_approval' => $request->boolean('requires_approval', false),
            'channels' => $data['channels'],
            
            // Legacy Fields mapping to prevent breaking old apps
            'title' => $data['title'] ?? null,
            'message' => $data['message'] ?? null,
            'banner_image' => $data['banner_image'] ?? null,
            'banner_cta_text' => $data['banner_cta_text'] ?? null,
            'banner_cta_link' => $data['banner_cta_link'] ?? null,
            'target_zip_group_id' => $data['target_zip_group_id'] ?? null,
            
            // New JSON fields (decode if they are strings from hidden inputs)
            'target_segments' => !empty($data['target_segments']) ? json_decode($data['target_segments'], true) : null,
            'exclusions' => !empty($data['exclusions']) ? json_decode($data['exclusions'], true) : null,
            'capacity_thresholds' => !empty($data['capacity_thresholds']) ? json_decode($data['capacity_thresholds'], true) : null,
            
            'weather_condition' => array_filter([
                'temp_max_c' => $data['temp_max_c'] ?? null,
                'rain_probability_min' => $data['rain_probability_min'] ?? null,
                'expected_rainfall_min_mm' => $data['expected_rainfall_min_mm'] ?? null,
                'condition_codes' => $data['condition_codes'] ?? null,
                'condition_logic' => $data['condition_logic'] ?? 'AND',
                'forecast_days_out' => $data['forecast_days_out'] ?? null,
                'required_matching_days' => $data['required_matching_days'] ?? null,
            ], fn ($v) => $v !== null && $v !== []),
            
            'active_days' => $data['active_days'] ?? null,
            'active_from' => $data['active_from'] ?? null,
            'active_until' => $data['active_until'] ?? null,
            
            'lead_time_hours' => $data['lead_time_hours'] ?? 24,
            'sending_time_start' => $data['sending_time_start'] ?? null,
            'sending_time_end' => $data['sending_time_end'] ?? null,
            
            'respects_marketing_optout' => $request->boolean('respects_marketing_optout', true),
            'channel_content' => $data['channel_content'] ?? null,
        ];
    }
}
