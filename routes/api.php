<?php

use App\Http\Controllers\Api\V1\ItineraryController;
use App\Http\Controllers\Api\V1\PoiController;
use App\Http\Controllers\Api\V1\WeatherController;
use App\Http\Controllers\CommunityController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('pois', [PoiController::class, 'index']);
    Route::get('poi/{id}', [PoiController::class, 'show']);
    Route::get('alerts', [CommunityController::class, 'alertsApi']);
    Route::get('weather', [WeatherController::class, 'show']);

    Route::post('itineraries/generate', [ItineraryController::class, 'generate']);

    // Diagnostic : dernières lignes du journal applicatif (clé dérivée de APP_KEY).
    Route::get('diag/log', function (\Illuminate\Http\Request $request) {
        abort_unless(hash_equals(substr(sha1((string) config('app.key')), 0, 16), (string) $request->query('key')), 404);
        $files = glob(storage_path('logs/laravel*.log')) ?: [];
        rsort($files);
        $lines = $files ? array_slice(file($files[0]), -150) : ['(aucun journal)'];

        return response(implode('', $lines), 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    });
});
