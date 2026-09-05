<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Itinerary extends Model
{
    protected $fillable = ['user_id', 'name', 'result_json', 'share_token'];

    protected $casts = [
        'result_json' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Crée le lien public si besoin et le retourne. */
    public function shareUrl(): string
    {
        if (! $this->share_token) {
            $this->forceFill(['share_token' => Str::lower(Str::random(20))])->save();
        }

        return route('itineraries.shared', $this->share_token);
    }

    /** Export GPX : tracé + un waypoint par étape. */
    public function toGpx(): string
    {
        $r = $this->result_json ?? [];
        $esc = fn ($v) => htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<gpx version="1.1" creator="CAMINO" xmlns="http://www.topografix.com/GPX/1/1">' . "\n";
        $xml .= '  <metadata><name>' . $esc($this->name) . '</name><time>' . $this->created_at?->toIso8601String() . '</time></metadata>' . "\n";
        if (! empty($r['start'])) {
            $xml .= '  <wpt lat="' . $r['start']['lat'] . '" lon="' . $r['start']['lng'] . '"><name>' . $esc('Départ · ' . ($r['start']['label'] ?? '')) . '</name><sym>Flag, Green</sym></wpt>' . "\n";
        }
        foreach ($r['steps'] ?? [] as $s) {
            $xml .= '  <wpt lat="' . $s['lat'] . '" lon="' . $s['lng'] . '"><name>' . $esc(($s['order'] ?? '') . '. ' . $s['title']) . '</name><desc>' . $esc(($s['arrive_at'] ?? '') . ' → ' . ($s['leave_at'] ?? '') . ($s['address'] ? ' · ' . $s['address'] : '')) . '</desc></wpt>' . "\n";
        }
        if (! empty($r['end'])) {
            $xml .= '  <wpt lat="' . $r['end']['lat'] . '" lon="' . $r['end']['lng'] . '"><name>' . $esc('Arrivée · ' . ($r['end']['label'] ?? '')) . '</name><sym>Flag, Red</sym></wpt>' . "\n";
        }
        $xml .= '  <trk><name>' . $esc($this->name) . '</name><trkseg>' . "\n";
        foreach ($r['geometry'] ?? [] as $pt) {
            $xml .= '    <trkpt lat="' . $pt[0] . '" lon="' . $pt[1] . '"></trkpt>' . "\n";
        }
        $xml .= "  </trkseg></trk>\n</gpx>\n";

        return $xml;
    }
}
