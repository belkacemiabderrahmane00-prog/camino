<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WeatherController extends Controller
{
    /** GET /api/v1/weather?lat&lng */
    public function show(Request $request, WeatherService $weather): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);
        $default = config('camino.default_start');

        $forecast = $weather->forecast((float) ($data['lat'] ?? $default['lat']), (float) ($data['lng'] ?? $default['lng']));

        return response()->json([
            'current' => $forecast['current'],
            'days' => $forecast['days'],
            'hours' => array_slice($forecast['hours'], 0, 24),
            'available' => $forecast['available'],
            'error' => $forecast['available'] ? null : $weather->lastError,
        ]);
    }
}
