<?php

namespace App\Http\Controllers;

use App\Models\PlaceAlert;
use App\Services\WeatherService;

class MapController extends Controller
{
    public function index(WeatherService $weather)
    {
        $start = config('camino.default_start');

        return view('map.index', [
            'forecast' => $weather->forecast((float) $start['lat'], (float) $start['lng']),
            'alertTypes' => PlaceAlert::TYPES,
        ]);
    }
}
