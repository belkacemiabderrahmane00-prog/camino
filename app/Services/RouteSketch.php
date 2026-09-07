<?php

namespace App\Services;

/**
 * Croquis SVG d'un tracé (géométrie lat/lng) et de ses étapes, sans fond de carte : pour la carte souvenir et le PDF.
 */
class RouteSketch
{
    /**
     * @param array<int,array{0:float,1:float}> $geometry
     * @param array<int,array{lat:float,lng:float,n?:int|string}> $stops
     */
    public static function svg(array $geometry, array $stops, int $width = 240, int $height = 150, string $stroke = '#FF5A3C', string $ink = '#12161C', bool $labels = true): string
    {
        $pts = array_values(array_filter($geometry, fn ($p) => isset($p[0], $p[1])));
        foreach ($stops as $s) {
            if (isset($s['lat'], $s['lng'])) {
                $pts[] = [(float) $s['lat'], (float) $s['lng']];
            }
        }
        if (count($pts) < 2) {
            return '';
        }
        $lats = array_column($pts, 0);
        $lngs = array_column($pts, 1);
        $minLat = min($lats); $maxLat = max($lats); $minLng = min($lngs); $maxLng = max($lngs);
        $k = cos(deg2rad(($minLat + $maxLat) / 2));
        $w = max(1e-6, ($maxLng - $minLng) * $k);
        $h = max(1e-6, $maxLat - $minLat);
        $pad = 14;
        $scale = min(($width - 2 * $pad) / $w, ($height - 2 * $pad) / $h);
        $ox = ($width - $w * $scale) / 2;
        $oy = ($height - $h * $scale) / 2;
        $map = fn (float $lat, float $lng) => [round($ox + ($lng - $minLng) * $k * $scale, 1), round($oy + ($maxLat - $lat) * $scale, 1)];
        $d = '';
        foreach ($geometry as $i => $p) {
            [$x, $y] = $map((float) $p[0], (float) $p[1]);
            $d .= ($i === 0 ? 'M' : 'L') . $x . ' ' . $y . ' ';
        }
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $width . ' ' . $height . '" width="' . $width . '" height="' . $height . '" fill="none">';
        if ($d !== '') {
            $svg .= '<path d="' . trim($d) . '" stroke="' . $stroke . '" stroke-opacity=".25" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>';
            $svg .= '<path d="' . trim($d) . '" stroke="' . $stroke . '" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>';
        }
        foreach ($stops as $s) {
            if (! isset($s['lat'], $s['lng'])) {
                continue;
            }
            [$x, $y] = $map((float) $s['lat'], (float) $s['lng']);
            $n = $s['n'] ?? '';
            if ($n === 'start') {
                $svg .= '<circle cx="' . $x . '" cy="' . $y . '" r="5" fill="' . $stroke . '" stroke="#fff" stroke-width="2"/>';
            } else {
                $svg .= '<circle cx="' . $x . '" cy="' . $y . '" r="7.5" fill="' . $ink . '" stroke="#fff" stroke-width="2"/>';
                if ($labels && $n !== '') {
                    $svg .= '<text x="' . $x . '" y="' . ($y + 3) . '" text-anchor="middle" font-size="8" font-weight="700" font-family="Sora, Arial, sans-serif" fill="#fff">' . htmlspecialchars((string) $n) . '</text>';
                }
            }
        }

        return $svg . '</svg>';
    }
}
