<?php

namespace App\Http\Controllers;

use App\Models\Place;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    public function store(Request $request, Place $place)
    {
        $user = Auth::user();

        if (! $user) {
            abort(403);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string', 'max:1000'],
            'visited_at' => ['nullable', 'date'],
        ]);

        Review::create([
            'place_id' => $place->id,
            'user_id' => $user->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'],
            'visited_at' => $validated['visited_at'] ?? null,
        ]);

        return redirect()
            ->route('places.show', $place)
            ->withFragment('avis')
            ->with('status', __('Merci pour ton avis !'));
    }
}

