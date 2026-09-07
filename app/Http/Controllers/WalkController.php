<?php

namespace App\Http\Controllers;

use App\Models\Itinerary;
use App\Models\Walk;
use App\Models\WalkMember;
use App\Models\WalkMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Balade à plusieurs : groupe éphémère (12 h) rejoint par lien ou QR, positions en direct, point de rendez-vous,
 * messages courts, réactions et photos. Les invités participent sans compte (pseudo + jeton en session).
 */
class WalkController extends Controller
{
    private const TTL_HOURS = 12;
    private const ARRIVE_M = 40;
    private const QUICK = ['👍', '❤️', '😂', '🏃', '☕', '📍', '🍽️', '⏳'];

    /** Crée une balade à partir du parcours en session ou d'un parcours enregistré. */
    public function store(Request $request)
    {
        $data = $request->validate(['itinerary_id' => ['nullable', 'integer'], 'title' => ['nullable', 'string', 'max:160']]);
        $result = null;
        $itineraryId = null;
        if (! empty($data['itinerary_id'])) {
            $itinerary = Itinerary::findOrFail((int) $data['itinerary_id']);
            abort_unless($itinerary->user_id === Auth::id() || $itinerary->share_token, 403);
            $result = $itinerary->result_json;
            $itineraryId = $itinerary->id;
        } else {
            $result = session('itinerary_result');
        }
        $route = $result ? [
            'title' => $result['title'] ?? null, 'start' => $result['start'] ?? null, 'end' => $result['end'] ?? null, 'geometry' => $result['geometry'] ?? [],
            'steps' => array_map(fn ($s) => ['lat' => $s['lat'], 'lng' => $s['lng'], 'title' => $s['title'], 'order' => $s['order'] ?? null, 'arrive_at' => $s['arrive_at'] ?? null, 'slug' => $s['category_slug'] ?? null], $result['steps'] ?? []),
        ] : null;
        $walk = Walk::create([
            'code' => Walk::newCode(),
            'title' => $data['title'] ?? ($route['title'] ?? __('Balade à plusieurs')),
            'itinerary_id' => $itineraryId,
            'route_json' => $route,
            'status' => 'active',
            'expires_at' => now()->addHours(self::TTL_HOURS),
        ]);
        $member = $this->join($walk, $request, Auth::user()?->name ?? __('Moi'));
        $walk->update(['host_member_id' => $member->id]);

        return redirect()->route('walks.show', $walk->code);
    }

    /** Page de la balade : formulaire d'entrée pour un nouveau venu, carte en direct pour un membre. */
    public function show(string $code, Request $request)
    {
        $walk = Walk::where('code', $code)->firstOrFail();
        $member = $this->member($walk, $request);
        if (! $member && Auth::check() && $walk->isActive()) {
            $member = $this->join($walk, $request, Auth::user()->name);
        }
        $membersOnline = $walk->members()->whereNull('left_at')->count();
        $guideUrl = null;
        if ($walk->itinerary_id && Auth::check() && $walk->itinerary?->user_id === Auth::id()) {
            $guideUrl = route('itineraries.navigate-saved', [$walk->itinerary_id, 'balade' => $walk->code]);
        } elseif (session('itinerary_result') && ($walk->route_json['title'] ?? null) === (session('itinerary_result')['title'] ?? '')) {
            $guideUrl = route('itineraries.navigate', ['balade' => $walk->code]);
        }

        return view('walks.show', [
            'guideUrl' => $guideUrl,
            'walk' => $walk,
            'member' => $member,
            'membersOnline' => $membersOnline,
            'quick' => self::QUICK,
            'palette' => Walk::PALETTE,
            'joinName' => Auth::user()?->name ?? '',
        ]);
    }

    public function joinRequest(string $code, Request $request)
    {
        $walk = Walk::where('code', $code)->firstOrFail();
        abort_unless($walk->isActive(), 410, __('Cette balade est terminée.'));
        $data = $request->validate(['name' => ['required', 'string', 'min:1', 'max:40'], 'color' => ['nullable', 'string', 'max:9']]);
        $this->join($walk, $request, trim($data['name']), $data['color'] ?? null);

        return redirect()->route('walks.show', $walk->code);
    }

