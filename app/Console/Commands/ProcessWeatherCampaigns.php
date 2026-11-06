<?php

namespace App\Console\Commands;

use App\Jobs\WeatherCampaignDispatchJob;
use App\Models\WeatherCampaign;
use App\Models\ZipCode;
use App\Services\WeatherService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessWeatherCampaigns extends Command
{
    protected $signature = 'weather-campaigns:process';

    protected $description = 'Evaluate active weather-triggered campaigns against current forecasts and dispatch matching sends';

    public function handle(WeatherService $weatherService, \App\Services\WeatherCampaignAudienceService $audienceService)
    {
        $today = now();
        $todayDate = $today->format('Y-m-d');

        $campaigns = WeatherCampaign::where('status', 'active')->get();

        if ($campaigns->isEmpty()) {
            $this->info('No active weather campaigns.');
            return 0;
        }

        foreach ($campaigns as $campaign) {
            if (!$campaign->isWithinActiveDateWindow($today) || !$campaign->isActiveOnDay($today)) {
                continue;
            }

            if (!$campaign->isWithinSendingTime($today)) {
                continue;
            }

            // 1. Resolve eligible audience
            $audience = $audienceService->resolve($campaign);
            if ($audience->isEmpty()) {
                continue;
            }

            // 2. Extract unique eligible-user postcodes
            $audience->load('shippingAddresses');
            $targetZipCodes = collect();
            foreach ($audience as $user) {
                foreach ($user->shippingAddresses as $address) {
                    if ($address->zip_code) {
                        $targetZipCodes->push($address->zip_code);
                    }
                }
            }
            $targetZipCodes = $targetZipCodes->unique()->values();

            if ($targetZipCodes->isEmpty()) {
                continue;
            }

            // 3. Evaluate weather only for those postcodes
            $matched = [];
            $lastForecast = [];

            $leadTimeDays = (int) round(($campaign->lead_time_hours ?? 0) / 24);

            foreach ($targetZipCodes as $zip) {
                $multiDayForecast = $weatherService->getMultiDayForecastForZip((string) $zip);
                if (!$multiDayForecast) {
                    continue;
                }

                $snapshot = $campaign->evaluateMultiDayWeather($multiDayForecast);
                
                if ($snapshot) {
                    // Check if today is the correct day to trigger based on lead time
                    $eventStartDate = $snapshot['date'];
                    $targetTriggerDate = \Illuminate\Support\Carbon::parse($eventStartDate)
                        ->subDays($leadTimeDays)
                        ->format('Y-m-d');

                    if ($targetTriggerDate === $todayDate) {
                        $matched[] = (string) $zip;
                        $lastForecast = $snapshot; // Use the matched event snapshot for interpolation
                    }
                }
            }

            $campaign->last_evaluated_at = $today;
            $campaign->last_matched_zip_codes = $matched;
            $campaign->save();

            if (empty($matched)) {
                continue;
            }

            $this->info("Weather campaign #{$campaign->id} ({$campaign->name}) matched " . count($matched) . ' zip code(s).');

            if (!empty(array_intersect($campaign->channels ?? [], ['push', 'email']))) {
                WeatherCampaignDispatchJob::dispatch($campaign->id, $matched, $todayDate, $lastForecast);

                Log::info('ProcessWeatherCampaigns: dispatched', [
                    'campaign_id' => $campaign->id,
                    'matched_zip_count' => count($matched),
                ]);
            }
        }

        $this->info('Finished evaluating weather campaigns.');
        return 0;
    }
}
