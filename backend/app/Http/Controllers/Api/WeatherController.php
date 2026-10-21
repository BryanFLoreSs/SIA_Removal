<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WeatherController extends Controller
{
    public function show(Request $request)
    {
        $data = $request->validate(['city' => ['required', 'string', 'max:100']]);
        $key = config('services.openweather.key');
        if (!$key) return response()->json(['message' => 'Weather service is not configured.'], 503);
        $response = Http::timeout(8)->get('https://api.openweathermap.org/data/2.5/weather', [
            'q' => $data['city'], 'appid' => $key, 'units' => 'metric',
        ]);
        if (!$response->successful()) return response()->json(['message' => 'Weather is temporarily unavailable.'], 502);
        $weather = $response->json();
        return response()->json(['location' => $weather['name'] ?? $data['city'], 'temperature_c' => data_get($weather, 'main.temp'), 'condition' => data_get($weather, 'weather.0.description'), 'humidity' => data_get($weather, 'main.humidity'), 'wind_mps' => data_get($weather, 'wind.speed')]);
    }
}
