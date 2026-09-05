<x-app-layout title="Mon espace">
    <section class="max-w-7xl mx-auto px-4 sm:px-6 pt-8 sm:pt-12">
        <div class="grid lg:grid-cols-[1fr_360px] gap-6">
            <div class="space-y-8 min-w-0">
                {{-- Bienvenue --}}
                <div class="rounded-4xl bg-ink text-white p-6 sm:p-8 relative overflow-hidden">
                    <div class="absolute -right-16 -top-16 h-56 w-56 rounded-full bg-teal/40 blur-3xl"></div>
                    <div class="relative flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p class="eyebrow">Bonjour {{ auth()->user()->name }}</p>
                            <h1 class="display text-3xl sm:text-4xl mt-1">Où va-t-on aujourd'hui ?</h1>
                            @if(!empty($profile['top']))
                                <p class="mt-3 text-white/75 text-sm">Ton profil culturel : <span class="text-white font-semibold">{{ collect($profile['top'])->pluck('name')->implode(', ') }}</span>. Basé sur {{ $profile['signals']['favorites'] }} favoris, {{ $profile['signals']['reviews'] }} avis et {{ $profile['signals']['itineraries'] }} parcours.</p>
                            @else
                                <p class="mt-3 text-white/75 text-sm">Ajoute des favoris et des avis : CAMINO apprend tes goûts et affine ses recommandations, comme Spotify pour la musique.</p>
                            @endif
                        </div>
                        <div class="flex gap-2">
                            <a href="{{ route('itineraries.create') }}" class="btn btn-md btn-primary"><span class="material-symbols-outlined" style="font-size:18px">auto_awesome</span>Générer un parcours</a>
                            <a href="{{ route('map.index') }}" class="btn btn-md bg-white/15 text-white hover:bg-white/25"><span class="material-symbols-outlined" style="font-size:18px">map</span>Carte</a>
                        </div>
                    </div>
                </div>

                {{-- Recommandations --}}
                <div>
                    <x-section-heading eyebrow="Pour toi" :title="!empty($profile['top']) ? 'Sélection selon tes goûts' : 'À découvrir'" :href="route('map.index')" link-label="Explorer" />
                    <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
                        @foreach($recommended as $place)
                            <x-place-card :place="$place" />
                        @endforeach
                    </div>
                </div>

                {{-- Parcours récents --}}
                <div>
                    <x-section-heading eyebrow="Historique" title="Tes derniers parcours" :href="route('itineraries.index')" />
                    @if($itineraries->isNotEmpty())
                        <div class="space-y-2">
                            @foreach($itineraries as $itinerary)
                                @php $r = $itinerary->result_json ?? []; @endphp
                                <a href="{{ route('itineraries.show', $itinerary) }}" class="card card-hover p-4 flex items-center gap-4">
                                    <span class="h-10 w-10 rounded-2xl bg-coral-soft text-coral flex items-center justify-center shrink-0"><span class="material-symbols-outlined">route</span></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-semibold truncate">{{ $itinerary->name }}</p>
                                        <p class="text-xs text-ink-muted">{{ $itinerary->created_at->translatedFormat('j F') }} · {{ count($r['steps'] ?? []) }} étapes · {{ number_format($r['total_distance_km'] ?? 0, 1, ',', ' ') }} km</p>
                                    </div>
                                    <span class="material-symbols-outlined text-ink-muted">arrow_forward</span>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="card p-6 text-sm text-ink-muted">Aucun parcours pour l'instant. <a href="{{ route('itineraries.create') }}" class="font-semibold text-ink underline">Génère le premier</a>.</div>
                    @endif
                </div>
            </div>

            <aside class="space-y-6">
                <div class="card p-5">
                    <p class="eyebrow mb-3">Météo à Paris</p>
                    @if(!empty($forecast['current']))
                        <div class="flex items-center gap-3">
                            <span class="h-14 w-14 rounded-3xl bg-sun-soft text-amber-600 flex items-center justify-center"><span class="material-symbols-outlined filled" style="font-size:30px">{{ $forecast['current']['icon'] }}</span></span>
                            <div><p class="text-3xl font-semibold leading-none">{{ round($forecast['current']['temp']) }}°</p><p class="text-sm text-ink-muted mt-1">{{ $forecast['current']['label'] }}</p></div>
                        </div>
                        <div class="mt-4 grid grid-cols-3 gap-2 text-center text-xs">
                            @foreach(array_slice($forecast['days'], 0, 3) as $i => $day)
                                <div class="rounded-2xl bg-paper p-2">
                                    <p class="text-ink-muted">{{ $i === 0 ? 'Auj.' : \Illuminate\Support\Carbon::parse($day['date'])->translatedFormat('D') }}</p>
                                    <span class="material-symbols-outlined text-amber-600 my-1">{{ $day['icon'] }}</span>
                                    <p class="font-semibold">{{ $day['tmax'] }}° <span class="text-ink-muted font-normal">{{ $day['tmin'] }}°</span></p>
                                    <p class="text-[10px] text-ink-muted">{{ $day['rain_probability'] }} % pluie</p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-ink-muted">Météo indisponible pour le moment.</p>
                    @endif
                </div>

                <div class="card p-5">
                    <div class="flex items-center justify-between mb-3"><p class="eyebrow">Favoris récents</p><a href="{{ route('places.favorites') }}" class="text-xs font-semibold hover:text-coral">Tout voir</a></div>
                    <div class="space-y-2">
                        @forelse($favorites as $place)
                            <x-place-card :place="$place" :compact="true" />
                        @empty
                            <p class="text-sm text-ink-muted">Aucun favori pour l'instant.</p>
                        @endforelse
                    </div>
                </div>

                @if($events->isNotEmpty())
                    <div class="card p-5">
                        <p class="eyebrow mb-3">Événements à venir</p>
                        <div class="space-y-3">
                            @foreach($events as $event)
                                <a href="{{ route('places.show', $event) }}" class="block text-sm hover:text-coral">
                                    <p class="text-[11px] text-amber-700 font-semibold">{{ ($event->event_start_at ?? $event->event_end_at)->translatedFormat('j M') }}</p>
                                    <p class="font-semibold leading-snug line-clamp-2">{{ $event->title }}</p>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="card p-5">
                    <p class="eyebrow mb-3">Contribuer</p>
                    <div class="space-y-2 text-sm">
                        <a href="{{ route('community.propose') }}" class="flex items-center gap-3 rounded-2xl p-2 hover:bg-paper"><span class="material-symbols-outlined text-teal">add_location_alt</span>Proposer un lieu</a>
                        <a href="{{ route('map.index') }}" class="flex items-center gap-3 rounded-2xl p-2 hover:bg-paper"><span class="material-symbols-outlined text-coral">campaign</span>Signaler un événement gratuit</a>
                        <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-2xl p-2 hover:bg-paper"><span class="material-symbols-outlined text-ink-muted">person</span>Mon profil</a>
                    </div>
                </div>
            </aside>
        </div>
    </section>
</x-app-layout>
