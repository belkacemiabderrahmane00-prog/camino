@props(['code', 'size' => 18])
@php
    /** Drapeau SVG inline (pas d'emoji : rendu identique sur tous les systèmes). */
    $w = $size;
    $h = (int) round($size * 0.7);
@endphp
@if($code === 'fr')
    <svg {{ $attributes->merge(['class' => 'inline-block rounded-[3px] shadow-sm shrink-0']) }} width="{{ $w }}" height="{{ $h }}" viewBox="0 0 30 21" aria-hidden="true"><rect width="10" height="21" fill="#0055A4"/><rect x="10" width="10" height="21" fill="#FFFFFF"/><rect x="20" width="10" height="21" fill="#EF4135"/></svg>
@elseif($code === 'en')
    <svg {{ $attributes->merge(['class' => 'inline-block rounded-[3px] shadow-sm shrink-0']) }} width="{{ $w }}" height="{{ $h }}" viewBox="0 0 60 42" aria-hidden="true"><clipPath id="fl-uk"><rect width="60" height="42"/></clipPath><g clip-path="url(#fl-uk)"><rect width="60" height="42" fill="#012169"/><path d="M0,0 L60,42 M60,0 L0,42" stroke="#FFFFFF" stroke-width="8"/><path d="M0,0 L60,42 M60,0 L0,42" stroke="#C8102E" stroke-width="3"/><path d="M30,0 V42 M0,21 H60" stroke="#FFFFFF" stroke-width="12"/><path d="M30,0 V42 M0,21 H60" stroke="#C8102E" stroke-width="7"/></g></svg>
@elseif($code === 'zh')
    <svg {{ $attributes->merge(['class' => 'inline-block rounded-[3px] shadow-sm shrink-0']) }} width="{{ $w }}" height="{{ $h }}" viewBox="0 0 30 20" aria-hidden="true"><rect width="30" height="20" fill="#EE1C25"/><g fill="#FFFF00"><polygon points="5,2 6.18,5.63 10,5.63 6.91,7.87 8.09,11.5 5,9.26 1.91,11.5 3.09,7.87 0,5.63 3.82,5.63"/><polygon points="10,1 10.4,1.9 11.4,1.9 10.6,2.5 10.9,3.5 10,2.9 9.1,3.5 9.4,2.5 8.6,1.9 9.6,1.9"/><polygon points="12,3.5 12.4,4.4 13.4,4.4 12.6,5 12.9,6 12,5.4 11.1,6 11.4,5 10.6,4.4 11.6,4.4"/><polygon points="12,7 12.4,7.9 13.4,7.9 12.6,8.5 12.9,9.5 12,8.9 11.1,9.5 11.4,8.5 10.6,7.9 11.6,7.9"/><polygon points="10,9.5 10.4,10.4 11.4,10.4 10.6,11 10.9,12 10,11.4 9.1,12 9.4,11 8.6,10.4 9.6,10.4"/></g></svg>
@endif
