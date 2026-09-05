<?php

namespace App\Http\Controllers;

use App\Models\Place;

class MapController extends Controller
{
    public function index()
    {
        $places = Place::query()
            ->with('category')
            ->withAvg('reviews', 'rating')
            ->approved()
            ->latest()
            ->take(100)
            ->get();

        return view('map.index', [
            'places' => $places,
        ]);
    }
}
