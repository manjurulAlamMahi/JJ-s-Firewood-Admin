<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around the OpenWeatherMap API for weather-triggered campaigns.
 *
 * Geocoding (postcode -> lat/lon) is cached forever per postcode since
 * postcodes don't move. Forecasts are cached for a few hours so the
 * `weather-campaigns:process` schedule (which runs every 3 hours) doesn't
 * burn the free-tier rate limit, and so the public banner endpoint never
 * needs a live call (see WeatherCampaign::last_matched_zip_codes).
 */
class WeatherService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.openweathermap.base_url'), '/');
        $this->apiKey = (string) config('services.openweathermap.api_key');
    }

    /**
     * Get a multi-day forecast for an Australian postcode, aggregated by day.
     *
     * @return array<string, array>|null Map of Y-m-d => daily forecast
     */
    public function getMultiDayForecastForZip(string $zip, string $countryCode = 'AU'): ?array
    {
        if (empty($this->apiKey)) {
            Log::warning('WeatherService: OPENWEATHERMAP_API_KEY is not configured');
            return null;
        }

        $coords = $this->geocodeZip($zip, $countryCode);
        if (!$coords) {
            return null;
        }

        return $this->forecastForCoords($coords['lat'], $coords['lon']);
    }

    private function geocodeZip(string $zip, string $countryCode): ?array
    {
        $cacheKey = "weather:geocode:{$countryCode}:{$zip}";

        return Cache::rememberForever($cacheKey, function () use ($zip, $countryCode) {
            $res = Http::timeout(10)->get("{$this->baseUrl}/geo/1.0/zip", [
                'zip' => "{$zip},{$countryCode}",
                'appid' => $this->apiKey,
            ]);

            if (!$res->successful()) {
                Log::warning('WeatherService: geocode failed', [
                    'zip' => $zip,
                    'status' => $res->status(),
                ]);
                return null;
            }

            $body = $res->json();
            if (!isset($body['lat'], $body['lon'])) {
                return null;
            }

            return ['lat' => $body['lat'], 'lon' => $body['lon']];
        });
    }

    private function forecastForCoords(float $lat, float $lon): ?array
    {
        $roundedLat = round($lat, 2);
        $roundedLon = round($lon, 2);
        $cacheKey = "weather:forecast:{$roundedLat}:{$roundedLon}";

        return Cache::remember($cacheKey, now()->addHours(3), function () use ($lat, $lon) {
            $res = Http::timeout(10)->get("{$this->baseUrl}/data/2.5/forecast", [
                'lat' => $lat,
                'lon' => $lon,
                'units' => 'metric',
                'appid' => $this->apiKey,
            ]);

            if (!$res->successful()) {
                Log::warning('WeatherService: forecast fetch failed', [
                    'lat' => $lat,
                    'lon' => $lon,
                    'status' => $res->status(),
                ]);
                return null;
            }

            $list = $res->json('list') ?? [];
            if (empty($list)) {
                return null;
            }

            return $this->normalizeMultiDayForecast($list);
        });
    }

    /**
     * Reduce the 3-hourly forecast list down to an array of daily snapshots,
     * keyed by Y-m-d (UTC/local mix).
     */
    private function normalizeMultiDayForecast(array $list): array
    {
        $daily = [];
        $conditionPriority = ['Thunderstorm', 'Snow', 'Rain', 'Drizzle', 'Squall', 'Tornado'];

        foreach ($list as $entry) {
            if (!isset($entry['dt_txt'])) {
                continue;
            }

            $date = substr($entry['dt_txt'], 0, 10);
            
            if (!isset($daily[$date])) {
                $daily[$date] = [
                    'date' => $date,
                    'temp_max_c' => -999,
                    'temp_min_c' => 999,
                    'pop' => 0.0,
                    'expected_rainfall_min_mm' => 0.0,
                    'condition_main' => null,
                ];
            }

            $main = $entry['main'] ?? [];
            if (isset($main['temp_max'])) {
                $daily[$date]['temp_max_c'] = max($daily[$date]['temp_max_c'], $main['temp_max']);
            }
            if (isset($main['temp_min'])) {
                $daily[$date]['temp_min_c'] = min($daily[$date]['temp_min_c'], $main['temp_min']);
            }

            $daily[$date]['pop'] = max($daily[$date]['pop'], (float) ($entry['pop'] ?? 0));
            
            // Add rainfall if present (OpenWeatherMap returns rain.3h)
            if (isset($entry['rain']) && is_array($entry['rain']) && isset($entry['rain']['3h'])) {
                $daily[$date]['expected_rainfall_min_mm'] += (float) $entry['rain']['3h'];
            }

            $entryMain = $entry['weather'][0]['main'] ?? null;
            if ($entryMain) {
                if ($daily[$date]['condition_main'] === null) {
                    $daily[$date]['condition_main'] = $entryMain;
                } else {
                    $entryRank = array_search($entryMain, $conditionPriority, true);
                    $currentRank = array_search($daily[$date]['condition_main'], $conditionPriority, true);
                    $entryRank = $entryRank === false ? PHP_INT_MAX : $entryRank;
                    $currentRank = $currentRank === false ? PHP_INT_MAX : $currentRank;
                    if ($entryRank < $currentRank) {
                        $daily[$date]['condition_main'] = $entryMain;
                    }
                }
            }
        }

        foreach ($daily as $date => &$stats) {
            $stats['temp_max_c'] = $stats['temp_max_c'] === -999 ? null : round($stats['temp_max_c'], 1);
            $stats['temp_min_c'] = $stats['temp_min_c'] === 999 ? null : round($stats['temp_min_c'], 1);
            $stats['condition_main'] = $stats['condition_main'] ?? 'Unknown';
        }

        ksort($daily);
        return $daily;
    }
}
