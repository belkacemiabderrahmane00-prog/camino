@props(['category', 'size' => 'sm'])
@php
    $slug = $category->slug ?? 'default';
    $style = [
        'musee' => ['palette', '#7C3AED'], 'monument' => ['account_balance', '#B45309'], 'parc-jardin' => ['park', '#15803D'],
        'lieu-culturel' => ['theater_comedy', '#0369A1'], 'restauration' => ['restaurant', '#DB2777'], 'evenement-culturel' => ['celebration', '#F59E0B'],
        'street-art' => ['brush', '#E11D48'], 'itineraire' => ['route', '#0F766E'],
        'librairies-bibliotheques' => ['menu_book', '#1D4ED8'], 'ateliers-artisans' => ['handyman', '#9A3412'],
    ];
    [$icon, $color] = $style[$slug] ?? ['place', '#0F8B8D'];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full font-semibold ' . ($size === 'xs' ? 'px-2 py-0.5 text-[10px]' : 'px-2.5 py-1 text-[11px]')]) }} style="background: {{ $color }}1A; color: {{ $color }};">
    <span class="material-symbols-outlined" style="font-size: {{ $size === 'xs' ? 12 : 14 }}px">{{ $icon }}</span>
    {{ __($category->name ?? 'Lieu') }}
</span>
