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
    // Adresse (BAN) + lieux CAMINO par nom : on peut partir d'une gare, d'un musée ou d'une rue.
    Route::get('geocode', function (\Illuminate\Http\Request $request, \App\Services\GeocodingService $geo) {
        $q = trim((string) $request->query('q', ''));
        $places = mb_strlen($q) >= 3 ? \App\Models\Place::visible()->search($q)->whereNotNull('lat')->orderByRaw('cover_image_url is null')->limit(3)->get()->map(fn ($p) => ['label' => $p->title . ($p->address ? ' · ' . \Illuminate\Support\Str::limit($p->address, 40, '') : ''), 'city' => null, 'type' => 'place', 'lat' => (float) $p->lat, 'lng' => (float) $p->lng])->all() : [];
        $addresses = $geo->search($q, $request->float('lat') ?: null, $request->float('lng') ?: null);

        return response()->json(array_slice(array_merge($places, $addresses), 0, 8));
    })->middleware('throttle:60,1');
    Route::get('geocode/reverse', function (\Illuminate\Http\Request $request, \App\Services\GeocodingService $geo) {
        return response()->json(['label' => $geo->reverse($request->float('lat'), $request->float('lng'))]);
    })->middleware('throttle:60,1');

    Route::post('itineraries/generate', [ItineraryController::class, 'generate']);

    // Recalcul d'un tronçon pendant le guidage (position actuelle → prochaine étape).
    Route::post('route', function (\Illuminate\Http\Request $request, \App\Services\RoutingService $routing) {
        $data = $request->validate([
            'points' => ['required', 'array', 'min:2', 'max:4'],
            'points.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'points.*.lng' => ['required', 'numeric', 'between:-180,180'],
            'mode' => ['nullable', 'in:walk,bike,transit'],
        ]);
        $route = $routing->route($data['points'], $data['mode'] ?? 'walk');

        return response()->json(['source' => $route['source'], 'distance_km' => $route['distance_km'], 'duration_min' => $route['duration_min'], 'legs' => $route['legs']]);
    })->middleware('throttle:30,1');

    // Diagnostic : dernières lignes du journal applicatif (clé dérivée de APP_KEY).
    Route::get('diag/php', function (\Illuminate\Http\Request $request) {
        abort_unless(hash_equals(substr(sha1((string) config('app.key')), 0, 16), (string) $request->query('key')), 404);
        $tmp = @tempnam(sys_get_temp_dir(), 'camino');
        $gd = function_exists('gd_info') ? gd_info() : [];

        return response()->json([
            'php' => PHP_VERSION,
            'user' => get_current_user(),
            'sys_temp_dir' => sys_get_temp_dir(),
            'upload_tmp_dir' => ini_get('upload_tmp_dir'),
            'tempnam_ok' => $tmp !== false && is_file($tmp),
            'file_uploads' => ini_get('file_uploads'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'gd_jpeg' => (bool) ($gd['JPEG Support'] ?? false),
            'gd_webp' => (bool) ($gd['WebP Support'] ?? false),
            'exif' => function_exists('exif_read_data'),
        ]);
    });
    Route::get('diag/log', function (\Illuminate\Http\Request $request) {
        abort_unless(hash_equals(substr(sha1((string) config('app.key')), 0, 16), (string) $request->query('key')), 404);
        $files = glob(storage_path('logs/laravel*.log')) ?: [];
        rsort($files);
        $lines = $files ? array_slice(file($files[0]), -150) : ['(aucun journal)'];

        return response(implode('', $lines), 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    });
});
