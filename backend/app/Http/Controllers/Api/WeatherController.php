<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherController extends Controller
{
    public function show(Request $request)
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'city' => ['sometimes', 'string', 'max:100'],
        ]);
        $key = config('services.google_weather.key');
        if (!$key) return response()->json(['message' => 'Google Weather API is not configured on the backend.'], 503);

        try {
            $response = Http::timeout(8)->get('https://weather.googleapis.com/v1/currentConditions:lookup', [
                'key' => $key,
                'location.latitude' => $data['latitude'],
                'location.longitude' => $data['longitude'],
            ]);
        } catch (ConnectionException) {
            Log::warning('Google Weather API connection failed.');
            return response()->json(['message' => 'Could not connect to Google Weather API. Try again shortly.'], 503);
        }

        if (!$response->successful()) {
            $status = $response->status();
            Log::warning('Google Weather API request failed.', ['status' => $status]);
            $message = match ($status) {
                400 => 'Google Weather API rejected the location request.',
                401, 403 => 'Google Weather API rejected the key or is not enabled for this Google Cloud project. Check API access and billing.',
                429 => 'Google Weather API request limit reached. Try again later.',
                default => 'Google Weather API is temporarily unavailable.',
            };
            return response()->json(['message' => $message], 502);
        }

        $weather = $response->json();
        $windSpeed = (float) data_get($weather, 'wind.speed.value', 0);
        $windUnit = data_get($weather, 'wind.speed.unit', 'KILOMETERS_PER_HOUR');
        $windMps = match ($windUnit) {
            'MILES_PER_HOUR' => $windSpeed * 0.44704,
            'KILOMETERS_PER_HOUR' => $windSpeed / 3.6,
            default => $windSpeed,
        };

        return response()->json([
            'location' => $data['city'] ?? 'Manila',
            'temperature_c' => data_get($weather, 'temperature.degrees'),
            'feels_like_c' => data_get($weather, 'feelsLikeTemperature.degrees'),
            'condition' => data_get($weather, 'weatherCondition.description.text', 'Current conditions'),
            'humidity' => data_get($weather, 'relativeHumidity'),
            'wind_mps' => $windMps,
        ]);
    }
}
