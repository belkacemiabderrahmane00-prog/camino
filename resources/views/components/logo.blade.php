@props(['size' => 32, 'animate' => 'once', 'text' => false, 'id' => null])
@php
    /**
     * Logo CAMINO : une goutte de position (coral) traversée par un chemin qui se dessine, et un point (le marcheur)
     * qui parcourt ce chemin avant de se poser au cœur de la goutte. La goutte tombe et rebondit à l'apparition.
     * animate : once (à l'apparition), hover (au survol), loop (en continu), none.
     */
    $uid = $id ?? 'logo' . substr(md5(uniqid('', true)), 0, 6);
    $path = 'M20 40 C20 30 26 30 30 34 S38 40 40 30 S36 20 44 20';
@endphp
<span {{ $attributes->merge(['class' => 'camino-logo inline-flex items-center gap-2 ' . ($animate === 'none' ? '' : 'camino-logo-' . $animate)]) }} style="--logo-size: {{ $size }}px">
    <svg class="camino-logo-mark" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 64 64" fill="none" aria-hidden="true">
        <defs>
            <clipPath id="{{ $uid }}-clip"><path d="M32 60C32 60 10 39 10 26a22 22 0 0 1 44 0c0 13-22 34-22 34z"/></clipPath>
            <linearGradient id="{{ $uid }}-g" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#FF7A5C"/><stop offset="1" stop-color="#FF5A3C"/></linearGradient>
        </defs>
        <g class="camino-logo-drop">
            <ellipse class="camino-logo-shadow" cx="32" cy="60" rx="9" ry="2.6" fill="#12161C" opacity=".18"/>
            <path class="camino-logo-pin" d="M32 60C32 60 10 39 10 26a22 22 0 0 1 44 0c0 13-22 34-22 34z" fill="url(#{{ $uid }}-g)"/>
            <g clip-path="url(#{{ $uid }}-clip)">
                <path class="camino-logo-path camino-logo-path-ghost" d="{{ $path }}" stroke="#12161C" stroke-opacity=".18" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"/>
                <path class="camino-logo-path" d="{{ $path }}" stroke="#FFF6EA" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round" pathLength="100"/>
            </g>
            <circle class="camino-logo-dot" r="4.2" fill="#0F8B8D" stroke="#FFF6EA" stroke-width="2" style="offset-path: path('{{ $path }}')"/>
        </g>
    </svg>
    @if($text)<span class="font-display font-semibold tracking-tight leading-none" style="font-size: calc(var(--logo-size) * 0.72)">CAMINO</span>@endif
</span>
