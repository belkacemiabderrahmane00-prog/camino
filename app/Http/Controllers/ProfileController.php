<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Category;
use App\Models\User;
use App\Services\UserPreferenceService;
use App\Services\UserStatsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request, UserPreferenceService $preferences, UserStatsService $statsService): View
    {
        $user = $request->user();
        $stats = $statsService->stats($user);

        return view('profile.edit', [
            'user' => $user,
            'stats' => $stats,
            'level' => $statsService->level($stats['points']),
            'badges' => $statsService->badges($stats),
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

        if (! $request->hasFile('avatar') && $request->files->count() === 0 && str_starts_with((string) $request->header('Content-Type'), 'multipart/form-data') && (int) $request->header('Content-Length') > 4000) {
            // PHP n'a pas pu créer le fichier temporaire (dossier tmp non inscriptible) : on le dit au lieu d'ignorer.
            Log::warning('Upload avatar sans fichier reçu', ['files' => $_FILES, 'tmp' => sys_get_temp_dir(), 'length' => $request->header('Content-Length')]);

            return Redirect::route('profile.edit')->withErrors(['avatar' => 'La photo n\x27a pas pu être reçue par le serveur. Réessaie dans un instant.']);
        }

        if ($request->hasFile('avatar')) {
            $image = @imagecreatefromstring(file_get_contents($request->file('avatar')->getRealPath()));
            if ($image === false) {
                return Redirect::route('profile.edit')->withErrors(['avatar' => 'Photo illisible. Essaie un JPEG ou un PNG (les formats HEIC ne sont pas encore acceptés).']);
            }
            $image = $this->fixOrientation($image, $request->file('avatar')->getRealPath());
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

    /** Applique l'orientation EXIF (photos de téléphone prises en portrait). */
    private function fixOrientation(\GdImage $image, string $path): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data($path);
        $angle = match ((int) ($exif['Orientation'] ?? 1)) { 3 => 180, 6 => -90, 8 => 90, default => 0 };
        if ($angle !== 0) {
            $rotated = imagerotate($image, $angle, 0);
            if ($rotated !== false) {
                imagedestroy($image);
                return $rotated;
            }
        }

        return $image;
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

}
