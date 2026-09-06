@php
    $params = $result['params'] ?? [];
    $steps = $result['steps'] ?? [];
    $hasResult = !empty($steps);
    $today = \Illuminate\Support\Carbon::now(config('app.timezone'));
    $initial = [
        'startMode' => old('start_label') === 'Ma position' || ($params['start_source'] ?? null) === 'Ma position' ? 'me' : ((old('start_lat') ?? ($params['start_source'] ?? null)) ? 'address' : 'paris'),
        'start' => ['lat' => old('start_lat', $hasResult && ($params['start_source'] ?? null) ? $result['start']['lat'] : null), 'lng' => old('start_lng', $hasResult && ($params['start_source'] ?? null) ? $result['start']['lng'] : null), 'label' => old('start_label', $hasResult && ($params['start_source'] ?? null) ? $result['start']['label'] : '')],
        'endMode' => old('end_mode', $params['end_mode'] ?? 'open'),
        'end' => ['lat' => old('end_lat', $params['end']['lat'] ?? null), 'lng' => old('end_lng', $params['end']['lng'] ?? null), 'label' => old('end_label', $params['end']['label'] ?? '')],
        'date' => old('date', $params['date'] ?? $today->format('Y-m-d')),
        'time' => old('starts_at', $params['starts_at'] ?? ''),
        'today' => $today->format('Y-m-d'),
        'tomorrow' => $today->copy()->addDay()->format('Y-m-d'),
        'mode' => old('mode', $params['mode'] ?? 'walk'),
        'duration' => (int) old('duration_minutes', $params['duration_minutes'] ?? 180),
        'paris' => ['lat' => $defaultStart['lat'], 'lng' => $defaultStart['lng']],
        'step' => 1,
        'showForm' => ! $hasResult,
        'hasResult' => $hasResult,
    ];
    $mobIcon = match ($result['mode'] ?? 'walk') { 'bike' => 'directions_bike', 'transit' => 'directions_subway', default => 'directions_walk' };
