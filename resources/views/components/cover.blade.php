@props(['place', 'class' => 'h-40', 'sizes' => null])
@php
    $slug = $place->category->slug ?? 'default';
    $palette = [
        'musee' => ['#7C3AED', '#2E1065'], 'monument' => ['#B45309', '#451A03'], 'parc-jardin' => ['#15803D', '#052E16'],
        'lieu-culturel' => ['#0369A1', '#082F49'], 'restauration' => ['#DB2777', '#500724'], 'evenement-culturel' => ['#F59E0B', '#451A03'],
        'street-art' => ['#E11D48', '#4C0519'], 'itineraire' => ['#0F766E', '#042F2E'],
    ];
    [$c1, $c2] = $palette[$slug] ?? ['#0F8B8D', '#12161C'];
    $icons = ['musee' => 'palette', 'monument' => 'account_balance', 'parc-jardin' => 'park', 'lieu-culturel' => 'theater_comedy', 'restauration' => 'restaurant', 'evenement-culturel' => 'celebration', 'street-art' => 'brush', 'itineraire' => 'route'];
@endphp
@if($place->cover_image_url)
    <img src="{{ $place->coverThumb($class === 'h-full' ? 1200 : 800) }}" alt="{{ $place->title }}" loading="lazy" {{ $attributes->merge(['class' => 'w-full object-cover ' . $class]) }}>
@else
    <div {{ $attributes->merge(['class' => 'w-full placeholder-cover flex items-center justify-center ' . $class]) }} style="--c1: {{ $c1 }}; --c2: {{ $c2 }};">
        <span class="material-symbols-outlined text-white/70" style="font-size:40px;font-variation-settings:'wght' 300">{{ $icons[$slug] ?? 'place' }}</span>
    </div>
@endif
