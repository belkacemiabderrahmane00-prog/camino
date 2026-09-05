<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Category;
use App\Models\User;
use App\Services\UserPreferenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /** Paliers de niveau (points cumulés). */
    private const LEVELS = [
        [0, 'Curieux', 'explore'],
        [30, 'Flâneur', 'directions_walk'],
        [80, 'Explorateur', 'hiking'],
        [180, 'Guide local', 'tour'],
        [400, 'Légende du quartier', 'military_tech'],
    ];

    public function edit(Request $request, UserPreferenceService $preferences): View
    {
        $user = $request->user();
        $stats = $this->stats($user);

        return view('profile.edit', [
            'user' => $user,
            'stats' => $stats,
            'level' => $this->level($stats['points']),
            'profile' => $preferences->profile($user),
            'categories' => Category::whereIn('slug', ['musee', 'monument', 'parc-jardin', 'lieu-culturel', 'street-art', 'evenement-culturel', 'restauration', 'itineraire'])->orderBy('name')->get(),
            'recentItineraries' => $user->itineraries()->latest()->take(3)->get(),
            'recentPhotos' => $user->photos()->with('place')->latest()->take(6)->get(),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'bio' => $data['bio'] ?? null,
            'city' => $data['city'] ?? null,
            'mobility' => $data['mobility'] ?? 'walk',
            'interests' => array_values($data['interests'] ?? []),
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if (! empty($data['remove_avatar'])) {
            $user->avatar = null;
            $user->avatar_mime = null;
        }

        if ($request->hasFile('avatar')) {
            $image = @imagecreatefromstring(file_get_contents($request->file('avatar')->getRealPath()));
            abort_if($image === false, 422, 'Image illisible.');
            // Recadrage carré centré + 512 px, JPEG.
            $w = imagesx($image);
            $h = imagesy($image);
            $side = min($w, $h);
            $square = imagecreatetruecolor(512, 512);
            imagecopyresampled($square, $image, 0, 0, (int) (($w - $side) / 2), (int) (($h - $side) / 2), 512, 512, $side, $side);
            ob_start();
            imagejpeg($square, null, 85);
            $user->avatar = ob_get_clean();
            $user->avatar_mime = 'image/jpeg';
            imagedestroy($image);
            imagedestroy($square);
        }

        $user->save();

        return Redirect::route('profile.edit')->with('status', 'Profil mis à jour.');
    }

    public function avatar(User $user)
    {
        abort_unless($user->avatar_mime && $user->avatar, 404);

        return response($user->avatar, 200, [
            'Content-Type' => $user->avatar_mime,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /** @return array{itineraries:int,km:float,favorites:int,reviews:int,photos:int,alerts:int,places:int,points:int} */
    private function stats(User $user): array
    {
        $itineraries = $user->itineraries()->get(['result_json']);
        $km = round($itineraries->sum(fn ($i) => (float) ($i->result_json['total_distance_km'] ?? 0)), 1);
        $counts = [
            'itineraries' => $itineraries->count(),
            'km' => $km,
            'favorites' => $user->savedPlaces()->count(),
            'reviews' => $user->reviews()->count(),
            'photos' => $user->photos()->where('status', 'approved')->count(),
            'alerts' => $user->alerts()->count(),
            'places' => $user->submittedPlaces()->where('status', 'approved')->count(),
        ];
        $counts['points'] = (int) ($counts['itineraries'] * 10 + $counts['reviews'] * 5 + $counts['photos'] * 8 + $counts['alerts'] * 3 + $counts['favorites'] + $counts['places'] * 15 + $km);

        return $counts;
    }

    /** @return array{index:int,name:string,icon:string,points:int,next:?int,progress:int} */
    private function level(int $points): array
    {
        $current = 0;
        foreach (self::LEVELS as $i => [$threshold]) {
            if ($points >= $threshold) {
                $current = $i;
            }
        }
        $next = self::LEVELS[$current + 1][0] ?? null;
        $base = self::LEVELS[$current][0];

        return [
            'index' => $current + 1,
            'name' => self::LEVELS[$current][1],
            'icon' => self::LEVELS[$current][2],
            'points' => $points,
            'next' => $next,
            'progress' => $next ? (int) round(($points - $base) / ($next - $base) * 100) : 100,
        ];
    }
}
