<?php

use App\Http\Controllers\Api\V1\ItineraryController;
use App\Http\Controllers\Api\V1\PoiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('pois', [PoiController::class, 'index']);
    Route::get('poi/{id}', [PoiController::class, 'show']);

    Route::post('itineraries/generate', [ItineraryController::class, 'generate']);
});
