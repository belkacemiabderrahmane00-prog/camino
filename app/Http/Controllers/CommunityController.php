<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Place;
use App\Models\PlaceAlert;
use App\Models\PlacePhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Contributions communautaires (brief client, "comme Waze") :
 * alertes (événement gratuit, affluence, fermeture, bon plan), photos, proposition de lieu.
 */
class CommunityController extends Controller
{
    // ---------------------------------------------------------------- Alertes

    public function storeAlert(Request $request, ?Place $place = null)
    {
        $data = $request->validate([
            'type' => ['required', 'in:' . implode(',', array_keys(PlaceAlert::TYPES))],
            'title' => ['required', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:500'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'starts_at' => ['nullable', 'date'],
            'duration_hours' => ['nullable', 'integer', 'min:1', 'max:72'],
        ]);

        $lat = $place?->lat ?? ($data['lat'] ?? null);
        $lng = $place?->lng ?? ($data['lng'] ?? null);
        abort_if($lat === null || $lng === null, 422, 'Position manquante.');

        $hours = (int) ($data['duration_hours'] ?? PlaceAlert::TYPES[$data['type']]['hours']);

        // Anti-spam léger : 10 alertes actives max par utilisateur.
        abort_if(PlaceAlert::active()->where('user_id', Auth::id())->count() >= 10, 429, 'Trop d\'alertes actives.');

        $alert = PlaceAlert::create([
            'place_id' => $place?->id,
            'user_id' => Auth::id(),
            'type' => $data['type'],
            'title' => $data['title'],
            'message' => $data['message'] ?? null,
            'lat' => $lat,
            'lng' => $lng,
            'starts_at' => $data['starts_at'] ?? now(),
            'expires_at' => now()->addHours($hours),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['data' => $this->alertPayload($alert)], 201);
        }

        return back()->with('status', __('Merci ! Ton alerte est visible sur la carte pendant :h h.', ['h' => $hours]));
    }

    public function destroyAlert(PlaceAlert $alert)
    {
        abort_unless($alert->user_id === Auth::id() || Auth::user()?->is_admin, 403);
        $alert->update(['status' => 'hidden']);

        return back()->with('status', __('Alerte retirée.'));
    }

    // ---------------------------------------------------------------- Photos

    public function storePhoto(Request $request, Place $place)
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:8192'],
            'caption' => ['nullable', 'string', 'max:160'],
        ]);

        $file = $request->file('photo');
        $image = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        if ($image === false) {
            return back()->withErrors(['photo' => 'Photo illisible. Essaie un JPEG ou un PNG (les formats HEIC ne sont pas encore acceptés).']);
        }
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($file->getRealPath());
            $angle = match ((int) ($exif['Orientation'] ?? 1)) { 3 => 180, 6 => -90, 8 => 90, default => 0 };
            if ($angle !== 0 && ($rotated = imagerotate($image, $angle, 0)) !== false) {
                imagedestroy($image);
                $image = $rotated;
            }
        }

        // Redimensionnement : côté max 1400 px, JPEG qualité 82 (≈ 150–250 Ko).
        $w = imagesx($image);
        $h = imagesy($image);
        $max = 1400;
        $scale = min(1, $max / max($w, $h));
        $nw = (int) round($w * $scale);
        $nh = (int) round($h * $scale);
        $resized = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);
        ob_start();
        imagejpeg($resized, null, 82);
        $data = ob_get_clean();
        imagedestroy($image);
        imagedestroy($resized);

        $photo = PlacePhoto::create([
            'place_id' => $place->id,
            'user_id' => Auth::id(),
            'caption' => $request->input('caption'),
            'mime' => 'image/jpeg',
            'width' => $nw,
            'height' => $nh,
            'bytes' => strlen($data),
            'data' => $data,
            // Un admin publie directement ; sinon modération.
            'status' => Auth::user()?->is_admin ? 'approved' : 'pending',
        ]);

        if ($photo->status === 'approved' && ! $place->cover_image_url) {
            $place->update(['cover_image_url' => $photo->url, 'cover_image_source' => 'community', 'cover_image_author' => Auth::user()?->name]);
        }

        return back()->with('status', $photo->status === 'approved' ? 'Photo publiée, merci !' : 'Merci ! Ta photo sera publiée après validation.');
    }

    public function showPhoto(PlacePhoto $photo)
    {
        abort_unless($photo->status === 'approved' || Auth::user()?->is_admin || $photo->user_id === Auth::id(), 404);

        return response($photo->data, 200, [
            'Content-Type' => $photo->mime,
            'Content-Length' => $photo->bytes,
            'Cache-Control' => 'public, max-age=2592000, immutable',
        ]);
    }

    // ---------------------------------------------------------------- Proposition de lieu

    public function createPlace()
    {
        return view('community.propose', [
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function storePlace(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'category_id' => ['required', 'exists:categories,id'],
            'description' => ['required', 'string', 'min:20', 'max:2000'],
            'address' => ['nullable', 'string', 'max:255'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'is_free' => ['nullable', 'boolean'],
            'price_level' => ['nullable', 'integer', 'min:1', 'max:3'],
            'visit_duration_min' => ['nullable', 'integer', 'min:10', 'max:480'],
            'tags' => ['nullable', 'string', 'max:200'],
        ]);

        $place = Place::create([
            'title' => $data['title'],
            'slug' => Str::slug($data['title']) . '-' . Str::lower(Str::random(5)),
            'description' => $data['description'],
            'category_id' => $data['category_id'],
            'lat' => $data['lat'],
            'lng' => $data['lng'],
            'address' => $data['address'] ?? null,
            'status' => Auth::user()?->is_admin ? 'approved' : 'pending',
            'is_free' => ! empty($data['is_free']),
            'price_level' => empty($data['is_free']) ? ($data['price_level'] ?? null) : null,
            'visit_duration_min' => $data['visit_duration_min'] ?? 60,
            'tags' => array_values(array_filter(array_map('trim', explode(',', (string) ($data['tags'] ?? ''))))),
            'sources' => ['community'],
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('places.show', $place)
            ->with('status', $place->status === 'approved' ? 'Lieu publié !' : 'Merci ! Ton lieu sera visible après validation par l\'équipe.');
    }

    // ---------------------------------------------------------------- API

    public function alertsApi(Request $request)
    {
        $query = PlaceAlert::active()->with('place:id,title,slug')->latest();

        if ($bbox = $request->string('bbox')->toString()) {
            $c = array_map('floatval', explode(',', $bbox));
            if (count($c) === 4) {
                $query->whereBetween('lat', [$c[0], $c[2]])->whereBetween('lng', [$c[1], $c[3]]);
            }
        }

        return response()->json(['data' => $query->limit(100)->get()->map(fn (PlaceAlert $a) => $this->alertPayload($a))]);
    }

    private function alertPayload(PlaceAlert $alert): array
    {
        return [
            'id' => $alert->id,
            'type' => $alert->type,
            'label' => $alert->type_label,
            'icon' => $alert->type_icon,
            'color' => $alert->type_color,
            'title' => $alert->title,
            'message' => $alert->message,
            'lat' => $alert->lat,
            'lng' => $alert->lng,
            'place' => $alert->place ? ['id' => $alert->place->id, 'title' => $alert->place->title] : null,
            'expires_at' => $alert->expires_at?->toIso8601String(),
            'expires_in' => $alert->expires_at?->diffForHumans(null, true),
        ];
    }
}
