<?php

namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use App\Models\WeatherCampaign;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class WeatherCampaignController extends Controller
{
    use ApiResponse;

    /**
     * Active, currently weather-matched campaign banners for the app.
     * Reads the cache written by the weather-campaigns:process schedule
     * (WeatherCampaign::last_matched_zip_codes) rather than calling the
     * weather API live on every app request.
     */
    public function active(Request $request)
    {
        $postcode = trim((string) $request->query('postcode', ''));

        $campaigns = WeatherCampaign::where('status', 'active')
            ->whereJsonContains('channels', 'banner')
            ->whereNotNull('last_evaluated_at')
            ->get()
            ->filter(function (WeatherCampaign $campaign) use ($postcode) {
                $matched = $campaign->last_matched_zip_codes ?? [];
                if (empty($matched)) {
                    return false;
                }

                return $postcode === '' || in_array($postcode, $matched, true);
            })
            ->values()
            ->map(function (WeatherCampaign $campaign) {
                $banner = $campaign->channel_content['banner'] ?? [];
                
                $imageUrl = $banner['image_url'] ?? $campaign->banner_image;

                return [
                    'id' => $campaign->id,
                    'title' => $banner['heading'] ?? $campaign->title,
                    'message' => $banner['message'] ?? $campaign->message,
                    'banner_image' => $imageUrl ? url($imageUrl) : null,
                    'cta_text' => $banner['cta_text'] ?? $campaign->banner_cta_text,
                    'cta_link' => $banner['cta_link'] ?? $campaign->banner_cta_link,
                ];
            });

        return $this->success($campaigns, 'Active weather campaigns retrieved successfully.');
    }

    /**
     * Single active campaign, for the Campaign Landing screen reached via
     * the `campaign` deep-link screen value.
     */
    public function show(Request $request, $id)
    {
        $campaign = WeatherCampaign::where('status', 'active')->find($id);

        if (!$campaign) {
            return $this->error('Campaign not found.', 404);
        }

        $banner = $campaign->channel_content['banner'] ?? [];
        $imageUrl = $banner['image_url'] ?? $campaign->banner_image;

        return $this->success([
            'id' => $campaign->id,
            'title' => $banner['heading'] ?? $campaign->title,
            'message' => $banner['message'] ?? $campaign->message,
            'banner_image' => $imageUrl ? url($imageUrl) : null,
            'cta_text' => $banner['cta_text'] ?? $campaign->banner_cta_text,
            'cta_link' => $banner['cta_link'] ?? $campaign->banner_cta_link,
            'active_from' => $campaign->active_from,
            'active_until' => $campaign->active_until,
        ], 'Campaign retrieved successfully.');
    }
}