    /** État complet ou incrémental (JSON) : membres, rendez-vous, messages depuis un identifiant. */
    public function state(string $code, Request $request)
    {
        $walk = Walk::where('code', $code)->firstOrFail();
        $me = $this->member($walk, $request);
        abort_unless($me, 403);
        $since = (int) $request->get('since', 0);
        $members = $walk->members()->whereNull('left_at')->orderBy('id')->get();
        $messages = $walk->messages()->with('member:id,name,color')->where('id', '>', $since)->orderBy('id')->limit(60)->get();

        return response()->json([
            'walk' => ['code' => $walk->code, 'title' => $walk->title, 'status' => $walk->isActive() ? 'active' : 'ended', 'host' => $walk->host_member_id, 'expires_at' => $walk->expires_at?->toIso8601String()],
            'me' => $me->id,
            'meeting' => $walk->meeting_lat !== null ? ['lat' => $walk->meeting_lat, 'lng' => $walk->meeting_lng, 'label' => $walk->meeting_label, 'by' => $walk->meeting_by, 'at' => $walk->meeting_at?->toIso8601String()] : null,
            'members' => $members->map(fn (WalkMember $m) => [
                'id' => $m->id, 'name' => $m->name, 'initials' => $m->initials(), 'color' => $m->color, 'user_id' => $m->user_id,
                'avatar' => $m->user?->avatar_mime ? route('users.avatar', [$m->user, 'v' => $m->user->updated_at?->timestamp]) : null,
                'lat' => $m->lat, 'lng' => $m->lng, 'heading' => $m->heading, 'accuracy' => $m->accuracy,
                'online' => $m->isOnline(), 'seen_at' => $m->seen_at?->toIso8601String(), 'arrived' => $m->arrived_at !== null,
            ])->values(),
            'messages' => $messages->map(fn (WalkMessage $msg) => [
                'id' => $msg->id, 'type' => $msg->type, 'body' => $msg->body, 'member' => $msg->member_id, 'name' => $msg->member?->name, 'color' => $msg->member?->color,
                'photo' => $msg->type === 'photo' ? route('walks.photo', [$walk->code, $msg->id]) : null, 'lat' => $msg->lat, 'lng' => $msg->lng, 'at' => $msg->created_at->toIso8601String(),
            ])->values(),
            'now' => now()->toIso8601String(),
        ]);
    }

    /** Position du membre (toutes les quelques secondes pendant la balade). */
    public function position(string $code, Request $request)
    {
        $walk = Walk::where('code', $code)->firstOrFail();
        $me = $this->member($walk, $request);
        abort_unless($me, 403);
        $data = $request->validate(['lat' => ['required', 'numeric', 'between:-90,90'], 'lng' => ['required', 'numeric', 'between:-180,180'], 'heading' => ['nullable', 'numeric'], 'accuracy' => ['nullable', 'numeric']]);
        $update = ['lat' => $data['lat'], 'lng' => $data['lng'], 'heading' => $data['heading'] ?? null, 'accuracy' => $data['accuracy'] ?? null, 'seen_at' => now()];
        // Arrivée au rendez-vous détectée côté serveur (annoncée à tout le groupe).
        if ($walk->meeting_lat !== null) {
            $d = $this->distance((float) $data['lat'], (float) $data['lng'], $walk->meeting_lat, $walk->meeting_lng);
            if ($d <= self::ARRIVE_M && $me->arrived_at === null) {
                $update['arrived_at'] = now();
                $walk->messages()->create(['member_id' => $me->id, 'type' => 'arrive', 'body' => null]);
            } elseif ($d > self::ARRIVE_M * 3 && $me->arrived_at !== null) {
                $update['arrived_at'] = null;
            }
        }
        $me->update($update);
        if ($walk->isActive() && $walk->expires_at && $walk->expires_at->lt(now()->addHours(2))) {
            $walk->update(['expires_at' => now()->addHours(self::TTL_HOURS)]);
        }

        return response()->json(['ok' => true, 'arrived' => $me->arrived_at !== null]);
    }

    public function message(string $code, Request $request)
    {
        $walk = Walk::where('code', $code)->firstOrFail();
        $me = $this->member($walk, $request);
        abort_unless($me && $walk->isActive(), 403);
        $data = $request->validate(['type' => ['required', 'in:text,emoji,ping'], 'body' => ['nullable', 'string', 'max:500']]);
        $body = trim((string) ($data['body'] ?? ''));
        if ($data['type'] !== 'ping' && $body === '') {
            return response()->json(['ok' => false], 422);
        }
        $msg = $walk->messages()->create(['member_id' => $me->id, 'type' => $data['type'], 'body' => $body ?: null, 'lat' => $me->lat, 'lng' => $me->lng]);

        return response()->json(['ok' => true, 'id' => $msg->id]);
    }

