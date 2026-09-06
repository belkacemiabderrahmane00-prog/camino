@props(['transit', 'minutes' => null, 'open' => false, 'compact' => false])
@php
    /** Feuille de trajet en transports, section par section (marche, ligne, correspondance), façon application de mobilité. */
    $sections = $transit['sections'] ?? [];
    $lines = $transit['lines'] ?? [];
    $alerts = $transit['alerts'] ?? [];
    $alternatives = array_slice($transit['alternatives'] ?? [], 0, 3);
    $blocking = collect($alerts)->contains(fn ($a) => ($a['severity'] ?? '') === 'blocking');
    $serious = count(array_filter($alerts, fn ($a) => ($a['severity'] ?? '') !== 'info'));
    $lineBadge = fn ($l, $size = 'text-[10px]') => '<span class="inline-flex items-center rounded-md px-1.5 py-0.5 ' . $size . ' font-bold leading-none" style="background:' . e($l['color']) . ';color:' . e($l['text_color']) . '">' . e(trim(($l['mode'] === 'Métro' ? 'M' : ($l['mode'] === 'Bus' ? 'Bus' : $l['mode'])) . ' ' . $l['code'])) . '</span>';
@endphp
<div x-data="{ open: {{ $open ? 'true' : 'false' }} }" {{ $attributes->merge(['class' => 'rounded-2xl bg-white border border-ink/5 overflow-hidden transit-sheet']) }}>
    {{-- En-tête : résumé du trajet, cliquable --}}
    <button type="button" @click="open = !open" class="w-full text-left px-3 py-2 flex items-center gap-2 hover:bg-paper/60 transition" :aria-expanded="open">
        <span class="material-symbols-outlined text-ink-muted shrink-0" style="font-size:16px">directions_subway</span>
        <span class="flex flex-wrap items-center gap-1.5 min-w-0 flex-1 text-[11px] text-ink-muted">
            @if($minutes !== null)<span class="font-semibold text-ink text-xs">{{ $minutes }} min</span>@endif
            @if(!empty($transit['depart_at']) && !empty($transit['arrive_at']))<span class="tabular-nums">{{ $transit['depart_at'] }} → {{ $transit['arrive_at'] }}</span>@endif
            <span class="inline-flex items-center gap-1">
                @foreach($sections as $sec)
                    @if($sec['type'] === 'walk')
                        @if($sec['minutes'] > 0)<span class="inline-flex items-center text-ink-muted"><span class="material-symbols-outlined" style="font-size:14px">directions_walk</span><span class="text-[10px]">{{ $sec['minutes'] }}</span></span>@endif
                    @elseif($sec['type'] === 'pt')
                        <span class="material-symbols-outlined text-ink/30" style="font-size:12px">chevron_right</span>{!! $lineBadge($sec) !!}
                    @endif
                @endforeach
            </span>
            @if($serious > 0)<span class="inline-flex items-center gap-0.5 {{ $blocking ? 'text-coral-dark' : 'text-amber-700' }}"><span class="material-symbols-outlined" style="font-size:14px">warning</span>{{ $serious }}</span>@endif
        </span>
        <span class="material-symbols-outlined text-ink-muted shrink-0 transition-transform" :class="open && 'rotate-180'" style="font-size:18px">expand_more</span>
    </button>

    {{-- Détail section par section --}}
    <div x-show="open" x-transition.opacity.duration.150ms x-cloak class="border-t border-ink/5">
        <ol class="relative px-3 py-3 space-y-0">
            @foreach($sections as $k => $sec)
                @if($sec['type'] === 'walk')
                    <li class="relative pl-9 pb-3">
                        <span class="absolute left-1 top-0 bottom-0 w-4 flex flex-col items-center"><span class="material-symbols-outlined text-ink-muted bg-white" style="font-size:16px">directions_walk</span><span class="flex-1 border-l-2 border-dotted border-ink/25 my-0.5"></span></span>
                        <p class="text-xs text-ink-soft">
                            {{ __('Marche :n min', ['n' => $sec['minutes']]) }}@if(!empty($sec['distance_m'])) <span class="text-ink-muted">({{ $sec['distance_m'] >= 1000 ? number_format($sec['distance_m'] / 1000, 1, ',', ' ') . ' km' : $sec['distance_m'] . ' m' }})</span>@endif
                            @if(!empty($sec['transfer']))<span class="text-ink-muted"> · {{ __('correspondance') }}</span>@endif
                        </p>
                        @if(!empty($sec['to']) && ($k === count($sections) - 1 || $loop->first))<p class="text-[11px] text-ink-muted">{{ $loop->first ? __('jusqu\'à :stop', ['stop' => $sec['to']]) : __('jusqu\'à l\'arrivée') }}</p>@endif
                        @if(!empty($sec['access']))<p class="mt-1 inline-flex items-center gap-1 rounded-lg bg-paper px-2 py-0.5 text-[11px] font-semibold text-ink"><span class="material-symbols-outlined" style="font-size:14px">{{ $sec['access']['kind'] === 'entrance' ? 'login' : 'logout' }}</span>{{ $sec['access']['kind'] === 'entrance' ? __('Entrée') : __('Sortie') }}{{ $sec['access']['code'] !== '' ? ' ' . $sec['access']['code'] : '' }}{{ $sec['access']['name'] !== '' ? ' · ' . $sec['access']['name'] : '' }}</p>@endif
                    </li>
                @elseif($sec['type'] === 'wait')
                    <li class="relative pl-9 pb-3">
                        <span class="absolute left-1 top-0 bottom-0 w-4 flex flex-col items-center"><span class="material-symbols-outlined text-ink-muted bg-white" style="font-size:16px">hourglass_top</span><span class="flex-1 border-l-2 border-dotted border-ink/25 my-0.5"></span></span>
                        <p class="text-xs text-ink-muted">{{ __('Attente :n min', ['n' => $sec['minutes']]) }}</p>
                    </li>
                @elseif($sec['type'] === 'pt')
                    <li class="relative pl-9 pb-3" x-data="{ stops: false }">
                        <span class="absolute left-[9px] top-2 bottom-1 w-1.5 rounded-full" style="background: {{ $sec['color'] }}"></span>
                        <span class="absolute left-[5px] top-0 h-3.5 w-3.5 rounded-full border-[3px] bg-white" style="border-color: {{ $sec['color'] }}"></span>
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-semibold text-sm leading-tight">{{ $sec['from'] }}</p>
                                <p class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-ink-soft">{!! $lineBadge($sec, 'text-[11px]') !!}<span class="truncate">{{ __('direction') }} {{ $sec['direction'] }}</span></p>
                            </div>
                            @if(!empty($sec['depart_at']))<p class="font-display text-lg tabular-nums shrink-0 leading-none pt-0.5">{{ $sec['depart_at'] }}</p>@endif
                        </div>
                        @foreach($sec['alerts'] ?? [] as $a)
                            <p class="mt-2 rounded-xl px-2.5 py-1.5 text-[11px] flex items-start gap-1.5 {{ match($a['severity'] ?? '') { 'blocking' => 'bg-coral-soft text-coral-dark', 'info' => 'bg-paper text-ink-muted', default => 'bg-sun-soft text-amber-800' } }}"><span class="material-symbols-outlined shrink-0" style="font-size:14px">{{ ($a['severity'] ?? '') === 'info' ? 'accessible' : 'warning' }}</span><span><span class="font-semibold">{{ $a['title'] }}</span>@if(!empty($a['text'])) · {{ $a['text'] }}@endif</span></p>
                        @endforeach
                        @if(($sec['stops'] ?? 0) > 0)
                            <button type="button" @click="stops = !stops" class="mt-2 inline-flex items-center gap-1 text-xs text-ink-muted hover:text-ink"><span class="material-symbols-outlined transition-transform" :class="stops && 'rotate-180'" style="font-size:16px">expand_more</span>{{ trans_choice(':n arrêt|:n arrêts', $sec['stops'], ['n' => $sec['stops']]) }} ({{ $sec['minutes'] }} min)</button>
                            <ul x-show="stops" x-transition.opacity.duration.150ms x-cloak class="mt-1 space-y-1 text-[11px] text-ink-muted">
                                @foreach(array_slice($sec['stop_names'] ?? [], 0, -1) as $stop)<li class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-ink/25 shrink-0"></span>{{ $stop }}</li>@endforeach
                            </ul>
                        @endif
                        <div class="mt-2 flex items-start justify-between gap-2">
                            <div class="min-w-0"><p class="font-semibold text-sm leading-tight">{{ $sec['to'] }}</p><p class="text-[11px] text-ink-muted">{{ __('Descends ici') }}</p></div>
                            @if(!empty($sec['arrive_at']))<p class="text-sm tabular-nums text-ink-soft shrink-0">{{ $sec['arrive_at'] }}</p>@endif
                        </div>
                    </li>
                @endif
            @endforeach
        </ol>
        @if($alternatives !== [])
            <div class="border-t border-ink/5 px-3 py-2 text-[11px] text-ink-muted flex flex-wrap items-center gap-x-2 gap-y-1">
                <span class="material-symbols-outlined" style="font-size:14px">schedule</span><span>{{ __('Aussi') }}</span>
                @foreach($alternatives as $alt)
                    <span class="inline-flex items-center gap-1 rounded-full bg-paper px-2 py-0.5"><span class="font-semibold text-ink tabular-nums">{{ $alt['depart_at'] }}</span>@foreach($alt['lines'] as $l){!! $lineBadge($l, 'text-[9px]') !!}@endforeach<span>{{ $alt['duration_min'] }} min</span></span>
                @endforeach
            </div>
        @endif
        <p class="border-t border-ink/5 px-3 py-1.5 text-[10px] text-ink-muted">{{ __('Horaires Île-de-France Mobilités · recalculés en temps réel pendant le guidage.') }}</p>
    </div>
</div>
