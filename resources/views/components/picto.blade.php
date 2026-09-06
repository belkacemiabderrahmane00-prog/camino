@props(['name', 'size' => 40, 'accent' => '#FF5A3C'])
@php
    /**
     * Pictogrammes illustrés du générateur : trait épais arrondi (couleur courante) + une touche d'accent.
     * Dessinés dans une grille 48×48, cohérents entre eux (pas d'icône de police).
     */
    $a = $accent;
    $svg = match ($name) {
        'walk' => '<circle cx="27" cy="8" r="4" fill="' . $a . '"/><path d="M22 44l4-12-5-4-3 9M21 28l3-9 7 2 4 6 5 2M24 19l-6 3-3 6M28 32l6 5 2 7"/>',
        'bike' => '<circle cx="12" cy="33" r="8"/><circle cx="36" cy="33" r="8"/><path d="M12 33l8-14h9l7 14M20 19l-3-6h-5M29 19l-4 14"/><circle cx="31" cy="9" r="3.5" fill="' . $a . '"/>',
        'transit' => '<rect x="10" y="6" width="28" height="30" rx="6"/><path d="M10 22h28M17 44l-3 4M31 44l3 4M14 36v8h20v-8"/><circle cx="17" cy="30" r="2.2" fill="' . $a . '"/><circle cx="31" cy="30" r="2.2" fill="' . $a . '"/><path d="M18 6v-2h12v2"/>',
        'me' => '<circle cx="24" cy="24" r="9"/><circle cx="24" cy="24" r="3" fill="' . $a . '"/><path d="M24 4v7M24 37v7M4 24h7M37 24h7"/>',
        'address' => '<circle cx="21" cy="21" r="12"/><path d="M30 30l12 12"/><path d="M15 21a6 6 0 0 1 6-6" stroke="' . $a . '"/>',
        'city' => '<path d="M6 42V16l10-6v32M16 42h16V8l10 6v28M6 42h36"/><path d="M22 16h4M22 23h4M22 30h4M11 22h1M11 29h1M35 22h1M35 29h1" stroke="' . $a . '"/>',
        'pin' => '<path d="M24 44s-14-12-14-24a14 14 0 0 1 28 0c0 12-14 24-14 24z"/><circle cx="24" cy="20" r="5" fill="' . $a . '"/>',
        'open' => '<path d="M8 40c6-2 8-10 14-14s12-4 18-12"/><path d="M34 10h6v6" stroke="' . $a . '"/><circle cx="8" cy="40" r="3" fill="' . $a . '"/>',
        'loop' => '<path d="M14 14h20a10 10 0 0 1 0 20H14"/><path d="M20 8l-6 6 6 6" stroke="' . $a . '"/><circle cx="14" cy="34" r="3" fill="' . $a . '"/>',
        'flag' => '<path d="M12 44V6"/><path d="M12 8h24l-6 8 6 8H12" fill="' . $a . '" stroke="' . $a . '"/>',
        'today' => '<rect x="6" y="10" width="36" height="32" rx="6"/><path d="M6 20h36M16 6v8M32 6v8"/><circle cx="24" cy="31" r="4" fill="' . $a . '"/>',
        'tomorrow' => '<rect x="6" y="10" width="36" height="32" rx="6"/><path d="M6 20h36M16 6v8M32 6v8"/><path d="M19 31h10M25 26l5 5-5 5" stroke="' . $a . '"/>',
        'calendar' => '<rect x="6" y="10" width="36" height="32" rx="6"/><path d="M6 20h36M16 6v8M32 6v8"/><path d="M14 28h4M22 28h4M30 28h4M14 35h4M22 35h4" stroke="' . $a . '"/>',
        'clock' => '<circle cx="24" cy="24" r="18"/><path d="M24 12v12l8 5" stroke="' . $a . '"/>',
        'budget' => '<rect x="4" y="12" width="40" height="26" rx="6"/><circle cx="24" cy="25" r="6"/><path d="M4 20h40M4 30h40" opacity=".35"/><circle cx="24" cy="25" r="2" fill="' . $a . '"/>',
        'duration' => '<circle cx="24" cy="26" r="16"/><path d="M24 26V16M20 4h8M24 4v6" /><path d="M24 26l7 7" stroke="' . $a . '"/>',
        'museum' => '<path d="M6 18L24 8l18 10H6zM8 42h32M10 18v24M20 18v24M28 18v24M38 18v24"/><path d="M6 42h36" stroke="' . $a . '"/>',
        'monument' => '<path d="M24 4l6 12v20H18V16z"/><path d="M12 36h24l4 8H8z"/><path d="M24 12v8" stroke="' . $a . '"/>',
        'park' => '<path d="M24 4l-10 14h5l-7 10h8l-5 8h18l-5-8h8l-7-10h5z"/><path d="M24 36v8" stroke="' . $a . '"/><circle cx="38" cy="10" r="4" fill="' . $a . '" stroke="none"/>',
        'culture' => '<path d="M6 12h36v6a6 6 0 0 1-6 6H12a6 6 0 0 1-6-6zM10 24v18h28V24"/><path d="M18 24v8M30 24v8" stroke="' . $a . '"/><path d="M6 12l6-6h24l6 6"/>',
        'event' => '<path d="M8 42l10-30 20 20z"/><path d="M28 6l2 6M36 12l6-2M34 22l6 2" stroke="' . $a . '"/><circle cx="41" cy="6" r="2" fill="' . $a . '" stroke="none"/>',
        'street-art' => '<path d="M10 40l4-14 16-16 10 10-16 16z"/><path d="M30 10l8 8" stroke="' . $a . '"/><path d="M10 40l-4 4"/><circle cx="40" cy="8" r="3" fill="' . $a . '" stroke="none"/>',
        'food' => '<path d="M12 6v14a4 4 0 0 0 4 4v18M16 6v10M20 6v10M32 6c-4 2-6 8-6 14h6v22"/><path d="M12 12h8" stroke="' . $a . '"/>',
        'trail' => '<path d="M8 40c10 0 8-16 18-16s8 16 18 16"/><path d="M8 40h4M40 40h4"/><circle cx="8" cy="40" r="3" fill="' . $a . '"/><path d="M40 12l-4 8h8z" fill="' . $a . '" stroke="none"/>',
        'book' => '<path d="M8 8h12a4 4 0 0 1 4 4v28a4 4 0 0 0-4-4H8zM40 8H28a4 4 0 0 0-4 4v28a4 4 0 0 1 4-4h12z"/><path d="M13 16h6M13 22h6" stroke="' . $a . '"/>',
        'craft' => '<path d="M10 38l14-14M24 24l6-6M6 42l4-4"/><path d="M30 18l6-6 4 4-6 6z" fill="' . $a . '" stroke="' . $a . '"/><path d="M26 10l6-4 8 8-4 6"/>',
        'lunch' => '<path d="M6 30h36a18 18 0 0 0-36 0z"/><path d="M4 36h40"/><circle cx="24" cy="12" r="2" fill="' . $a . '" stroke="none"/><path d="M14 24c2-4 6-6 10-6" stroke="' . $a . '"/>',
        'weather' => '<path d="M14 36a8 8 0 0 1 2-15.7A11 11 0 0 1 37 22a7 7 0 0 1 1 14z"/><path d="M12 12l2 2M30 6v3M42 12l-2 2" stroke="' . $a . '"/>',
        'accessible' => '<circle cx="26" cy="8" r="4" fill="' . $a . '" stroke="none"/><path d="M20 14l2 12h10l5 10M22 26h-2a10 10 0 1 0 12 12"/>',
        'free' => '<path d="M6 24l4-16h28l4 16-18 18z"/><path d="M6 24h36M18 8l6 16 6-16" stroke="' . $a . '"/>',
        'radius' => '<circle cx="24" cy="24" r="18" stroke-dasharray="4 5"/><circle cx="24" cy="24" r="3" fill="' . $a . '" stroke="none"/><path d="M24 24l12-8" stroke="' . $a . '"/>',
        'surprise' => '<rect x="8" y="8" width="32" height="32" rx="8"/><circle cx="17" cy="17" r="2.5" fill="' . $a . '" stroke="none"/><circle cx="31" cy="31" r="2.5" fill="' . $a . '" stroke="none"/><circle cx="24" cy="24" r="2.5" fill="' . $a . '" stroke="none"/><circle cx="31" cy="17" r="2.5" fill="' . $a . '" stroke="none"/><circle cx="17" cy="31" r="2.5" fill="' . $a . '" stroke="none"/>',
        'sparkle' => '<path d="M24 6l4 12 12 4-12 4-4 12-4-12-12-4 12-4z"/><path d="M38 34l2 4 4 2-4 2-2 4-2-4-4-2 4-2z" fill="' . $a . '" stroke="none"/>',
        'summary' => '<rect x="8" y="6" width="32" height="36" rx="6"/><path d="M16 18h16M16 26h16M16 34h8"/><circle cx="34" cy="34" r="3" fill="' . $a . '" stroke="none"/>',
        default => '<circle cx="24" cy="24" r="16"/><circle cx="24" cy="24" r="4" fill="' . $a . '"/>',
    };
@endphp
<svg {{ $attributes->merge(['class' => 'picto shrink-0']) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $svg !!}</svg>