    /** Photo prise pendant la balade (redimensionnée, stockée en base comme les photos communautaires). */
    public function photo(string $code, Request $request)
    {
        $walk = Walk::where('code', $code)->firstOrFail();
        $me = $this->member($walk, $request);
        abort_unless($me && $walk->isActive(), 403);
        $request->validate(['photo' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:8192'], 'caption' => ['nullable', 'string', 'max:160']]);
        $file = $request->file('photo');
        $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $image) {
            return response()->json(['ok' => false, 'message' => __('Photo illisible.')], 422);
        }
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($file->getRealPath());
            $o = (int) ($exif['Orientation'] ?? 1);
            if (in_array($o, [3, 6, 8], true)) {
                $image = imagerotate($image, [3 => 180, 6 => -90, 8 => 90][$o], 0);
            }
        }
        $w = imagesx($image); $h = imagesy($image);
        $scale = min(1, 1400 / max($w, $h));
        $nw = max(1, (int) round($w * $scale)); $nh = max(1, (int) round($h * $scale));
        $resized = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);
        ob_start();
        imagejpeg($resized, null, 82);
        $data = (string) ob_get_clean();
        imagedestroy($image); imagedestroy($resized);
        $msg = $walk->messages()->create(['member_id' => $me->id, 'type' => 'photo', 'body' => $request->get('caption') ?: null, 'photo' => $data, 'photo_mime' => 'image/jpeg', 'photo_bytes' => strlen($data), 'lat' => $me->lat, 'lng' => $me->lng]);

        return response()->json(['ok' => true, 'id' => $msg->id, 'url' => route('walks.photo', [$walk->code, $msg->id])]);
    }

    public function showPhoto(string $code, WalkMessage $message)
    {
        abort_unless($message->type === 'photo' && $message->walk?->code === $code, 404);

        return response($message->photo, 200, ['Content-Type' => $message->photo_mime ?: 'image/jpeg', 'Content-Length' => $message->photo_bytes, 'Cache-Control' => 'public, max-age=2592000, immutable']);
    }

    /** Point de rendez-vous (n'importe quel membre peut le poser ou le retirer). */
    public function meeting(string $code, Request $request)
    {
        $walk = Walk::where('code', $code)->firstOrFail();
        $me = $this->member($walk, $request);
        abort_unless($me && $walk->isActive(), 403);
        if ($request->isMethod('delete')) {
            $walk->update(['meeting_lat' => null, 'meeting_lng' => null, 'meeting_label' => null, 'meeting_by' => null, 'meeting_at' => null]);
            $walk->members()->update(['arrived_at' => null]);
            $walk->messages()->create(['member_id' => $me->id, 'type' => 'meet', 'body' => null]);

            return response()->json(['ok' => true]);
        }
        $data = $request->validate(['lat' => ['required', 'numeric', 'between:-90,90'], 'lng' => ['required', 'numeric', 'between:-180,180'], 'label' => ['nullable', 'string', 'max:160']]);
        $label = trim((string) ($data['label'] ?? ''));
        $walk->update(['meeting_lat' => $data['lat'], 'meeting_lng' => $data['lng'], 'meeting_label' => $label ?: null, 'meeting_by' => $me->id, 'meeting_at' => now()]);
        $walk->members()->update(['arrived_at' => null]);
        $walk->messages()->create(['member_id' => $me->id, 'type' => 'meet', 'body' => $label ?: null, 'lat' => $data['lat'], 'lng' => $data['lng']]);

        return response()->json(['ok' => true]);
    }

    public function leave(string $code, Request $request)
    {
        $walk = Walk::where('code', $code)->firstOrFail();
        $me = $this->member($walk, $request);
        if ($me) {
            $me->update(['left_at' => now()]);
            $walk->messages()->create(['member_id' => $me->id, 'type' => 'leave']);
            $request->session()->forget('walk_member_' . $walk->id);
        }

        return redirect()->route(Auth::check() ? 'dashboard' : 'home')->with('status', __('Tu as quitté la balade.'));
    }

    public function end(string $code, Request $request)
    {
        $walk = Walk::where('code', $code)->firstOrFail();
        $me = $this->member($walk, $request);
        abort_unless($me && $me->id === $walk->host_member_id, 403);
        $walk->update(['status' => 'ended']);

        return redirect()->route('walks.show', $walk->code)->with('status', __('Balade terminée. Merci à tous !'));
    }

    // ---------------------------------------------------------------- helpers

    private function member(Walk $walk, Request $request): ?WalkMember
    {
        return $walk->memberFor($request);
    }

    private function join(Walk $walk, Request $request, string $name, ?string $color = null): WalkMember
    {
        $existing = $this->member($walk, $request);
        if ($existing) {
            return $existing;
        }
        $used = $walk->members()->pluck('color')->all();
        $free = array_values(array_diff(Walk::PALETTE, $used));
        $member = $walk->members()->create([
            'user_id' => Auth::id(),
            'token' => Str::random(40),
            'name' => Str::limit($name, 40, ''),
            'color' => $color && preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? $color : ($free[0] ?? Walk::PALETTE[count($used) % count(Walk::PALETTE)]),
            'seen_at' => now(),
        ]);
        $request->session()->put('walk_member_' . $walk->id, $member->token);
        $walk->messages()->create(['member_id' => $member->id, 'type' => 'join']);

        return $member;
    }

    private function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $x = deg2rad($lng2 - $lng1) * cos(deg2rad(($lat1 + $lat2) / 2));
        $y = deg2rad($lat2 - $lat1);

        return sqrt($x * $x + $y * $y) * 6371000;
    }
}
