<?php

namespace App\Http\Controllers;

use App\Models\Itinerary;
use App\Models\Place;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Journal des visites : enregistré automatiquement par le guidage à l'arrivée, ou à la main depuis la fiche.
 */
class VisitController extends Controller
{
    public function store(Request $request, Place $place)
    {
        $data = $request->validate([
            'source' => ['nullable', 'in:guidage,manuel'],
            'minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'itinerary_id' => ['nullable', 'integer'],
        ]);
        $itineraryId = null;
        if (! empty($data['itinerary_id'])) {
            $itineraryId = Itinerary::where('user_id', Auth::id())->where('id', $data['itinerary_id'])->value('id');
        }

        // Une même arrivée signalée plusieurs fois (GPS qui oscille) ne compte qu'une fois par heure.
        $recent = Visit::where('user_id', Auth::id())->where('place_id', $place->id)
            ->where('visited_at', '>=', Carbon::now()->subHour())->exists();
        if (! $recent) {
            Visit::create([
                'user_id' => Auth::id(),
                'place_id' => $place->id,
                'itinerary_id' => $itineraryId,
                'source' => $data['source'] ?? 'manuel',
                'minutes' => $data['minutes'] ?? null,
                'visited_at' => Carbon::now(),
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'recorded' => ! $recent]);
        }

        return back()->with('status', $recent ? 'Visite déjà notée.' : 'Visite ajoutée à ton journal.');
    }

    public function destroy(Visit $visit)
    {
        abort_unless($visit->user_id === Auth::id(), 403);
        $visit->delete();

        return back()->with('status', 'Visite retirée du journal.');
    }
}
