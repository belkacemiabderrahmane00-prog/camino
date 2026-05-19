<?php

use App\Http\Controllers\ItineraryController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\PlaceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home.landing');
});

Route::get('/carte', [MapController::class, 'index'])->name('map.index');
Route::get('/parcours', [ItineraryController::class, 'create'])->name('itineraries.create');
Route::post('/parcours', [ItineraryController::class, 'store'])->name('itineraries.store');
Route::post('/parcours/ajouter-lieu/{place}', [ItineraryController::class, 'addPlace'])->name('itineraries.add-place');
Route::delete('/parcours/retirer-lieu/{place}', [ItineraryController::class, 'removePlace'])->name('itineraries.remove-place');
Route::post('/parcours/vider-lieux', [ItineraryController::class, 'clearPlaces'])->name('itineraries.clear-places');

Route::get('/lieux/{place}', [PlaceController::class, 'show'])->name('places.show');
Route::post('/lieux/{place}/signaler', [PlaceController::class, 'report'])->name('places.report');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/favoris', [PlaceController::class, 'favorites'])->name('places.favorites');

    Route::post('/lieux/{place}/favori', [PlaceController::class, 'toggleFavorite'])
        ->name('places.toggle-favorite');

    Route::post('/lieux/{place}/avis', [ReviewController::class, 'store'])
        ->name('places.reviews.store');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