@endphp
<x-app-layout title="Générer un parcours">
    <section class="max-w-7xl mx-auto px-4 sm:px-6 pt-6 sm:pt-12">
        <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
            <div>
                <p class="eyebrow mb-1.5">Générateur de parcours</p>
                <h1 class="display text-3xl sm:text-5xl">Ton parcours, calculé pour de vrai.</h1>
                <p class="mt-3 text-ink-muted max-w-2xl">Lieux choisis selon tes envies, la météo et leurs horaires d'ouverture, ordre optimisé, trajets réels dans les rues, heure d'arrivée à chaque étape.</p>
            </div>
            @auth<a href="{{ route('itineraries.index') }}" class="btn btn-sm btn-soft"><span class="material-symbols-outlined" style="font-size:16px">history</span>Mes parcours</a>@endauth
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[420px_1fr] gap-6 items-start" x-data="itineraryForm(@js($initial))">
            {{-- ============================================================ Formulaire --}}
            <div x-show="hasResult && showForm" x-cloak @click="showForm = false" class="lg:hidden fixed inset-0 z-[1150] bg-ink/40 backdrop-blur-sm"></div>
            <form method="POST" action="{{ route('itineraries.store') }}" class="card p-5 sm:p-6 space-y-6 lg:sticky lg:top-24 min-w-0 {{ $hasResult ? 'max-lg:fixed max-lg:inset-x-0 max-lg:bottom-0 max-lg:z-[1200] max-lg:max-h-[90vh] max-lg:overflow-y-auto max-lg:rounded-b-none max-lg:shadow-float' : '' }}" :class="{ 'hidden lg:block': hasResult && !showForm }" @submit="beforeSubmit">
                @csrf
                @if($hasResult)
                    <div class="lg:hidden flex items-center justify-between -mt-1">
                        <p class="font-semibold">Modifier le parcours</p>
                        <button type="button" @click="showForm = false" class="btn btn-icon btn-ghost" aria-label="Fermer"><span class="material-symbols-outlined">close</span></button>
                    </div>
                @endif
                <input type="hidden" name="start_lat" :value="startMode === 'paris' ? '' : (start.lat ?? '')">
                <input type="hidden" name="start_lng" :value="startMode === 'paris' ? '' : (start.lng ?? '')">
                <input type="hidden" name="start_label" :value="startMode === 'paris' ? '' : (start.label || (startMode === 'me' ? 'Ma position' : 'Point sur la carte'))">
                <input type="hidden" name="end_mode" :value="endMode">
                <input type="hidden" name="end_lat" :value="endMode === 'point' ? (end.lat ?? '') : ''">
                <input type="hidden" name="end_lng" :value="endMode === 'point' ? (end.lng ?? '') : ''">
                <input type="hidden" name="end_label" :value="endMode === 'point' ? end.label : ''">
                <input type="hidden" name="date" :value="date">

                @if($itineraryPlaces->isNotEmpty())
                    <div class="rounded-2xl bg-teal-soft p-4 text-sm">
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <p class="font-semibold text-teal-dark"><span class="material-symbols-outlined align-middle" style="font-size:18px">playlist_add_check</span> Ta sélection ({{ $itineraryPlaces->count() }})</p>
                            <button formaction="{{ route('itineraries.clear-places') }}" class="text-xs text-teal-dark underline">Vider</button>
                        </div>
                        <ul class="space-y-1 text-xs text-ink-soft max-h-28 overflow-y-auto">
                            @foreach($itineraryPlaces as $p)<li class="truncate">• {{ $p->title }}</li>@endforeach
                        </ul>
                        <p class="mt-2 text-[11px] text-teal-dark">Le parcours enchaînera ces lieux dans cet ordre, avec les trajets réels et leurs horaires.</p>
                    </div>
                @endif

                @if($errors->any())
                    <div class="rounded-2xl bg-coral-soft text-coral-dark px-4 py-3 text-sm space-y-0.5">@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>
                @endif

                <div class="space-y-6" :class="{ 'hidden lg:block': step !== 1 }">
                <p class="lg:hidden eyebrow">Étape 1 sur 3 · D'où et jusqu'où</p>
                {{-- Départ --}}
                <div>
                    <p class="label flex items-center gap-1.5"><span class="material-symbols-outlined text-coral" style="font-size:16px">trip_origin</span>Départ</p>
                    <div class="grid grid-cols-3 gap-1.5">
                        <button type="button" @click="useMe()" class="chip justify-center" :data-active="startMode === 'me'"><span class="material-symbols-outlined" :class="locating && 'animate-spin'" style="font-size:16px" x-text="locating ? 'progress_activity' : 'my_location'"></span>Ma position</button>
                        <button type="button" @click="startMode = 'address'; $nextTick(() => $refs.startSearch?.focus())" class="chip justify-center" :data-active="startMode === 'address'"><span class="material-symbols-outlined" style="font-size:16px">search</span>Adresse</button>
                        <button type="button" @click="startMode = 'paris'; start = { lat: null, lng: null, label: '' }" class="chip justify-center" :data-active="startMode === 'paris'"><span class="material-symbols-outlined" style="font-size:16px">location_city</span>Paris</button>
                    </div>
                    <div x-show="startMode !== 'paris'" x-cloak class="mt-2">
                        <div x-show="startMode === 'address'" x-data="addressSearch('start')" class="relative">
                            <input x-ref="startSearch" type="search" x-model="q" @input.debounce.250ms="search()" @focus="open = results.length > 0" @keydown.escape="open = false" placeholder="Numéro, rue, ville…" class="field pr-10" autocomplete="off">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-ink-muted" style="font-size:18px" x-text="loading ? 'progress_activity' : 'search'"></span>
                            <div x-show="open" x-cloak @click.outside="open = false" class="absolute z-30 mt-1 w-full card p-1 max-h-60 overflow-y-auto">
                                <template x-for="r in results" :key="r.label">
                                    <button type="button" @click="pick(r)" class="w-full text-left px-3 py-2 rounded-xl hover:bg-paper text-sm flex items-start gap-2"><span class="material-symbols-outlined text-ink-muted mt-0.5" style="font-size:16px" x-text="r.type === 'municipality' ? 'location_city' : 'place'"></span><span x-text="r.label"></span></button>
                                </template>
                                <p x-show="!loading && results.length === 0 && q.length >= 3" class="px-3 py-2 text-xs text-ink-muted">Aucune adresse trouvée en Île-de-France.</p>
                            </div>
                        </div>
                        <div class="mt-1.5 flex items-center justify-between gap-2 text-xs">
                            <p class="text-ink-muted truncate min-w-0"><template x-if="start.lat"><span><span class="material-symbols-outlined align-middle text-teal" style="font-size:14px">check_circle</span> <span x-text="start.label || 'Point choisi'"></span></span></template><template x-if="!start.lat"><span x-text="startMode === 'me' ? 'En attente de ta position…' : 'Choisis une adresse ou un point sur la carte.'"></span></template></p>
                            <button type="button" @click="openMap('start')" class="shrink-0 font-semibold text-ink hover:text-coral inline-flex items-center gap-1"><span class="material-symbols-outlined" style="font-size:16px">pin_drop</span>Sur la carte</button>
                        </div>
                    </div>
                </div>

                {{-- Arrivée --}}
                <div>
                    <p class="label flex items-center gap-1.5"><span class="material-symbols-outlined text-ink" style="font-size:16px">sports_score</span>Arrivée</p>
                    <div class="grid grid-cols-3 gap-1.5">
                        <button type="button" @click="endMode = 'open'" class="chip justify-center" :data-active="endMode === 'open'"><span class="material-symbols-outlined" style="font-size:16px">explore</span>Libre</button>
                        <button type="button" @click="endMode = 'loop'" class="chip justify-center" :data-active="endMode === 'loop'"><span class="material-symbols-outlined" style="font-size:16px">replay</span>Boucle</button>
                        <button type="button" @click="endMode = 'point'; $nextTick(() => $refs.endSearch?.focus())" class="chip justify-center" :data-active="endMode === 'point'"><span class="material-symbols-outlined" style="font-size:16px">search</span>Adresse</button>
                    </div>
                    <div x-show="endMode === 'point'" x-cloak class="mt-2">
                        <div x-data="addressSearch('end')" class="relative">
                            <input x-ref="endSearch" type="search" x-model="q" @input.debounce.250ms="search()" @focus="open = results.length > 0" @keydown.escape="open = false" placeholder="Adresse d'arrivée (gare, hôtel…)" class="field pr-10" autocomplete="off">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-ink-muted" style="font-size:18px" x-text="loading ? 'progress_activity' : 'search'"></span>
                            <div x-show="open" x-cloak @click.outside="open = false" class="absolute z-30 mt-1 w-full card p-1 max-h-60 overflow-y-auto">
                                <template x-for="r in results" :key="r.label"><button type="button" @click="pick(r)" class="w-full text-left px-3 py-2 rounded-xl hover:bg-paper text-sm" x-text="r.label"></button></template>
                            </div>
                        </div>
                        <div class="mt-1.5 flex items-center justify-between gap-2 text-xs">
                            <p class="text-ink-muted truncate min-w-0"><template x-if="end.lat"><span><span class="material-symbols-outlined align-middle text-teal" style="font-size:14px">check_circle</span> <span x-text="end.label || 'Point choisi'"></span></span></template><template x-if="!end.lat"><span>Où veux-tu finir ?</span></template></p>
                            <button type="button" @click="openMap('end')" class="shrink-0 font-semibold text-ink hover:text-coral inline-flex items-center gap-1"><span class="material-symbols-outlined" style="font-size:16px">pin_drop</span>Sur la carte</button>
                        </div>
                    </div>
                    <p x-show="endMode === 'loop'" x-cloak class="mt-1.5 text-xs text-ink-muted">Le retour au point de départ est compté dans le temps disponible.</p>
                </div>

                </div>
                <div class="space-y-6" :class="{ 'hidden lg:block': step !== 2 }">
                <p class="lg:hidden eyebrow">Étape 2 sur 3 · Quand et combien de temps</p>
                {{-- Quand --}}
                <div>
                    <p class="label flex items-center gap-1.5"><span class="material-symbols-outlined text-ink" style="font-size:16px">calendar_month</span>Quand</p>
                    <div class="grid grid-cols-[1fr_1fr_1.2fr] gap-1.5">
                        <button type="button" @click="date = today" class="chip justify-center" :data-active="date === today">Aujourd'hui</button>
                        <button type="button" @click="date = tomorrow" class="chip justify-center" :data-active="date === tomorrow">Demain</button>
                        <input type="date" x-model="date" :min="today" class="field !py-1.5 text-xs" aria-label="Date">
                    </div>
                    <div class="mt-2 grid grid-cols-2 gap-3">
                        <div><label class="label !mb-1 text-[11px]" for="starts_at">Heure de départ</label><input id="starts_at" type="time" name="starts_at" x-model="time" class="field" :placeholder="date === today ? 'Maintenant' : '10:00'"></div>
                        <div><label class="label !mb-1 text-[11px]" for="budget">Budget (€)</label><input id="budget" type="number" name="budget_eur" min="0" max="1000" step="1" value="{{ old('budget_eur', $params['budget_eur'] ?? 40) }}" class="field" placeholder="Sans limite"></div>
                    </div>
                    <p class="mt-1.5 text-[11px] text-ink-muted" x-text="date === today && !time ? 'Départ maintenant : les horaires d\'ouverture sont vérifiés pour aujourd\'hui.' : 'Les lieux fermés ce jour-là sont écartés automatiquement.'"></p>
                </div>

                {{-- Temps + mobilité --}}
                <div>
                    <div class="flex items-center justify-between"><label class="label" for="duration">Temps disponible</label><span class="text-sm font-semibold" x-text="label(duration)"></span></div>
                    <input id="duration" type="range" name="duration_minutes" min="30" max="600" step="15" x-model="duration" class="w-full accent-coral">
                    <div class="flex justify-between text-[10px] text-ink-muted mt-1"><span>30 min</span><span>2 h</span><span>Demi-journée</span><span>Journée</span></div>
                    <div class="mt-3 grid {{ $transitEnabled ? 'grid-cols-3' : 'grid-cols-2' }} gap-2">
                        @foreach(array_filter(['walk' => ['directions_walk', 'À pied', 'rayon auto ~1 km/h'], 'bike' => ['directions_bike', 'À vélo', 'rayon auto ~2,5 km/h'], 'transit' => $transitEnabled ? ['directions_subway', 'Transports', 'métro, RER, bus'] : null]) as $m => [$icon, $l, $hint])
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

                </div>
                <div class="space-y-6" :class="{ 'hidden lg:block': step !== 3 }">
                <p class="lg:hidden eyebrow">Étape 3 sur 3 · Tes envies</p>
                {{-- Envies --}}
                <div>
                    <p class="label">Envies</p>
                    <div class="flex flex-wrap gap-1.5">
                        @php $selected = old('interests', $params['interests'] ?? ($user?->interests ?: ['musee', 'monument'])); @endphp
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

                {{-- Options --}}
                <div class="space-y-2 text-sm">
                    <label class="flex items-center justify-between gap-3 cursor-pointer rounded-2xl bg-paper px-3 py-2.5"><span class="flex items-center gap-2"><span class="material-symbols-outlined text-ink-muted" style="font-size:18px">restaurant</span>Pause déjeuner dans le parcours</span><input type="hidden" name="with_lunch" value="0"><input type="checkbox" name="with_lunch" value="1" class="rounded border-ink/20 text-coral focus:ring-coral" @checked(old('with_lunch', $params['with_lunch'] ?? false))></label>
                    <label class="flex items-center justify-between gap-3 cursor-pointer rounded-2xl bg-paper px-3 py-2.5"><span class="flex items-center gap-2"><span class="material-symbols-outlined text-ink-muted" style="font-size:18px">rainy</span>Tenir compte de la météo</span><input type="hidden" name="use_weather" value="0"><input type="checkbox" name="use_weather" value="1" class="rounded border-ink/20 text-coral focus:ring-coral" @checked(old('use_weather', $params['use_weather'] ?? true))></label>
                    <label class="flex items-center justify-between gap-3 cursor-pointer rounded-2xl bg-paper px-3 py-2.5"><span class="flex items-center gap-2"><span class="material-symbols-outlined text-ink-muted" style="font-size:18px">accessible</span>Accessible fauteuil ou poussette</span><input type="hidden" name="accessible" value="0"><input type="checkbox" name="accessible" value="1" class="rounded border-ink/20 text-coral focus:ring-coral" @checked(old('accessible', $params['accessible'] ?? false))></label>
                    <label class="flex items-center justify-between gap-3 cursor-pointer rounded-2xl bg-paper px-3 py-2.5"><span class="flex items-center gap-2"><span class="material-symbols-outlined text-ink-muted" style="font-size:18px">loyalty</span>Lieux gratuits uniquement</span><input type="checkbox" name="free_only" value="1" class="rounded border-ink/20 text-coral focus:ring-coral" @checked(old('free_only', $params['free_only'] ?? false))></label>
                    <label class="flex items-center justify-between gap-3 rounded-2xl bg-paper px-3 py-2"><span class="flex items-center gap-2"><span class="material-symbols-outlined text-ink-muted" style="font-size:18px">radar</span>Rayon de recherche</span>
                        <select name="radius_km" class="field !w-auto !py-1.5 text-xs">
                            <option value="">Auto</option>
                            @foreach([2, 4, 6, 10, 15, 25] as $r)<option value="{{ $r }}" @selected((int) old('radius_km', $params['radius_km'] ?? 0) === $r)>{{ $r }} km</option>@endforeach
                        </select>
                    </label>
                </div>

                <button type="submit" class="btn btn-lg btn-primary w-full" :disabled="submitting"><span class="material-symbols-outlined" :class="submitting && 'animate-spin'" x-text="submitting ? 'progress_activity' : 'auto_awesome'"></span><span x-text="submitting ? 'Calcul des trajets réels…' : '{{ $hasResult ? 'Recalculer' : 'Générer mon parcours' }}'"></span></button>
                <button type="submit" name="surprise" value="1" class="btn btn-md btn-soft w-full" :disabled="submitting"><span class="material-symbols-outlined" style="font-size:18px">casino</span>Surprends-moi</button>
                </div>

                {{-- Assistant mobile : précédent / suivant --}}
                <div class="lg:hidden flex items-center gap-2 pt-1">
                    <button type="button" x-show="step > 1" @click="step--" class="btn btn-md btn-soft"><span class="material-symbols-outlined" style="font-size:18px">arrow_back</span>Retour</button>
                    <div class="flex-1 flex justify-center gap-1.5"><template x-for="i in 3" :key="i"><span class="h-1.5 rounded-full transition-all" :class="i === step ? 'w-6 bg-coral' : 'w-2 bg-ink/15'"></span></template></div>
                    <button type="button" x-show="step < 3" @click="step++; $el.closest('form').scrollTo({ top: 0 })" class="btn btn-md btn-ink">Suivant<span class="material-symbols-outlined" style="font-size:18px">arrow_forward</span></button>
                </div>
            </form>

            {{-- ============================================================ Résultat --}}
            <div class="min-w-0">
                @if($result && $hasResult)
                    <button type="button" @click="showForm = true; step = 1" class="lg:hidden btn btn-md btn-soft w-full mb-3"><span class="material-symbols-outlined" style="font-size:18px">tune</span>Modifier les critères</button>
                    @php
                        $v3 = ($result['version'] ?? 2) >= 3;
                        $startsAt = \Illuminate\Support\Carbon::parse($result['starts_at']);
                        $endsAt = \Illuminate\Support\Carbon::parse($result['ends_at']);
                    @endphp
                    @if(!empty($result['variants']) && count($result['variants']) > 1)
                        <div class="mb-3">
                            <p class="eyebrow mb-2">Trois propositions, même départ</p>
                            <div class="grid grid-cols-3 gap-2">
                                @foreach($result['variants'] as $v)
                                    <form method="POST" action="{{ route('itineraries.variant', $v['key']) }}">@csrf
                                        <button type="submit" class="w-full text-left rounded-2xl border-2 p-3 transition {{ $v['active'] ? 'border-ink bg-white shadow-card' : 'border-transparent bg-white/70 hover:bg-white' }}" @disabled($v['active'])>
                                            <span class="material-symbols-outlined {{ $v['active'] ? 'text-coral' : 'text-ink-muted' }}">{{ $v['icon'] }}</span>
                                            <p class="font-semibold text-sm leading-tight mt-1">{{ $v['label'] }}</p>
                                            <p class="text-[11px] text-ink-muted mt-0.5">{{ $v['steps'] }} lieux · {{ floor($v['minutes'] / 60) }} h{{ $v['minutes'] % 60 ? str_pad($v['minutes'] % 60, 2, '0', STR_PAD_LEFT) : '' }} · {{ number_format($v['km'], 1, ',', ' ') }} km</p>
                                            <p class="text-[10px] text-ink-muted mt-1 line-clamp-2 hidden sm:block">{{ implode(' · ', $v['titles']) }}</p>
                                        </button>
                                    </form>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    <div class="card overflow-hidden">
                        <div id="itinerary-map" class="h-[320px] sm:h-[440px]"></div>
                        <div class="p-5 sm:p-6">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="eyebrow">{{ ucfirst($startsAt->translatedFormat('l j F')) }} · départ {{ $startsAt->format('H\hi') }}</p>
                                    <h2 class="display text-2xl sm:text-3xl mt-1">{{ $result['title'] }}</h2>
                                    <p class="text-sm text-ink-muted mt-1 flex flex-wrap items-center gap-x-1.5">
                                        <span class="material-symbols-outlined text-coral" style="font-size:16px">trip_origin</span>{{ $result['start']['label'] }}
                                        @if(!empty($result['end']))<span class="material-symbols-outlined text-ink-muted" style="font-size:16px">arrow_forward</span>{{ $result['end']['label'] }}@endif
                                        · {{ match($result['mode']) { 'bike' => 'à vélo', 'transit' => 'à pied et en transports', default => 'à pied' } }}
                                        @if(($result['routing_source'] ?? '') === 'valhalla') · trajets réels @endif
                                    </p>
                                </div>
                                @if(!empty($result['weather']))
                                    <button type="button" @click="$dispatch('open-weather')" class="inline-flex items-center gap-2 rounded-full bg-sun-soft px-3 py-1.5 text-xs text-amber-800 hover:bg-sun-soft/70">
                                        <span class="material-symbols-outlined filled" style="font-size:18px">{{ $result['weather']['icon'] }}</span>
                                        {{ $result['weather']['label'] }}{{ $result['weather']['temp'] !== null ? ' · ' . round($result['weather']['temp']) . '°' : '' }} · pluie {{ $result['weather']['rain_probability'] }} %
                                    </button>
                                @endif
                            </div>

                            <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-2 text-center">
                                @foreach([
                                    ['schedule', floor($result['total_minutes'] / 60) . ' h ' . str_pad($result['total_minutes'] % 60, 2, '0', STR_PAD_LEFT), 'fin vers ' . $endsAt->format('H\hi')],
                                    [$mobIcon, number_format($result['total_distance_km'], 1, ',', ' ') . ' km', ($v3 ? ($result['travel_share'] ?? 0) . ' % du temps' : 'de trajet')],
                                    ['museum', count(array_filter($steps, fn ($s) => ($s['kind'] ?? 'visit') === 'visit')) . ' lieux', $v3 && ($result['wait_minutes'] ?? 0) > 0 ? $result['wait_minutes'] . ' min d\'attente' : 'sans attente'],
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
                                        <p class="text-xs text-amber-800 bg-sun-soft rounded-xl px-3 py-2 flex items-start gap-2"><span class="material-symbols-outlined shrink-0" style="font-size:16px">info</span>{{ $w }}</p>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Timeline --}}
                            <ol class="mt-6 relative">
                                <div class="absolute left-[15px] top-4 bottom-4 w-0.5 bg-ink/10"></div>
                                <li class="relative pl-11 pb-5">
                                    <span class="absolute left-0 top-0 h-8 w-8 rounded-full bg-coral text-white flex items-center justify-center"><span class="material-symbols-outlined" style="font-size:16px">flag</span></span>
                                    <p class="text-sm font-semibold">{{ $result['start']['label'] }}</p>
                                    <p class="text-xs text-ink-muted">Départ à {{ $startsAt->format('H\hi') }}</p>
                                </li>
                                @foreach($steps as $step)
                                    @php $lunch = ($step['kind'] ?? 'visit') === 'lunch'; $h = $step['hours'] ?? null; @endphp
                                    <li class="relative pl-11 pb-5">
                                        <span class="absolute left-0 top-0 h-8 w-8 rounded-full flex items-center justify-center text-sm font-bold {{ $lunch ? 'bg-sun text-ink' : 'bg-ink text-white' }}">@if($lunch)<span class="material-symbols-outlined" style="font-size:16px">restaurant</span>@else{{ $step['order'] }}@endif</span>
                                        <p class="text-[11px] text-ink-muted mb-1.5 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                            @if(!empty($step['transit']))
                                                <span class="inline-flex flex-wrap items-center gap-1"><span class="material-symbols-outlined" style="font-size:14px">directions_subway</span>{{ $step['travel_minutes'] }} min ·
                                                    @foreach($step['transit']['lines'] as $line)<span class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-bold" style="background: {{ $line['color'] }}; color: {{ $line['text_color'] }}">{{ $line['mode'] }} {{ $line['code'] }}</span>@endforeach
                                                    <span>· {{ $step['transit']['walking_min'] }} min à pied</span></span>
                                            @else
                                                <span class="inline-flex items-center gap-1"><span class="material-symbols-outlined" style="font-size:14px">{{ ($step['travel_mode'] ?? '') === 'bike' ? 'directions_bike' : 'directions_walk' }}</span>{{ $step['travel_minutes'] }} min · {{ number_format($step['travel_km'], 1, ',', ' ') }} km</span>
                                            @endif
                                            @if(($step['wait_minutes'] ?? 0) > 0)<span class="inline-flex items-center gap-1 text-amber-700"><span class="material-symbols-outlined" style="font-size:14px">hourglass_top</span>{{ $step['wait_minutes'] }} min d'attente avant l'ouverture</span>@endif
                                        </p>
                                        <a href="{{ route('places.show', $step['place_id']) }}" class="card card-hover flex gap-3 p-2.5 {{ $lunch ? 'border-sun/60' : '' }}">
                                            <div class="w-24 h-24 rounded-xl overflow-hidden shrink-0 placeholder-cover flex items-center justify-center">
                                                @if($step['cover'])<img src="{{ $step['cover'] }}" alt="" loading="lazy" class="w-full h-full object-cover">@else<span class="material-symbols-outlined text-white/80">{{ $lunch ? 'restaurant' : 'place' }}</span>@endif
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">{{ $lunch ? 'Pause déjeuner' : $step['category'] }}</p>
                                                <p class="font-semibold leading-snug line-clamp-2">{{ $step['title'] }}</p>
                                                <p class="text-xs text-ink-muted mt-0.5">{{ $step['arrive_at'] }} → {{ $step['leave_at'] }} · {{ $step['visit_minutes'] }} min sur place{{ $step['is_free'] ? ' · gratuit' : ($step['cost_eur'] ? ' · ≈ ' . number_format($step['cost_eur'], 0) . ' €' : '') }}</p>
                                                <div class="mt-1.5 flex flex-wrap gap-1">
                                                    @if($h)
                                                        @if($h['status'] === 'open')
                                                            <span class="badge badge-free !text-[10px]"><span class="material-symbols-outlined" style="font-size:12px">schedule</span>Ouvert {{ $h['opens'] }}–{{ $h['closes'] }}</span>
                                                        @else
                                                            <span class="badge bg-paper-deep text-ink-muted !text-[10px]"><span class="material-symbols-outlined" style="font-size:12px">help</span>Horaires à vérifier</span>
                                                        @endif
                                                    @endif
                                                    @if(array_key_exists('accessible', $step) && $step['accessible'] === true)<span class="badge badge-free !text-[10px]" title="{{ $step['accessibility_note'] ?? '' }}"><span class="material-symbols-outlined" style="font-size:12px">accessible</span>PMR</span>@elseif(!empty($result['accessible']) && ($step['accessible'] ?? null) === null)<span class="badge bg-paper-deep text-ink-muted !text-[10px]"><span class="material-symbols-outlined" style="font-size:12px">accessible</span>Accès à vérifier</span>@endif
                                                    <span class="badge bg-teal-soft text-teal-dark !text-[10px]"><span class="material-symbols-outlined" style="font-size:12px">auto_awesome</span>{{ $step['reason'] }}</span>
                                                </div>
                                            </div>
                                        </a>
                                        @if(!empty($step['conflict']))
                                            <p class="mt-1.5 text-[11px] text-coral-dark bg-coral-soft rounded-xl px-3 py-1.5 flex items-center gap-1.5"><span class="material-symbols-outlined" style="font-size:14px">warning</span>La visite finirait après la fermeture ({{ $step['hours']['closes'] ?? '' }}).</p>
                                        @endif
                                        {{-- Outils d'édition de l'étape --}}
                                        @php $i = $loop->index; $last = $loop->last; @endphp
                                        <div class="mt-1.5 flex flex-wrap items-center gap-1">
                                            <form method="POST" action="{{ route('itineraries.step-move', $i) }}">@csrf<input type="hidden" name="direction" value="up"><button class="btn btn-icon btn-ghost !h-8 !w-8" title="Monter" @disabled($loop->first)><span class="material-symbols-outlined" style="font-size:18px">arrow_upward</span></button></form>
                                            <form method="POST" action="{{ route('itineraries.step-move', $i) }}">@csrf<input type="hidden" name="direction" value="down"><button class="btn btn-icon btn-ghost !h-8 !w-8" title="Descendre" @disabled($last)><span class="material-symbols-outlined" style="font-size:18px">arrow_downward</span></button></form>
                                            <form method="POST" action="{{ route('itineraries.step-duration', $i) }}">@csrf<input type="hidden" name="delta" value="-15"><button class="btn btn-icon btn-ghost !h-8 !w-8" title="15 min de moins" @disabled($step['visit_minutes'] <= 15)><span class="material-symbols-outlined" style="font-size:18px">remove</span></button></form>
                                            <span class="text-[11px] font-semibold tabular-nums">{{ $step['visit_minutes'] }} min</span>
                                            <form method="POST" action="{{ route('itineraries.step-duration', $i) }}">@csrf<input type="hidden" name="delta" value="15"><button class="btn btn-icon btn-ghost !h-8 !w-8" title="15 min de plus"><span class="material-symbols-outlined" style="font-size:18px">add</span></button></form>
                                            <span class="flex-1"></span>
                                            @if(!$lunch)
                                                <form method="POST" action="{{ route('itineraries.step-lock', $i) }}">@csrf<button class="btn btn-icon !h-8 !w-8 {{ !empty($step['locked']) ? 'btn-ink' : 'btn-ghost' }}" title="{{ !empty($step['locked']) ? 'Déverrouiller' : 'Garder ce lieu au recalcul' }}"><span class="material-symbols-outlined {{ !empty($step['locked']) ? 'filled' : '' }}" style="font-size:18px">{{ !empty($step['locked']) ? 'lock' : 'lock_open' }}</span></button></form>
                                            @endif
                                            <form method="POST" action="{{ route('itineraries.step-replace', $i) }}">@csrf<button class="btn btn-icon btn-ghost !h-8 !w-8" title="Remplacer par un lieu similaire"><span class="material-symbols-outlined" style="font-size:18px">swap_horiz</span></button></form>
                                            <form method="POST" action="{{ route('itineraries.step-remove', $i) }}">@csrf<button class="btn btn-icon btn-ghost !h-8 !w-8 hover:text-coral" title="Retirer" @disabled(count($steps) <= 1)><span class="material-symbols-outlined" style="font-size:18px">close</span></button></form>
                                        </div>
                                        @if(!empty($step['alternative']))
                                            <a href="{{ route('places.show', $step['alternative']['place_id']) }}" class="mt-1.5 flex items-center gap-2 rounded-xl bg-sky-50 px-3 py-2 text-xs text-sky-800 hover:bg-sky-100">
                                                <span class="material-symbols-outlined" style="font-size:16px">umbrella</span>
                                                <span class="min-w-0 truncate">Plan B s'il pleut : <span class="font-semibold">{{ $step['alternative']['title'] }}</span>{{ $step['alternative']['minutes_away'] !== null ? ' · à ' . $step['alternative']['minutes_away'] . ' min' : '' }}</span>
                                            </a>
                                        @endif
                                    </li>
                                @endforeach
                                <li class="relative pl-11">
                                    <span class="absolute left-0 top-0 h-8 w-8 rounded-full bg-paper-deep text-ink flex items-center justify-center"><span class="material-symbols-outlined" style="font-size:16px">sports_score</span></span>
                                    <p class="text-sm font-semibold">{{ $result['end']['label'] ?? 'Fin du parcours' }}</p>
                                    <p class="text-xs text-ink-muted">@if(!empty($result['end']))<span class="material-symbols-outlined align-middle" style="font-size:14px">{{ $mobIcon }}</span> {{ $result['end']['travel_minutes'] ?? 0 }} min · {{ number_format($result['end']['travel_km'] ?? 0, 1, ',', ' ') }} km · arrivée @endif vers {{ $endsAt->format('H\hi') }}</p>
                                </li>
                            </ol>

                            @php
                                $coords = collect($steps)->map(fn ($s) => $s['lat'] . ',' . $s['lng'])->all();
                                $origin = $result['start']['lat'] . ',' . $result['start']['lng'];
                                $destination = !empty($result['end']) ? $result['end']['lat'] . ',' . $result['end']['lng'] : end($coords);
                                $waypoints = !empty($result['end']) ? $coords : array_slice($coords, 0, -1);
                                $gmUrl = 'https://www.google.com/maps/dir/?api=1&origin=' . $origin . '&destination=' . $destination . ($waypoints ? '&waypoints=' . implode('|', $waypoints) : '') . '&travelmode=' . ($result['mode'] === 'bike' ? 'bicycling' : 'walking');
                            @endphp
                            <div class="mt-6 flex flex-wrap gap-2">
                                <a href="{{ route('itineraries.navigate') }}" class="btn btn-md btn-primary"><span class="material-symbols-outlined" style="font-size:18px">navigation</span>Suivre le parcours</a>
                                <a href="{{ $gmUrl }}" target="_blank" rel="noopener" class="btn btn-md btn-soft"><span class="material-symbols-outlined" style="font-size:18px">open_in_new</span>Google Maps</a>
                                @auth
                                    @if(!empty($result['itinerary_id']))
                                        <a href="{{ route('itineraries.show', $result['itinerary_id']) }}" class="btn btn-md btn-soft"><span class="material-symbols-outlined" style="font-size:18px">bookmark</span>Enregistré · voir la fiche</a>
                                    @endif
                                @else
                                    <a href="{{ route('register') }}" class="btn btn-md btn-soft"><span class="material-symbols-outlined" style="font-size:18px">bookmark</span>Créer un compte pour le garder</a>
                                @endauth
                                <button type="button" @click="navigator.clipboard.writeText(@js($gmUrl)); $dispatch('toast', 'Lien copié')" class="btn btn-md btn-ghost"><span class="material-symbols-outlined" style="font-size:18px">share</span>Partager</button>
                            </div>
                        </div>
                    </div>
                @elseif($result)
                    <button type="button" @click="showForm = true; step = 1" class="lg:hidden btn btn-md btn-soft w-full mb-3"><span class="material-symbols-outlined" style="font-size:18px">tune</span>Modifier les critères</button>
                    <div class="card p-8 text-center">
                        <span class="material-symbols-outlined text-4xl text-ink-muted">explore_off</span>
                        <p class="mt-3 font-semibold">Aucun parcours possible avec ces paramètres.</p>
                        <ul class="mt-2 text-sm text-ink-muted space-y-1">@foreach($result['warnings'] ?? [] as $w)<li>{{ $w }}</li>@endforeach</ul>
                        <p class="mt-3 text-sm text-ink-muted">Élargis le rayon, augmente le temps ou le budget, change de jour ou de point de départ.</p>
                    </div>
                @else
                    <div class="card p-8 sm:p-12 text-center">
                        <div class="mx-auto h-16 w-16 rounded-3xl bg-coral-soft text-coral flex items-center justify-center"><span class="material-symbols-outlined" style="font-size:32px">auto_awesome</span></div>
                        <h2 class="display text-2xl mt-4">Prêt quand tu l'es.</h2>
                        <p class="mt-2 text-sm text-ink-muted max-w-md mx-auto">Indique d'où tu pars, quand, combien de temps tu as. CAMINO choisit des lieux ouverts à ce moment-là, optimise l'ordre, calcule les vrais trajets et te donne l'heure d'arrivée à chaque étape.</p>
                        <div class="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-3 text-left text-sm">
                            @foreach([['schedule', 'Horaires vérifiés', 'Les lieux fermés ce jour-là sont écartés, l\'attente est calculée.'], ['route', 'Trajets réels', 'Rues et durées OpenStreetMap, à pied ou à vélo, ordre optimisé.'], ['umbrella', 'Météo et plan B', 'S\'il pleut, on privilégie le couvert et chaque étape dehors a une alternative.']] as [$i, $t, $d])
                                <div class="rounded-2xl bg-paper p-4"><span class="material-symbols-outlined text-teal">{{ $i }}</span><p class="font-semibold mt-2">{{ $t }}</p><p class="text-xs text-ink-muted mt-1">{{ $d }}</p></div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- ============================================================ Sélecteur sur la carte --}}
            <div x-cloak x-show="picker.open" class="fixed inset-0 z-[1300] flex items-end sm:items-center justify-center">
                <div class="absolute inset-0 bg-ink/50 backdrop-blur-sm" @click="closeMap()"></div>
                <div class="relative w-full sm:max-w-lg card rounded-b-none sm:rounded-3xl overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-3">
                        <p class="font-semibold" x-text="picker.target === 'start' ? 'Choisis ton point de départ' : 'Choisis ton point d\'arrivée'"></p>
                        <button type="button" @click="closeMap()" class="btn btn-icon btn-ghost" aria-label="Fermer"><span class="material-symbols-outlined">close</span></button>
                    </div>
                    <div class="relative h-[55vh] sm:h-[420px]">
                        <div x-ref="pickerMap" class="absolute inset-0"></div>
                        <div class="pointer-events-none absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-full z-[500]"><span class="material-symbols-outlined filled text-coral drop-shadow" style="font-size:44px">location_on</span></div>
                    </div>
                    <div class="p-4 flex items-center justify-between gap-3">
                        <p class="text-xs text-ink-muted min-w-0 truncate" x-text="picker.label || 'Déplace la carte, le repère reste au centre.'"></p>
                        <button type="button" @click="confirmMap()" class="btn btn-md btn-primary shrink-0"><span class="material-symbols-outlined" style="font-size:18px">check</span>Valider</button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @push('scripts')
    <script>
        function addressSearch(target) {
            return {
                q: '', results: [], open: false, loading: false,
                async search() {
                    if (this.q.trim().length < 3) { this.results = []; this.open = false; return; }
                    this.loading = true;
                    try {
                        const r = await fetch('/api/v1/geocode?q=' + encodeURIComponent(this.q), { headers: { Accept: 'application/json' } });
                        this.results = r.ok ? await r.json() : [];
                    } catch (e) { this.results = []; }
                    this.loading = false; this.open = true;
                },
                pick(r) { this.q = r.label; this.open = false; this.$dispatch('picked', { target, lat: r.lat, lng: r.lng, label: r.label }); },
            };
        }
        function itineraryForm(init) {
            let pickerMap = null;
            return {
                ...init, locating: false, submitting: false,
                picker: { open: false, target: 'start', label: '' },
                init() {
                    this.$el.addEventListener('picked', (e) => { const d = e.detail; this[d.target] = { lat: d.lat, lng: d.lng, label: d.label }; if (d.target === 'start') this.startMode = 'address'; });
                    if (this.startMode === 'me' && !this.start.lat) this.useMe();
                },
                label(m) { return m < 60 ? m + ' min' : Math.floor(m / 60) + ' h' + (m % 60 ? ' ' + String(m % 60).padStart(2, '0') : ''); },
                useMe() {
                    this.startMode = 'me'; this.locating = true;
                    window.Camino.locate().then(async (p) => {
                        this.start = { lat: +p.lat.toFixed(6), lng: +p.lng.toFixed(6), label: 'Ma position' };
                        this.locating = false;
                        try { const r = await fetch(`/api/v1/geocode/reverse?lat=${p.lat}&lng=${p.lng}`); const j = await r.json(); if (j.label) this.start.label = j.label; } catch (e) {}
                    }).catch(() => { this.locating = false; this.startMode = 'address'; this.$dispatch('toast', 'Position indisponible : saisis une adresse.'); });
                },
                openMap(target) {
                    this.picker = { open: true, target, label: '' };
                    const current = this[target].lat ? this[target] : (this.start.lat ? this.start : this.paris);
                    this.$nextTick(() => {
                        const el = this.$refs.pickerMap;
                        if (!pickerMap) {
                            pickerMap = L.map(el, { zoomControl: false }).setView([current.lat, current.lng], 14);
                            window.Camino.tileLayer().addTo(pickerMap);
                            L.control.zoom({ position: 'bottomright' }).addTo(pickerMap);
                            pickerMap.on('moveend', () => { const c = pickerMap.getCenter(); this.picker.label = c.lat.toFixed(5) + ', ' + c.lng.toFixed(5); });
                            // La carte naît dans une boîte encore invisible : on recalcule sa taille dès qu'elle change.
                            if (window.ResizeObserver) new ResizeObserver(() => pickerMap.invalidateSize()).observe(el);
                        } else {
                            pickerMap.setView([current.lat, current.lng], 14);
                        }
                        [100, 350, 800].forEach(ms => setTimeout(() => pickerMap.invalidateSize(), ms));
                    });
                },
                closeMap() { this.picker.open = false; },
                async confirmMap() {
                    const c = pickerMap.getCenter();
                    const target = this.picker.target;
                    this[target] = { lat: +c.lat.toFixed(6), lng: +c.lng.toFixed(6), label: 'Point sur la carte' };
                    if (target === 'start') this.startMode = 'map'; else this.endMode = 'point';
                    this.picker.open = false;
                    try { const r = await fetch(`/api/v1/geocode/reverse?lat=${c.lat}&lng=${c.lng}`); const j = await r.json(); if (j.label) this[target].label = j.label; } catch (e) {}
                },
                beforeSubmit(e) {
                    if (this.startMode !== 'paris' && !this.start.lat) { e.preventDefault(); this.$dispatch('toast', 'Choisis un point de départ (adresse, position ou carte).'); return; }
                    if (this.endMode === 'point' && !this.end.lat) { e.preventDefault(); this.$dispatch('toast', 'Indique une adresse d\'arrivée.'); return; }
                    this.submitting = true;
                },
            };
        }
        @if($result && $hasResult)
        document.addEventListener('DOMContentLoaded', () => {
            const el = document.getElementById('itinerary-map');
            if (!el || !window.L) return;
            const C = window.Camino;
            const result = @js(['start' => $result['start'], 'end' => $result['end'] ?? null, 'steps' => collect($steps)->map(fn ($s) => ['lat' => $s['lat'], 'lng' => $s['lng'], 'title' => $s['title'], 'order' => $s['order'], 'arrive' => $s['arrive_at'], 'slug' => $s['category_slug'], 'kind' => $s['kind'] ?? 'visit'])->all(), 'geometry' => $result['geometry'], 'mode' => $result['mode'], 'legs' => collect($result['legs'] ?? [])->map(fn ($l) => ['transit' => $l['transit'] ?? false, 'shape' => $l['shape'] ?? [], 'color' => $l['lines'][0]['color'] ?? null])->all()]);
            const map = L.map(el, { zoomControl: false, scrollWheelZoom: false });
            C.tileLayer().addTo(map);
            L.control.zoom({ position: 'bottomright' }).addTo(map);
            const pts = (result.geometry && result.geometry.length > 1) ? result.geometry : [[result.start.lat, result.start.lng], ...result.steps.map(s => [s.lat, s.lng])];
            L.polyline(pts, { color: '#12161C', weight: 7, opacity: 0.25 }).addTo(map);
            const line = L.polyline(pts, { color: result.mode === 'bike' ? '#0F8B8D' : '#FF5A3C', weight: 4, opacity: result.mode === 'transit' ? 0.35 : 0.95, lineJoin: 'round' }).addTo(map);
            (result.legs || []).forEach(l => { if (l.shape && l.shape.length > 1) L.polyline(l.shape, { color: l.transit ? (l.color || '#1D4ED8') : '#FF5A3C', weight: l.transit ? 5 : 4, opacity: 0.95, dashArray: l.transit ? '8 8' : null, lineJoin: 'round' }).addTo(map); });
            L.marker([result.start.lat, result.start.lng], { icon: C.stepIcon(0, true) }).addTo(map).bindPopup('<div class="p-3 text-sm font-semibold">Départ</div>');
            result.steps.forEach(s => L.marker([s.lat, s.lng], { icon: s.kind === 'lunch' ? C.placeIcon('restauration', { size: 30 }) : C.stepIcon(s.order) }).addTo(map).bindPopup(`<div class="p-3"><p class="text-sm font-semibold">${s.kind === 'lunch' ? '🍽️ ' : s.order + '. '}${C.escapeHtml(s.title)}</p><p class="text-xs text-ink-muted">Arrivée ${s.arrive}</p></div>`));
            if (result.end) L.marker([result.end.lat, result.end.lng], { icon: C.stepIcon('<span class="material-symbols-outlined" style="font-size:16px">sports_score</span>') }).addTo(map).bindPopup('<div class="p-3 text-sm font-semibold">Arrivée</div>');
            map.fitBounds(line.getBounds(), { padding: [30, 30] });
        });
        @endif
    </script>
    @endpush
</x-app-layout>
