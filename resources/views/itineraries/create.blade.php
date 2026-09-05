@php
    $params = $result['params'] ?? [];
    $steps = $result['steps'] ?? [];
    $hasResult = !empty($steps);
@endphp
<x-app-layout title="Générer un parcours">
    <section class="max-w-7xl mx-auto px-4 sm:px-6 pt-8 sm:pt-12">
        <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
            <div>
                <p class="eyebrow mb-1.5">Générateur de parcours</p>
                <h1 class="display text-4xl sm:text-5xl">Ton parcours, calculé pour de vrai.</h1>
                <p class="mt-3 text-ink-muted max-w-2xl">Sélection selon tes envies et la météo, ordre optimisé, trajets réels à pied ou à vélo sur les rues d'OpenStreetMap, horaires à chaque étape.</p>
            </div>
            <div class="flex items-center gap-2">
                <x-weather-chip :forecast="$forecast" label="Paris" :detailed="true" />
                @auth<a href="{{ route('itineraries.index') }}" class="btn btn-sm btn-soft"><span class="material-symbols-outlined" style="font-size:16px">history</span>Mes parcours</a>@endauth
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[400px_1fr] gap-6 items-start">
            {{-- ============================================================ Formulaire --}}
            <form method="POST" action="{{ route('itineraries.store') }}" class="card p-5 sm:p-6 space-y-5 lg:sticky lg:top-24"
                  x-data="{
                      mode: '{{ old('mode', $params['mode'] ?? 'walk') }}',
                      duration: {{ (int) old('duration_minutes', $params['duration_minutes'] ?? 180) }},
                      startMode: '{{ (old('start_lat') || (($result['start']['label'] ?? '') === 'Ma position')) ? 'me' : 'paris' }}',
                      locating: false,
                      label(m) { return m < 60 ? m + ' min' : Math.floor(m / 60) + ' h' + (m % 60 ? ' ' + String(m % 60).padStart(2, '0') : ''); },
                      useMe() {
                          this.locating = true;
                          window.Camino.locate().then(p => { this.$refs.lat.value = p.lat.toFixed(6); this.$refs.lng.value = p.lng.toFixed(6); this.startMode = 'me'; this.locating = false; })
                              .catch(() => { this.locating = false; alert('Impossible de récupérer ta position.'); });
                      },
                      useParis() { this.$refs.lat.value = ''; this.$refs.lng.value = ''; this.startMode = 'paris'; }
                  }">
                @csrf
                <input type="hidden" name="start_lat" x-ref="lat" value="{{ old('start_lat', ($result['start']['label'] ?? '') === 'Ma position' ? ($result['start']['lat'] ?? '') : '') }}">
                <input type="hidden" name="start_lng" x-ref="lng" value="{{ old('start_lng', ($result['start']['label'] ?? '') === 'Ma position' ? ($result['start']['lng'] ?? '') : '') }}">
                <input type="hidden" name="start_label" :value="startMode === 'me' ? 'Ma position' : ''">

                @if($itineraryPlaces->isNotEmpty())
                    <div class="rounded-2xl bg-teal-soft p-4 text-sm">
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <p class="font-semibold text-teal-dark"><span class="material-symbols-outlined align-middle" style="font-size:18px">playlist_add_check</span> Ta sélection ({{ $itineraryPlaces->count() }})</p>
                            <button formaction="{{ route('itineraries.clear-places') }}" class="text-xs text-teal-dark underline">Vider</button>
                        </div>
                        <ul class="space-y-1 text-xs text-ink-soft max-h-28 overflow-y-auto">
                            @foreach($itineraryPlaces as $p)<li class="truncate">• {{ $p->title }}</li>@endforeach
                        </ul>
                        <p class="mt-2 text-[11px] text-teal-dark">Le parcours enchaînera ces lieux dans cet ordre, avec les trajets réels.</p>
                    </div>
                @endif

                <div>
                    <div class="flex items-center justify-between"><label class="label" for="duration">Temps disponible</label><span class="text-sm font-semibold" x-text="label(duration)"></span></div>
                    <input id="duration" type="range" name="duration_minutes" min="30" max="600" step="15" x-model="duration" class="w-full accent-coral">
                    <div class="flex justify-between text-[10px] text-ink-muted mt-1"><span>30 min</span><span>2 h</span><span>Demi-journée</span><span>Journée</span></div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="budget">Budget (€)</label>
                        <input id="budget" type="number" name="budget_eur" min="0" max="1000" step="1" value="{{ old('budget_eur', $params['budget_eur'] ?? 40) }}" class="field" placeholder="Sans limite">
                    </div>
                    <div>
                        <label class="label" for="starts_at">Heure de départ</label>
                        <input id="starts_at" type="time" name="starts_at" value="{{ old('starts_at') }}" class="field" placeholder="Maintenant">
                    </div>
                </div>

                <div>
                    <p class="label">Mobilité</p>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach(['walk' => ['directions_walk', 'À pied', '4 km/h'], 'bike' => ['directions_bike', 'À vélo', 'rayon élargi']] as $m => [$icon, $l, $hint])
                            <label class="cursor-pointer">
                                <input type="radio" name="mode" value="{{ $m }}" class="peer sr-only" x-model="mode">
                                <span class="flex items-center gap-2 rounded-2xl border border-ink/10 px-3 py-2.5 text-sm font-medium peer-checked:bg-ink peer-checked:text-white peer-checked:border-ink transition">
                                    <span class="material-symbols-outlined" style="font-size:20px">{{ $icon }}</span>
                                    <span class="leading-tight">{{ $l }}<br><span class="text-[10px] font-normal opacity-70">{{ $hint }}</span></span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <p class="label">Point de départ</p>
                    <div class="flex gap-2">
                        <button type="button" @click="useParis()" class="chip flex-1 justify-center" :data-active="startMode === 'paris'"><span class="material-symbols-outlined" style="font-size:16px">location_city</span>Centre de Paris</button>
                        <button type="button" @click="useMe()" class="chip flex-1 justify-center" :data-active="startMode === 'me'"><span class="material-symbols-outlined" :class="locating && 'animate-spin'" style="font-size:16px" x-text="locating ? 'progress_activity' : 'my_location'"></span>Ma position</button>
                    </div>
                    <label class="mt-2 flex items-center justify-between text-xs text-ink-muted">
                        <span>Rayon de recherche</span>
                        <select name="radius_km" class="field !w-auto !py-1.5 text-xs">
                            @foreach([2, 4, 6, 10, 15] as $r)<option value="{{ $r }}" @selected((int) old('radius_km', $params['radius_km'] ?? 4) === $r)>{{ $r }} km</option>@endforeach
                        </select>
                    </label>
                </div>

                <div>
                    <p class="label">Centres d'intérêt</p>
                    <div class="flex flex-wrap gap-1.5">
                        @php $selected = old('interests', $params['interests'] ?? ['musee', 'monument']); @endphp
                        @foreach($categories as $category)
                            <label class="cursor-pointer">
                                <input type="checkbox" name="interests[]" value="{{ $category->slug }}" class="peer sr-only" @checked(in_array($category->slug, $selected))>
                                <span class="chip peer-checked:bg-ink peer-checked:text-white peer-checked:border-ink">{{ $category->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    @if(!empty($profile['top']))
                        <p class="mt-2 text-[11px] text-ink-muted"><span class="material-symbols-outlined align-middle text-coral" style="font-size:14px">auto_awesome</span> Ton profil aime : {{ collect($profile['top'])->pluck('name')->implode(', ') }}. Le générateur en tient compte.</p>
                    @endif
                </div>

                <div class="flex flex-wrap gap-4 text-sm">
                    <label class="inline-flex items-center gap-2 cursor-pointer"><input type="checkbox" name="free_only" value="1" class="rounded border-ink/20 text-coral focus:ring-coral" @checked(old('free_only', $params['free_only'] ?? false))>Lieux gratuits uniquement</label>
                    <label class="inline-flex items-center gap-2 cursor-pointer"><input type="hidden" name="use_weather" value="0"><input type="checkbox" name="use_weather" value="1" class="rounded border-ink/20 text-coral focus:ring-coral" checked>Tenir compte de la météo</label>
                </div>

                <button type="submit" class="btn btn-lg btn-primary w-full"><span class="material-symbols-outlined">auto_awesome</span>{{ $hasResult ? 'Recalculer' : 'Générer mon parcours' }}</button>
            </form>

            {{-- ============================================================ Résultat --}}
            <div class="min-w-0">
                @if($result && $hasResult)
                    <div class="card overflow-hidden">
                        <div id="itinerary-map" class="h-[320px] sm:h-[420px]"></div>
                        <div class="p-5 sm:p-6">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="eyebrow">Parcours généré</p>
                                    <h2 class="display text-2xl sm:text-3xl mt-1">{{ $result['title'] }}</h2>
                                    <p class="text-sm text-ink-muted mt-1">
                                        Départ {{ \Illuminate\Support\Carbon::parse($result['starts_at'])->translatedFormat('H\hi') }} · {{ $result['start']['label'] }}
                                        · {{ $result['mode'] === 'bike' ? 'à vélo' : 'à pied' }}
                                        @if(($result['routing_source'] ?? '') === 'valhalla') · trajets réels @endif
                                    </p>
                                </div>
                                @if(!empty($result['weather']))
                                    <div class="inline-flex items-center gap-2 rounded-full bg-sun-soft px-3 py-1.5 text-xs text-amber-800">
                                        <span class="material-symbols-outlined filled" style="font-size:18px">{{ $result['weather']['icon'] }}</span>
                                        {{ $result['weather']['label'] }}{{ $result['weather']['temp'] !== null ? ' · ' . round($result['weather']['temp']) . '°' : '' }} · pluie {{ $result['weather']['rain_probability'] }} %
                                    </div>
                                @endif
                            </div>

                            <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                                @foreach([
                                    ['schedule', floor($result['total_minutes'] / 60) . ' h ' . str_pad($result['total_minutes'] % 60, 2, '0', STR_PAD_LEFT), 'au total'],
                                    [$result['mode'] === 'bike' ? 'directions_bike' : 'directions_walk', number_format($result['total_distance_km'], 1, ',', ' ') . ' km', 'de trajet'],
                                    ['payments', number_format($result['total_cost_eur'], 0, ',', ' ') . ' €', 'estimés'],
                                ] as [$icon, $value, $label])
                                    <div class="rounded-2xl bg-paper p-3">
                                        <span class="material-symbols-outlined text-ink-muted" style="font-size:18px">{{ $icon }}</span>
                                        <p class="font-semibold text-lg leading-tight">{{ $value }}</p>
                                        <p class="text-[11px] text-ink-muted">{{ $label }}</p>
                                    </div>
                                @endforeach
                            </div>

                            @if(!empty($result['warnings']))
                                <div class="mt-4 space-y-1">
                                    @foreach($result['warnings'] as $w)
                                        <p class="text-xs text-amber-800 bg-sun-soft rounded-xl px-3 py-2 flex items-start gap-2"><span class="material-symbols-outlined" style="font-size:16px">info</span>{{ $w }}</p>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Timeline --}}
                            <ol class="mt-6 relative">
                                <div class="absolute left-[15px] top-4 bottom-4 w-0.5 bg-ink/10"></div>
                                <li class="relative pl-11 pb-5">
                                    <span class="absolute left-0 top-0 h-8 w-8 rounded-full bg-coral text-white flex items-center justify-center"><span class="material-symbols-outlined" style="font-size:16px">flag</span></span>
                                    <p class="text-sm font-semibold">{{ $result['start']['label'] }}</p>
                                    <p class="text-xs text-ink-muted">Départ à {{ \Illuminate\Support\Carbon::parse($result['starts_at'])->format('H\hi') }}</p>
                                </li>
                                @foreach($steps as $step)
                                    <li class="relative pl-11 pb-5">
                                        <span class="absolute left-0 top-0 h-8 w-8 rounded-full bg-ink text-white flex items-center justify-center text-sm font-bold">{{ $step['order'] }}</span>
                                        <p class="text-[11px] text-ink-muted mb-1.5 flex items-center gap-1"><span class="material-symbols-outlined" style="font-size:14px">{{ $result['mode'] === 'bike' ? 'directions_bike' : 'directions_walk' }}</span>{{ $step['travel_minutes'] }} min · {{ number_format($step['travel_km'], 1, ',', ' ') }} km</p>
                                        <a href="{{ route('places.show', $step['place_id']) }}" class="card card-hover flex gap-3 p-2.5">
                                            <div class="w-24 h-20 rounded-xl overflow-hidden shrink-0 placeholder-cover flex items-center justify-center">
                                                @if($step['cover'])<img src="{{ $step['cover'] }}" alt="" loading="lazy" class="w-full h-full object-cover">@else<span class="material-symbols-outlined text-white/80">place</span>@endif
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">{{ $step['category'] }}</p>
                                                <p class="font-semibold leading-snug line-clamp-2">{{ $step['title'] }}</p>
                                                <p class="text-xs text-ink-muted mt-0.5">{{ $step['arrive_at'] }} → {{ $step['leave_at'] }} · {{ $step['visit_minutes'] }} min sur place{{ $step['is_free'] ? ' · gratuit' : ($step['cost_eur'] ? ' · ≈ ' . number_format($step['cost_eur'], 0) . ' €' : '') }}</p>
                                                <p class="text-[11px] text-teal mt-1 flex items-center gap-1"><span class="material-symbols-outlined" style="font-size:14px">auto_awesome</span>{{ $step['reason'] }}</p>
                                            </div>
                                        </a>
                                    </li>
                                @endforeach
                                <li class="relative pl-11">
                                    <span class="absolute left-0 top-0 h-8 w-8 rounded-full bg-paper-deep text-ink flex items-center justify-center"><span class="material-symbols-outlined" style="font-size:16px">sports_score</span></span>
                                    <p class="text-sm font-semibold">Fin du parcours</p>
                                    <p class="text-xs text-ink-muted">vers {{ \Illuminate\Support\Carbon::parse($result['ends_at'])->format('H\hi') }}</p>
                                </li>
                            </ol>

                            @php
                                $coords = collect($steps)->map(fn ($s) => $s['lat'] . ',' . $s['lng'])->all();
                                $origin = $result['start']['lat'] . ',' . $result['start']['lng'];
                                $gmUrl = 'https://www.google.com/maps/dir/?api=1&origin=' . $origin . '&destination=' . end($coords) . (count($coords) > 1 ? '&waypoints=' . implode('|', array_slice($coords, 0, -1)) : '') . '&travelmode=' . ($result['mode'] === 'bike' ? 'bicycling' : 'walking');
                            @endphp
                            <div class="mt-6 flex flex-wrap gap-2">
                                <a href="{{ $gmUrl }}" target="_blank" rel="noopener" class="btn btn-md btn-ink"><span class="material-symbols-outlined" style="font-size:18px">navigation</span>Lancer dans Google Maps</a>
                                @auth
                                    @if(!empty($result['itinerary_id']))
                                        <a href="{{ route('itineraries.show', $result['itinerary_id']) }}" class="btn btn-md btn-soft"><span class="material-symbols-outlined" style="font-size:18px">bookmark</span>Enregistré · voir la fiche</a>
                                    @endif
                                @else
                                    <a href="{{ route('register') }}" class="btn btn-md btn-soft"><span class="material-symbols-outlined" style="font-size:18px">bookmark</span>Créer un compte pour le garder</a>
                                @endauth
                                <button x-data @click="navigator.clipboard.writeText(@js($gmUrl)); alert('Lien Google Maps copié !')" class="btn btn-md btn-ghost"><span class="material-symbols-outlined" style="font-size:18px">share</span>Partager</button>
                            </div>
                        </div>
                    </div>
                @elseif($result)
                    <div class="card p-8 text-center">
                        <span class="material-symbols-outlined text-4xl text-ink-muted">explore_off</span>
                        <p class="mt-3 font-semibold">Aucun parcours possible avec ces paramètres.</p>
                        <ul class="mt-2 text-sm text-ink-muted space-y-1">@foreach($result['warnings'] ?? [] as $w)<li>{{ $w }}</li>@endforeach</ul>
                        <p class="mt-3 text-sm text-ink-muted">Élargis le rayon, augmente le temps ou le budget, ou change de point de départ.</p>
                    </div>
                @else
                    <div class="card p-8 sm:p-12 text-center">
                        <div class="mx-auto h-16 w-16 rounded-3xl bg-coral-soft text-coral flex items-center justify-center"><span class="material-symbols-outlined" style="font-size:32px">auto_awesome</span></div>
                        <h2 class="display text-2xl mt-4">Prêt quand tu l'es.</h2>
                        <p class="mt-2 text-sm text-ink-muted max-w-md mx-auto">Règle ton temps, ton budget et tes envies à gauche. CAMINO choisit les lieux, optimise l'ordre et affiche le tracé réel avec les horaires de chaque étape.</p>
                        <div class="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-3 text-left text-sm">
                            @foreach([['cloud', 'Météo intégrée', 'S\'il pleut, on privilégie les lieux couverts.'], ['route', 'Trajets réels', 'Rues et distances OpenStreetMap, à pied ou à vélo.'], ['auto_awesome', 'Personnalisé', 'Tes favoris et avis affinent les choix.']] as [$i, $t, $d])
                                <div class="rounded-2xl bg-paper p-4"><span class="material-symbols-outlined text-teal">{{ $i }}</span><p class="font-semibold mt-2">{{ $t }}</p><p class="text-xs text-ink-muted mt-1">{{ $d }}</p></div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>

    @if($result && $hasResult)
        @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const el = document.getElementById('itinerary-map');
                if (!el || !window.L) return;
                const C = window.Camino;
                const result = @js(['start' => $result['start'], 'steps' => collect($steps)->map(fn ($s) => ['lat' => $s['lat'], 'lng' => $s['lng'], 'title' => $s['title'], 'order' => $s['order'], 'arrive' => $s['arrive_at'], 'slug' => $s['category_slug']])->all(), 'geometry' => $result['geometry'], 'mode' => $result['mode']]);
                const map = L.map(el, { zoomControl: false, scrollWheelZoom: false });
                C.tileLayer().addTo(map);
                L.control.zoom({ position: 'bottomright' }).addTo(map);
                const pts = (result.geometry && result.geometry.length > 1) ? result.geometry : [[result.start.lat, result.start.lng], ...result.steps.map(s => [s.lat, s.lng])];
                L.polyline(pts, { color: '#12161C', weight: 7, opacity: 0.25 }).addTo(map);
                const line = L.polyline(pts, { color: result.mode === 'bike' ? '#0F8B8D' : '#FF5A3C', weight: 4, opacity: 0.95, lineJoin: 'round' }).addTo(map);
                L.marker([result.start.lat, result.start.lng], { icon: C.stepIcon(0, true) }).addTo(map).bindPopup('<div class="p-3 text-sm font-semibold">Départ</div>');
                result.steps.forEach(s => L.marker([s.lat, s.lng], { icon: C.stepIcon(s.order) }).addTo(map).bindPopup(`<div class="p-3"><p class="text-sm font-semibold">${s.order}. ${C.escapeHtml(s.title)}</p><p class="text-xs text-ink-muted">Arrivée ${s.arrive}</p></div>`));
                map.fitBounds(line.getBounds(), { padding: [30, 30] });
            });
        </script>
        @endpush
    @endif
</x-app-layout>
