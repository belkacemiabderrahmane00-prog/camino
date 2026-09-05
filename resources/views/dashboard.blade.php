@php
    $tones = ['sun' => 'from-amber-300 to-orange-400', 'rain' => 'from-sky-400 to-indigo-500', 'hot' => 'from-orange-400 to-rose-500', 'cold' => 'from-sky-200 to-blue-400', 'mild' => 'from-teal to-teal-dark', 'neutral' => 'from-slate-400 to-slate-600'];
    $earned = collect($badges)->where('earned', true)->count();
@endphp
<x-app-layout title="Mon espace">
    <section class="max-w-7xl mx-auto px-4 sm:px-6 pt-6 sm:pt-10 space-y-6 sm:space-y-8">

        {{-- ============================================================ Bonjour + météo --}}
        <div class="grid lg:grid-cols-[1.4fr_1fr] gap-4">
            <div class="rounded-4xl bg-ink text-white p-5 sm:p-8 relative overflow-hidden">
                <div class="absolute -right-16 -top-16 h-64 w-64 rounded-full bg-coral/40 blur-3xl"></div>
                <div class="absolute -left-10 -bottom-20 h-56 w-56 rounded-full bg-teal/40 blur-3xl"></div>
                <div class="relative flex items-center gap-4">
                    <a href="{{ route('profile.edit') }}" class="relative shrink-0">
                        @if($user->avatar_url)
                            <img src="{{ $user->avatar_url }}" alt="" class="h-16 w-16 sm:h-20 sm:w-20 rounded-3xl object-cover border-2 border-white/20">
                        @else
                            <span class="h-16 w-16 sm:h-20 sm:w-20 rounded-3xl bg-teal flex items-center justify-center font-display text-3xl border-2 border-white/20">{{ $user->initial }}</span>
                        @endif
                        <span class="absolute -bottom-1.5 -right-1.5 h-8 w-8 rounded-xl bg-sun text-ink flex items-center justify-center shadow-card"><span class="material-symbols-outlined filled" style="font-size:16px">{{ $level['icon'] }}</span></span>
                    </a>
                    <div class="min-w-0 flex-1">
                        <p class="eyebrow">Niveau {{ $level['index'] }} · {{ $level['name'] }}</p>
                        <h1 class="display text-2xl sm:text-4xl mt-0.5 truncate">{{ $greeting }}, {{ $user->name }}</h1>
                        <div class="mt-2 flex items-center gap-2">
                            <div class="h-1.5 flex-1 max-w-[220px] rounded-full bg-white/15 overflow-hidden"><div class="h-full rounded-full bg-gradient-to-r from-coral to-sun" style="width: {{ $level['progress'] }}%"></div></div>
                            <span class="text-[11px] text-white/70">{{ $level['points'] }} pts{{ $level['next'] ? ' · ' . ($level['next'] - $level['points']) . ' avant le niveau ' . ($level['index'] + 1) : '' }}</span>
                        </div>
                    </div>
                </div>
                @if($nextBadge)
                    <a href="{{ ['first_route' => route('itineraries.create'), 'walker' => route('itineraries.create'), 'collector' => route('map.index'), 'critic' => route('map.index'), 'reporter' => route('map.index'), 'lookout' => route('map.index')][$nextBadge['key']] ?? route('map.index') }}" class="relative mt-4 flex items-center gap-3 rounded-2xl bg-white/10 hover:bg-white/15 p-3 transition">
                        <span class="h-10 w-10 rounded-2xl bg-sun-soft text-amber-700 flex items-center justify-center shrink-0"><span class="material-symbols-outlined">{{ $nextBadge['icon'] }}</span></span>
                        <div class="min-w-0 text-sm"><p class="font-semibold">Prochain badge : {{ $nextBadge['name'] }}</p><p class="text-white/70 text-xs">Encore {{ $nextBadge['missing'] }} {{ $nextBadge['label'] }} · {{ $earned }}/{{ count($badges) }} badges obtenus</p></div>
                        <span class="material-symbols-outlined text-white/60 ml-auto">arrow_forward</span>
                    </a>
                @endif
            </div>

            <button type="button" @click="$dispatch('open-weather')" class="text-left rounded-4xl bg-gradient-to-br {{ $tones[$advice['tone']] ?? $tones['neutral'] }} text-white p-5 sm:p-6 relative overflow-hidden hover:-translate-y-0.5 transition">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.16em] opacity-90">Paris · maintenant</p>
                        <p class="font-display text-5xl leading-none mt-1">{{ $advice['temp'] !== null ? round($advice['temp']) . '°' : '—' }}</p>
                        <p class="text-sm opacity-90 mt-1">{{ $advice['label'] ?? 'Météo indisponible' }}</p>
                    </div>
                    <span class="material-symbols-outlined filled" style="font-size:48px">{{ $advice['icon'] }}</span>
                </div>
                <div class="mt-4 rounded-2xl bg-white/15 backdrop-blur p-3">
                    <p class="font-semibold text-sm flex items-center gap-1.5"><span class="material-symbols-outlined" style="font-size:16px">auto_awesome</span>{{ $advice['title'] }}</p>
                    <p class="text-xs opacity-90 mt-0.5 line-clamp-2">{{ $advice['text'] }}</p>
                </div>
                <p class="mt-3 text-[11px] opacity-80 flex items-center gap-1">Prévisions sur 3 jours <span class="material-symbols-outlined" style="font-size:14px">chevron_right</span></p>
            </button>
        </div>

        {{-- ============================================================ Raccourcis --}}
        <div>
            <p class="eyebrow mb-3">Raccourcis</p>
            <div class="grid grid-cols-3 sm:grid-cols-6 gap-2 sm:gap-3">
                @foreach([
                    [route('map.index', ['locate' => 1]), 'my_location', 'Près de moi', 'bg-teal-soft text-teal'],
                    [route('itineraries.create'), 'auto_awesome', 'Parcours', 'bg-coral-soft text-coral'],
                    [route('map.index'), 'campaign', 'Signaler', 'bg-sun-soft text-amber-700'],
                    [route('places.favorites'), 'favorite', 'Favoris', 'bg-rose-50 text-rose-600'],
                    [route('itineraries.index'), 'history', 'Mes parcours', 'bg-violet-50 text-violet-700'],
                    [route('community.propose'), 'add_location_alt', 'Proposer', 'bg-emerald-50 text-emerald-700'],
                ] as [$href, $icon, $label, $cls])
                    <a href="{{ $href }}" class="card card-hover p-3 sm:p-4 flex flex-col items-center gap-2 text-center">
                        <span class="h-11 w-11 rounded-2xl flex items-center justify-center {{ $cls }}"><span class="material-symbols-outlined">{{ $icon }}</span></span>
                        <span class="text-xs font-semibold">{{ $label }}</span>
                    </a>
                @endforeach
            </div>
        </div>

        {{-- ============================================================ Reprendre --}}
        @if($lastItinerary || $selection->isNotEmpty())
            <div class="grid md:grid-cols-2 gap-4">
                @if($lastItinerary)
                    @php $r = $lastItinerary->result_json ?? []; $steps = $r['steps'] ?? []; @endphp
                    <a href="{{ route('itineraries.show', $lastItinerary) }}" class="card card-hover p-4 sm:p-5 flex gap-4">
                        <div class="flex -space-x-3 shrink-0">
                            @foreach(array_slice($steps, 0, 3) as $s)
                                <div class="h-14 w-14 rounded-2xl overflow-hidden border-2 border-white placeholder-cover flex items-center justify-center shadow-card">
                                    @if(!empty($s['cover']))<img src="{{ $s['cover'] }}" alt="" class="w-full h-full object-cover">@else<span class="material-symbols-outlined text-white/80" style="font-size:18px">place</span>@endif
                                </div>
                            @endforeach
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="eyebrow">Reprendre</p>
                            <p class="font-semibold leading-snug line-clamp-1 mt-0.5">{{ $lastItinerary->name }}</p>
                            <p class="text-xs text-ink-muted">{{ $lastItinerary->created_at->diffForHumans() }} · {{ count($steps) }} étapes · {{ number_format($r['total_distance_km'] ?? 0, 1, ',', ' ') }} km</p>
                        </div>
                        <span class="material-symbols-outlined text-ink-muted self-center">play_circle</span>
                    </a>
                @endif
                @if($selection->isNotEmpty())
                    <a href="{{ route('itineraries.create') }}" class="card card-hover p-4 sm:p-5 flex gap-4 items-center border-teal/30">
                        <span class="h-14 w-14 rounded-2xl bg-teal-soft text-teal flex items-center justify-center shrink-0"><span class="material-symbols-outlined">playlist_add_check</span></span>
                        <div class="min-w-0 flex-1">
                            <p class="eyebrow">Ta sélection</p>
                            <p class="font-semibold mt-0.5">{{ $selection->count() }} lieu{{ $selection->count() > 1 ? 'x' : '' }} en attente de parcours</p>
                            <p class="text-xs text-ink-muted line-clamp-1">{{ $selection->pluck('title')->implode(' · ') }}</p>
                        </div>
                        <span class="material-symbols-outlined text-ink-muted">arrow_forward</span>
                    </a>
                @endif
            </div>
        @endif

        {{-- ============================================================ Pour toi --}}
        <div>
            <x-section-heading eyebrow="Pour toi" title="Sélection du moment" :subtitle="$reason" :href="route('map.index')" link-label="Explorer" />
            <div class="flex gap-3 overflow-x-auto snap-x hide-scrollbar -mx-4 px-4 pb-2 sm:mx-0 sm:px-0 sm:grid sm:grid-cols-2 xl:grid-cols-3 sm:gap-4 sm:overflow-visible">
                @foreach($recommended as $place)
                    <div class="snap-start shrink-0 w-64 sm:w-auto"><x-place-card :place="$place" /></div>
                @endforeach
            </div>
        </div>

        {{-- ============================================================ En direct + événements --}}
        <div class="grid lg:grid-cols-2 gap-4">
            <div class="card p-5">
                <div class="flex items-center justify-between mb-3"><p class="eyebrow">En direct autour de toi</p><a href="{{ route('map.index') }}" class="text-xs font-semibold hover:text-coral">Carte</a></div>
                <div class="space-y-1">
                    @forelse($alerts as $alert)
                        <a href="{{ $alert->place ? route('places.show', $alert->place) : route('map.index', ['lat' => $alert->lat, 'lng' => $alert->lng, 'z' => 16]) }}" class="flex gap-3 p-2.5 rounded-2xl hover:bg-paper">
                            <span class="h-9 w-9 rounded-full flex items-center justify-center shrink-0 text-white" style="background: {{ $alert->type_color }}"><span class="material-symbols-outlined" style="font-size:18px">{{ $alert->type_icon }}</span></span>
                            <div class="min-w-0 text-sm"><p class="font-semibold leading-snug line-clamp-1">{{ $alert->title }}</p><p class="text-xs text-ink-muted">{{ $alert->type_label }}{{ $alert->place ? ' · ' . $alert->place->title : '' }} · {{ $alert->created_at->diffForHumans() }}</p></div>
                        </a>
                    @empty
                        <p class="text-sm text-ink-muted p-2">Rien à signaler pour l'instant. Tu vois un concert gratuit ou une file d'attente ? <a href="{{ route('map.index') }}" class="underline font-semibold text-ink">Dis-le sur la carte</a>.</p>
                    @endforelse
                </div>
            </div>
            <div class="card p-5">
                <div class="flex items-center justify-between mb-3"><p class="eyebrow">Ce week-end et après</p><a href="{{ route('map.index', ['filtre' => 'evenements']) }}" class="text-xs font-semibold hover:text-coral">Tous</a></div>
                <div class="space-y-1">
                    @forelse($events as $event)
                        <a href="{{ route('places.show', $event) }}" class="flex gap-3 p-2.5 rounded-2xl hover:bg-paper">
                            <div class="w-12 h-12 rounded-xl overflow-hidden shrink-0"><x-cover :place="$event" class="h-full" /></div>
                            <div class="min-w-0 text-sm"><p class="text-[11px] font-semibold text-amber-700">{{ ($event->event_start_at ?? $event->event_end_at)->translatedFormat('D j M') }}</p><p class="font-semibold leading-snug line-clamp-1">{{ $event->title }}</p></div>
                        </a>
                    @empty
                        <p class="text-sm text-ink-muted p-2">Aucun événement daté pour l'instant.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ============================================================ Favoris + parcours --}}
        <div class="grid lg:grid-cols-2 gap-4">
            <div>
                <x-section-heading eyebrow="Ma collection" title="Favoris récents" :href="route('places.favorites')" class="mb-3" />
                @if($favorites->isNotEmpty())
                    <div class="grid grid-cols-2 gap-3">
                        @foreach($favorites->take(4) as $place)<x-place-card :place="$place" />@endforeach
                    </div>
                @else
                    <div class="card p-6 text-sm text-ink-muted">Aucun favori. Le cœur sur une fiche, et il apparaît ici.</div>
                @endif
            </div>
            <div>
                <x-section-heading eyebrow="Historique" title="Mes parcours" :href="route('itineraries.index')" class="mb-3" />
                @if($itineraries->isNotEmpty())
                    <div class="space-y-2">
                        @foreach($itineraries as $itinerary)
                            @php $r = $itinerary->result_json ?? []; @endphp
                            <a href="{{ route('itineraries.show', $itinerary) }}" class="card card-hover p-3.5 flex items-center gap-3">
                                <span class="h-10 w-10 rounded-2xl bg-coral-soft text-coral flex items-center justify-center shrink-0"><span class="material-symbols-outlined">route</span></span>
                                <div class="min-w-0 flex-1"><p class="font-semibold truncate text-sm">{{ $itinerary->name }}</p><p class="text-xs text-ink-muted">{{ $itinerary->created_at->translatedFormat('j F') }} · {{ count($r['steps'] ?? []) }} étapes · {{ number_format($r['total_distance_km'] ?? 0, 1, ',', ' ') }} km</p></div>
                                <span class="material-symbols-outlined text-ink-muted">arrow_forward</span>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="card p-6 text-sm text-ink-muted">Aucun parcours pour l'instant. <a href="{{ route('itineraries.create') }}" class="font-semibold text-ink underline">Génère le premier</a>, il sera gardé ici.</div>
                @endif
                <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                    @foreach([['itineraries', 'parcours', 'route'], ['km', 'km', 'directions_walk'], ['reviews', 'avis', 'rate_review']] as [$k, $l, $i])
                        <div class="rounded-2xl bg-white border border-ink/5 p-3"><span class="material-symbols-outlined text-ink-muted" style="font-size:18px">{{ $i }}</span><p class="font-semibold">{{ is_float($stats[$k]) ? number_format($stats[$k], 1, ',', ' ') : $stats[$k] }}</p><p class="text-[10px] text-ink-muted uppercase tracking-wider">{{ $l }}</p></div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
</x-app-layout>
