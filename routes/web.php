<?php

use App\Http\Controllers\CommunityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ItineraryController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\ModerationController;
use App\Http\Controllers\PlaceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

// ------------------------------------------------------------------ Public
Route::get('/', [DashboardController::class, 'landing'])->name('home');

Route::get('/carte', [MapController::class, 'index'])->name('map.index');

Route::get('/parcours', [ItineraryController::class, 'create'])->name('itineraries.create');
Route::post('/parcours', [ItineraryController::class, 'store'])->name('itineraries.store');
Route::post('/parcours/ajouter-lieu/{place}', [ItineraryController::class, 'addPlace'])->name('itineraries.add-place');
Route::delete('/parcours/retirer-lieu/{place}', [ItineraryController::class, 'removePlace'])->name('itineraries.remove-place');
Route::post('/parcours/vider-lieux', [ItineraryController::class, 'clearPlaces'])->name('itineraries.clear-places');
Route::get('/parcours/suivre', [ItineraryController::class, 'navigate'])->name('itineraries.navigate');
Route::post('/parcours/variante/{key}', [ItineraryController::class, 'chooseVariant'])->name('itineraries.variant')->where('key', 'mix|culture|detente');
Route::post('/parcours/etape/{index}/retirer', [ItineraryController::class, 'editRemove'])->name('itineraries.step-remove')->whereNumber('index');
Route::post('/parcours/etape/{index}/deplacer', [ItineraryController::class, 'editMove'])->name('itineraries.step-move')->whereNumber('index');
Route::post('/parcours/etape/{index}/remplacer', [ItineraryController::class, 'editReplace'])->name('itineraries.step-replace')->whereNumber('index');
Route::post('/parcours/etape/{index}/verrouiller', [ItineraryController::class, 'editLock'])->name('itineraries.step-lock')->whereNumber('index');
Route::post('/parcours/etape/{index}/duree', [ItineraryController::class, 'editDuration'])->name('itineraries.step-duration')->whereNumber('index');
Route::get('/p/{token}', [ItineraryController::class, 'shared'])->name('itineraries.shared');
Route::post('/p/{token}/ouvrir', [ItineraryController::class, 'sharedOpen'])->name('itineraries.shared-open');
Route::get('/p/{token}/gpx', [ItineraryController::class, 'sharedGpx'])->name('itineraries.shared-gpx');

Route::get('/lieux/{place}', [PlaceController::class, 'show'])->name('places.show');
Route::post('/lieux/{place}/signaler', [PlaceController::class, 'report'])->name('places.report');

Route::get('/photos/{photo}', [CommunityController::class, 'showPhoto'])->name('photos.show');
Route::get('/avatar/{user}', [ProfileController::class, 'avatar'])->name('users.avatar');

// ------------------------------------------------------------------ Connecté
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/favoris', [PlaceController::class, 'favorites'])->name('places.favorites');
    Route::post('/lieux/{place}/favori', [PlaceController::class, 'toggleFavorite'])->name('places.toggle-favorite');
    Route::post('/lieux/{place}/avis', [ReviewController::class, 'store'])->name('places.reviews.store');

    Route::get('/mes-parcours', [ItineraryController::class, 'index'])->name('itineraries.index');
    Route::get('/mes-parcours/{itinerary}', [ItineraryController::class, 'show'])->name('itineraries.show');
    Route::post('/mes-parcours/{itinerary}/revoir', [ItineraryController::class, 'replay'])->name('itineraries.replay');
    Route::get('/mes-parcours/{itinerary}/suivre', [ItineraryController::class, 'navigateSaved'])->name('itineraries.navigate-saved');
    Route::post('/mes-parcours/{itinerary}/partager', [ItineraryController::class, 'share'])->name('itineraries.share');
    Route::get('/mes-parcours/{itinerary}/gpx', [ItineraryController::class, 'gpx'])->name('itineraries.gpx');
    Route::delete('/mes-parcours/{itinerary}', [ItineraryController::class, 'destroy'])->name('itineraries.destroy');

    // Communauté (façon Waze)
    Route::post('/lieux/{place}/alerte', [CommunityController::class, 'storeAlert'])->name('places.alerts.store');
    Route::post('/alertes', [CommunityController::class, 'storeAlert'])->name('alerts.store');
    Route::delete('/alertes/{alert}', [CommunityController::class, 'destroyAlert'])->name('alerts.destroy');
    Route::post('/lieux/{place}/photos', [CommunityController::class, 'storePhoto'])->name('places.photos.store');
    Route::post('/lieux/{place}/visite', [\App\Http\Controllers\VisitController::class, 'store'])->name('places.visit');
    Route::delete('/visites/{visit}', [\App\Http\Controllers\VisitController::class, 'destroy'])->name('visits.destroy');
    Route::get('/proposer-un-lieu', [CommunityController::class, 'createPlace'])->name('community.propose');
    Route::post('/proposer-un-lieu', [CommunityController::class, 'storePlace'])->name('community.propose.store');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// ------------------------------------------------------------------ Modération (admins)
Route::middleware(['auth', 'can:admin'])->prefix('moderation')->name('moderation.')->group(function () {
    Route::get('/', [ModerationController::class, 'index'])->name('index');
    Route::post('/lieux/{place}', [ModerationController::class, 'updatePlace'])->name('places.update');
    Route::post('/photos/{photo}', [ModerationController::class, 'updatePhoto'])->name('photos.update');
    Route::post('/alertes/{alert}/masquer', [ModerationController::class, 'hideAlert'])->name('alerts.hide');
    Route::delete('/signalements/{report}', [ModerationController::class, 'resolveReport'])->name('reports.resolve');
});

require __DIR__.'/auth.php';
