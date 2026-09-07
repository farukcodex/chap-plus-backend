<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DistanceService
{
    /**
     * Calculate driving distance (in km) and duration (in minutes) between two coordinates
     * using the Google Distance Matrix API.
     *
     * @param float|null $originLat
     * @param float|null $originLng
     * @param float|null $destLat
     * @param float|null $destLng
     * @return array{distance_km: float, duration_minute: int}|null
     */
    public function calculate(?float $originLat, ?float $originLng, ?float $destLat, ?float $destLng): ?array
    {
        if (is_null($originLat) || is_null($originLng) || is_null($destLat) || is_null($destLng)) {
            return null;
        }

        $apiKey = config('services.google_maps.api_key');

        if (empty($apiKey)) {
            Log::info('Google Maps API key is missing. Skipping distance calculation.');
            return null;
        }

        try {
            $response = Http::timeout(5)->get('https://maps.googleapis.com/maps/api/distancematrix/json', [
                'origins' => "{$originLat},{$originLng}",
                'destinations' => "{$destLat},{$destLng}",
                'mode' => 'driving',
                'key' => $apiKey,
            ]);

            if (!$response->successful()) {
                Log::warning('Google Distance Matrix API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

            $data = $response->json();

            if (($data['status'] ?? '') !== 'OK') {
                Log::warning('Google Distance Matrix returned non-OK status', ['status' => $data['status'] ?? null]);
                return null;
            }

            $element = $data['rows'][0]['elements'][0] ?? null;

            if (!$element || ($element['status'] ?? '') !== 'OK') {
                Log::warning('Google Distance Matrix element status not OK', ['element' => $element]);
                return null;
            }

            $meters = $element['distance']['value'] ?? 0;
            $seconds = $element['duration']['value'] ?? 0;

            return [
                'distance_km' => round($meters / 1000, 2),
                'duration_minute' => (int) round($seconds / 60),
            ];
        } catch (\Exception $e) {
            Log::error('Distance calculation error: ' . $e->getMessage());
            return null;
        }
    }
}
