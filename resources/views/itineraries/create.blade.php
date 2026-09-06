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
<x-app-layout title="{{ __('Générer un parcours') }}">
    <section class="max-w-7xl mx-auto px-4 sm:px-6 pt-6 sm:pt-12">
        <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
            <div>
                <p class="eyebrow mb-1.5">{{ __('Générateur de parcours') }}</p>
                <h1 class="display text-3xl sm:text-5xl">{{ __('Ton parcours, calculé pour de vrai.') }}</h1>
                <p class="mt-3 text-ink-muted max-w-2xl">{{ __('Lieux choisis selon tes envies, la météo et leurs horaires d\'ouverture, ordre optimisé, trajets réels dans les rues, heure d\'arrivée à chaque étape.') }}</p>
            </div>
            @auth<a href="{{ route('itineraries.index') }}" class="btn btn-sm btn-soft"><span class="material-symbols-outlined" style="font-size:16px">history</span>{{ __('Mes parcours') }}</a>@endauth
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[420px_1fr] gap-6 items-start" x-data="itineraryForm(@js($initial))">
            {{-- ============================================================ Formulaire --}}
            <div x-show="hasResult && showForm" x-cloak @click="showForm = false" class="lg:hidden fixed inset-0 z-[1150] bg-ink/40 backdrop-blur-sm"></div>
            <form method="POST" action="{{ route('itineraries.store') }}" class="card p-4 sm:p-6 space-y-5 lg:sticky lg:top-24 min-w-0 {{ $hasResult ? 'max-lg:fixed max-lg:inset-x-0 max-lg:bottom-0 max-lg:z-[1200] max-lg:max-h-[90vh] max-lg:overflow-y-auto max-lg:rounded-b-none max-lg:shadow-float' : '' }}" :class="{ 'hidden lg:block': hasResult && !showForm }" @submit="beforeSubmit">
                @csrf
                @if($hasResult)
                    <div class="lg:hidden flex items-center justify-between -mt-1">
                        <p class="font-semibold">{{ __('Modifier le parcours') }}</p>
                        <button type="button" @click="showForm = false" class="btn btn-icon btn-ghost" aria-label="{{ __('Fermer') }}"><span class="material-symbols-outlined">close</span></button>
                    </div>
                @endif
                <input type="hidden" name="start_lat" :value="startMode === 'paris' ? '' : (start.lat ?? '')">
                <input type="hidden" name="start_lng" :value="startMode === 'paris' ? '' : (start.lng ?? '')">
                <input type="hidden" name="start_label" :value="startMode === 'paris' ? '' : (start.label || (startMode === 'me' ? @js(__('Ma position')) : @js(__('Point sur la carte'))))">
                <input type="hidden" name="end_mode" :value="endMode">
                <input type="hidden" name="end_lat" :value="endMode === 'point' ? (end.lat ?? '') : ''">
                <input type="hidden" name="end_lng" :value="endMode === 'point' ? (end.lng ?? '') : ''">
                <input type="hidden" name="end_label" :value="endMode === 'point' ? end.label : ''">
                <input type="hidden" name="date" :value="date">

                {{-- Étapes de l'assistant (mobile) : Où · Quand · Envies --}}
                <div class="lg:hidden">
                    <ol class="grid grid-cols-3 gap-1.5">
                        @foreach([1 => ['pin', __('Où')], 2 => ['clock', __('Quand')], 3 => ['sparkle', __('Envies')]] as $n => [$picto, $label])
                            <li>
                                <button type="button" @click="step = {{ $n }}" class="w-full flex items-center gap-2 rounded-2xl px-2.5 py-2 text-left transition" :class="step === {{ $n }} ? 'bg-ink text-white' : (step > {{ $n }} ? 'bg-teal-soft text-teal-dark' : 'bg-paper text-ink-muted')">
                                    <span class="h-7 w-7 rounded-full flex items-center justify-center text-[11px] font-bold shrink-0" :class="step === {{ $n }} ? 'bg-white/15' : 'bg-white/70'"><template x-if="step > {{ $n }}"><span class="material-symbols-outlined" style="font-size:16px">check</span></template><template x-if="step <= {{ $n }}"><span>{{ $n }}</span></template></span>
                                    <span class="text-xs font-semibold truncate">{{ $label }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ol>
                </div>

                @if($itineraryPlaces->isNotEmpty())
                    <div class="rounded-2xl bg-teal-soft p-4 text-sm">
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <p class="font-semibold text-teal-dark"><span class="material-symbols-outlined align-middle" style="font-size:18px">playlist_add_check</span> {{ __('Ta sélection') }} ({{ $itineraryPlaces->count() }})</p>
                            <button formaction="{{ route('itineraries.clear-places') }}" class="text-xs text-teal-dark underline">{{ __('Vider') }}</button>
                        </div>
                        <ul class="space-y-1 text-xs text-ink-soft max-h-28 overflow-y-auto">
                            @foreach($itineraryPlaces as $p)<li class="truncate">• {{ $p->title }}</li>@endforeach
                        </ul>
                        <p class="mt-2 text-[11px] text-teal-dark">{{ __('Le parcours enchaînera ces lieux dans cet ordre, avec les trajets réels et leurs horaires.') }}</p>
                    </div>
                @endif

                @if($errors->any())
                    <div class="rounded-2xl bg-coral-soft text-coral-dark px-4 py-3 text-sm space-y-0.5">@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>
                @endif

                {{-- ============================== 1 · Où --}}
                <div class="space-y-5" :class="{ 'hidden lg:block': step !== 1 }">
                    <div>
                        <p class="label flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-coral"></span>{{ __('Départ') }}</p>
                        <div class="grid grid-cols-3 gap-2">
                            <button type="button" @click="useMe()" class="tile" :data-active="startMode === 'me'"><x-picto name="me" :size="34" /><span class="tile-label">{{ __('Ma position') }}</span><span x-show="locating" class="tile-hint animate-pulse">{{ __('Recherche…') }}</span></button>
                            <button type="button" @click="startMode = 'address'; $nextTick(() => $refs.startSearch?.focus())" class="tile" :data-active="startMode === 'address' || startMode === 'map'"><x-picto name="address" :size="34" /><span class="tile-label">{{ __('Adresse') }}</span><span class="tile-hint">{{ __('ou sur la carte') }}</span></button>
                            <button type="button" @click="startMode = 'paris'; start = { lat: null, lng: null, label: '' }" class="tile" :data-active="startMode === 'paris'"><x-picto name="city" :size="34" /><span class="tile-label">{{ __('Paris') }}</span><span class="tile-hint">{{ __('centre') }}</span></button>
                        </div>
                        <div x-show="startMode !== 'paris'" x-cloak class="mt-2">
                            <div x-show="startMode === 'address' || startMode === 'map'" x-data="addressSearch('start')" class="relative">
                                <input x-ref="startSearch" type="search" x-model="q" @input.debounce.250ms="search()" @focus="open = results.length > 0" @keydown.escape="open = false" placeholder="{{ __('Numéro, rue, ville…') }}" class="field pr-10" autocomplete="off">
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-ink-muted" style="font-size:18px" x-text="loading ? 'progress_activity' : 'search'"></span>
                                <div x-show="open" x-cloak @click.outside="open = false" class="absolute z-30 mt-1 w-full card p-1 max-h-60 overflow-y-auto">
                                    <template x-for="r in results" :key="r.label">
                                        <button type="button" @click="pick(r)" class="w-full text-left px-3 py-2 rounded-xl hover:bg-paper text-sm flex items-start gap-2"><span class="material-symbols-outlined text-ink-muted mt-0.5" style="font-size:16px" x-text="r.type === 'place' ? 'place' : 'signpost'"></span><span x-text="r.label"></span></button>
                                    </template>
                                    <p x-show="!loading && results.length === 0 && q.length >= 3" class="px-3 py-2 text-xs text-ink-muted">{{ __('Aucune adresse trouvée en Île-de-France.') }}</p>
                                </div>
                            </div>
                            <div class="mt-1.5 flex items-center justify-between gap-2 text-xs">
                                <p class="text-ink-muted truncate min-w-0"><template x-if="start.lat"><span><span class="material-symbols-outlined align-middle text-teal" style="font-size:14px">check_circle</span> <span x-text="start.label || @js(__('Point choisi'))"></span></span></template><template x-if="!start.lat && !locating"><span>{{ __('D\'où pars-tu ?') }}</span></template></p>
                                <button type="button" @click="openMap('start')" class="shrink-0 font-semibold text-ink hover:text-coral inline-flex items-center gap-1"><span class="material-symbols-outlined" style="font-size:16px">pin_drop</span>{{ __('Sur la carte') }}</button>
                            </div>
                        </div>
                    </div>

                    <div>
                        <p class="label flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-ink"></span>{{ __('Arrivée') }}</p>
                        <div class="grid grid-cols-3 gap-2">
                            <button type="button" @click="endMode = 'open'" class="tile" :data-active="endMode === 'open'"><x-picto name="open" :size="34" /><span class="tile-label">{{ __('Libre') }}</span><span class="tile-hint">{{ __('où le parcours finit') }}</span></button>
                            <button type="button" @click="endMode = 'loop'" class="tile" :data-active="endMode === 'loop'"><x-picto name="loop" :size="34" /><span class="tile-label">{{ __('Boucle') }}</span><span class="tile-hint">{{ __('retour au départ') }}</span></button>
                            <button type="button" @click="endMode = 'point'; $nextTick(() => $refs.endSearch?.focus())" class="tile" :data-active="endMode === 'point'"><x-picto name="flag" :size="34" /><span class="tile-label">{{ __('Adresse') }}</span><span class="tile-hint">{{ __('gare, hôtel…') }}</span></button>
                        </div>
                        <div x-show="endMode === 'point'" x-cloak class="mt-2">
                            <div x-data="addressSearch('end')" class="relative">
                                <input x-ref="endSearch" type="search" x-model="q" @input.debounce.250ms="search()" @focus="open = results.length > 0" @keydown.escape="open = false" placeholder="{{ __('Adresse d\'arrivée (gare, hôtel…)') }}" class="field pr-10" autocomplete="off">
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-ink-muted" style="font-size:18px" x-text="loading ? 'progress_activity' : 'search'"></span>
                                <div x-show="open" x-cloak @click.outside="open = false" class="absolute z-30 mt-1 w-full card p-1 max-h-60 overflow-y-auto">
                                    <template x-for="r in results" :key="r.label"><button type="button" @click="pick(r)" class="w-full text-left px-3 py-2 rounded-xl hover:bg-paper text-sm" x-text="r.label"></button></template>
                                </div>
                            </div>
                            <div class="mt-1.5 flex items-center justify-between gap-2 text-xs">
                                <p class="text-ink-muted truncate min-w-0"><template x-if="end.lat"><span><span class="material-symbols-outlined align-middle text-teal" style="font-size:14px">check_circle</span> <span x-text="end.label || @js(__('Point choisi'))"></span></span></template><template x-if="!end.lat"><span>{{ __('Où veux-tu finir ?') }}</span></template></p>
                                <button type="button" @click="openMap('end')" class="shrink-0 font-semibold text-ink hover:text-coral inline-flex items-center gap-1"><span class="material-symbols-outlined" style="font-size:16px">pin_drop</span>{{ __('Sur la carte') }}</button>
                            </div>
                        </div>
                        <p x-show="endMode === 'loop'" x-cloak class="mt-1.5 text-xs text-ink-muted">{{ __('Le retour au point de départ est compté dans le temps disponible.') }}</p>
                    </div>
                </div>

                {{-- ============================== 2 · Quand, combien de temps, comment --}}
                <div class="space-y-5" :class="{ 'hidden lg:block': step !== 2 }">
                    <div>
                        <p class="label">{{ __('Quand') }}</p>
                        <div class="grid grid-cols-3 gap-2">
                            <button type="button" @click="date = today" class="tile" :data-active="date === today"><x-picto name="today" :size="34" /><span class="tile-label">{{ __('Aujourd\'hui') }}</span></button>
                            <button type="button" @click="date = tomorrow" class="tile" :data-active="date === tomorrow"><x-picto name="tomorrow" :size="34" /><span class="tile-label">{{ __('Demain') }}</span></button>
                            <label class="tile cursor-pointer" :data-active="date !== today && date !== tomorrow"><x-picto name="calendar" :size="34" /><span class="tile-label" x-text="date !== today && date !== tomorrow ? dateLabel(date) : @js(__('Une date'))"></span><input type="date" x-model="date" :min="today" class="sr-only" aria-label="{{ __('Date') }}" @click.stop></label>
                        </div>
                        {{-- Formules rapides : heure + durée d'un coup --}}
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach([['morning', __('Matinée'), '09:30', 180], ['afternoon', __('Après-midi'), '14:00', 240], ['day', __('Journée'), '10:00', 420], ['evening', __('Fin de journée'), '17:00', 150]] as [$k, $l, $t, $d])
                                <button type="button" @click="preset('{{ $t }}', {{ $d }})" class="chip !py-1 text-[11px]" :data-active="time === '{{ $t }}' && +duration === {{ $d }}">{{ $l }}</button>
                            @endforeach
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-3">
                            <div><label class="label !mb-1 text-[11px]" for="starts_at">{{ __('Heure de départ') }}</label><input id="starts_at" type="time" name="starts_at" x-model="time" class="field" :placeholder="date === today ? @js(__('Maintenant')) : '10:00'"><p class="mt-1 text-[10px] text-ink-muted" x-show="date === today && !time">{{ __('Vide = maintenant') }}</p></div>
                            <div><label class="label !mb-1 text-[11px]" for="budget">{{ __('Budget (€)') }}</label><input id="budget" type="number" name="budget_eur" min="0" max="1000" step="1" value="{{ old('budget_eur', $params['budget_eur'] ?? 40) }}" class="field"></div>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-end justify-between gap-2">
                            <label class="label !mb-0" for="duration">{{ __('Temps disponible') }}</label>
                            <p class="text-right"><span class="font-display text-2xl leading-none" x-text="label(duration)"></span><span class="block text-[10px] text-ink-muted" x-text="'≈ ' + estStops + ' ' + @js(__('lieux'))"></span></p>
                        </div>
                        <input id="duration" type="range" name="duration_minutes" min="30" max="600" step="15" x-model="duration" class="w-full accent-coral mt-2">
                        <div class="mt-1 grid grid-cols-4 gap-1.5">
                            @foreach([[60, __('1 h')], [120, __('2 h')], [240, __('Demi-journée')], [420, __('Journée')]] as [$d, $l])
                                <button type="button" @click="duration = {{ $d }}" class="chip justify-center !py-1 text-[11px]" :data-active="+duration === {{ $d }}">{{ $l }}</button>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <p class="label">{{ __('Comment tu te déplaces') }}</p>
                        <div class="grid {{ $transitEnabled ? 'grid-cols-3' : 'grid-cols-2' }} gap-2">
                            @foreach(array_filter(['walk' => ['walk', __('À pied'), __('rayon 3 km')], 'bike' => ['bike', __('À vélo'), __('rayon 8 km')], 'transit' => $transitEnabled ? ['transit', __('Transports'), __('métro, RER, bus')] : null]) as $m => [$picto, $l, $hint])
                                <label class="tile cursor-pointer !py-3" :data-active="mode === '{{ $m }}'">
                                    <input type="radio" name="mode" value="{{ $m }}" class="sr-only" x-model="mode">
                                    <x-picto :name="$picto" :size="40" />
                                    <span class="tile-label">{{ $l }}</span>
                                    <span class="tile-hint">{{ $hint }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- ============================== 3 · Envies et options --}}
                <div class="space-y-5" :class="{ 'hidden lg:block': step !== 3 }">
                    <div>
                        <p class="label">{{ __('Envies') }}</p>
                        @php
                            $selected = old('interests', $params['interests'] ?? ($user?->interests ?: ['musee', 'monument']));
                            $pictos = ['musee' => 'museum', 'monument' => 'monument', 'parc-jardin' => 'park', 'lieu-culturel' => 'culture', 'evenement-culturel' => 'event', 'street-art' => 'street-art', 'restauration' => 'food', 'itineraire' => 'trail', 'librairies-bibliotheques' => 'book', 'ateliers-artisans' => 'craft'];
                            $colors = ['musee' => '#7C3AED', 'monument' => '#B45309', 'parc-jardin' => '#15803D', 'lieu-culturel' => '#0369A1', 'evenement-culturel' => '#F59E0B', 'street-art' => '#E11D48', 'restauration' => '#DB2777', 'itineraire' => '#0F766E', 'librairies-bibliotheques' => '#1D4ED8', 'ateliers-artisans' => '#9A3412'];
                        @endphp
                        <div class="grid grid-cols-2 gap-2">
                            @foreach($categories as $category)
                                <label class="tile-row cursor-pointer">
                                    <input type="checkbox" name="interests[]" value="{{ $category->slug }}" class="peer sr-only" @checked(in_array($category->slug, $selected))>
                                    <span class="tile-row-inner peer-checked:border-ink peer-checked:bg-ink peer-checked:text-white">
                                        <x-picto :name="$pictos[$category->slug] ?? 'pin'" :size="28" :accent="$colors[$category->slug] ?? '#FF5A3C'" />
                                        <span class="text-xs font-semibold leading-tight">{{ __($category->name) }}</span>
                                        <span class="material-symbols-outlined ml-auto text-[18px] opacity-0 peer-checked:opacity-100 check" style="font-size:18px">check_circle</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @if(!empty($profile['top']))
                            <p class="mt-2 text-[11px] text-ink-muted"><span class="material-symbols-outlined align-middle text-coral" style="font-size:14px">auto_awesome</span> {{ __('Ton profil aime :') }} {{ collect($profile['top'])->pluck('name')->map(fn ($n) => __($n))->implode(', ') }}. {{ __('Le générateur en tient compte.') }}</p>
                        @endif
                    </div>

                    <div class="space-y-1.5 text-sm">
                        <label class="option-row"><x-picto name="lunch" :size="26" /><span class="flex-1">{{ __('Pause déjeuner dans le parcours') }}</span><input type="hidden" name="with_lunch" value="0"><input type="checkbox" name="with_lunch" value="1" class="switch" @checked(old('with_lunch', $params['with_lunch'] ?? false))></label>
                        <label class="option-row"><x-picto name="weather" :size="26" /><span class="flex-1">{{ __('Tenir compte de la météo') }}</span><input type="hidden" name="use_weather" value="0"><input type="checkbox" name="use_weather" value="1" class="switch" @checked(old('use_weather', $params['use_weather'] ?? true))></label>
                        <label class="option-row"><x-picto name="accessible" :size="26" /><span class="flex-1">{{ __('Accessible fauteuil ou poussette') }}</span><input type="hidden" name="accessible" value="0"><input type="checkbox" name="accessible" value="1" class="switch" @checked(old('accessible', $params['accessible'] ?? false))></label>
                        <label class="option-row"><x-picto name="free" :size="26" /><span class="flex-1">{{ __('Lieux gratuits uniquement') }}</span><input type="checkbox" name="free_only" value="1" class="switch" @checked(old('free_only', $params['free_only'] ?? false))></label>
                        <label class="option-row"><x-picto name="radius" :size="26" /><span class="flex-1">{{ __('Rayon de recherche') }}</span>
                            <select name="radius_km" class="field !w-auto !py-1.5 text-xs">
                                <option value="">{{ __('Auto') }}</option>
                                @foreach([2, 4, 6, 10, 15, 25] as $r)<option value="{{ $r }}" @selected((int) old('radius_km', $params['radius_km'] ?? 0) === $r)>{{ $r }} km</option>@endforeach
                            </select>
                        </label>
                    </div>

                    <div class="space-y-2">
                        <button type="submit" class="btn btn-lg btn-primary w-full" :disabled="submitting"><span class="material-symbols-outlined" :class="submitting && 'animate-spin'" x-text="submitting ? 'progress_activity' : 'auto_awesome'"></span>{{ __('Générer mon parcours') }}</button>
                        <button type="submit" name="surprise" value="1" class="btn btn-md btn-soft w-full" :disabled="submitting"><x-picto name="surprise" :size="22" class="-ml-1" />{{ __('Surprends-moi') }}</button>
                    </div>
                </div>

                {{-- Résumé vivant + navigation de l'assistant --}}
                <div class="lg:hidden pt-1 space-y-2.5">
                    <p class="flex items-start gap-2 rounded-2xl bg-paper px-3 py-2 text-[12px] leading-snug text-ink-soft"><x-picto name="summary" :size="22" class="mt-0.5 text-ink-muted" /><span x-text="summary"></span></p>
                    <div class="flex items-center gap-2">
                        <button type="button" x-show="step > 1" @click="step--" class="btn btn-md btn-soft"><span class="material-symbols-outlined" style="font-size:18px">arrow_back</span>{{ __('Retour') }}</button>
                        <span class="flex-1"></span>
                        <button type="button" x-show="step < 3" @click="step++; $el.closest('form').scrollTo({ top: 0 })" class="btn btn-md btn-ink">{{ __('Suivant') }}<span class="material-symbols-outlined" style="font-size:18px">arrow_forward</span></button>
                    </div>
                </div>
                <p class="hidden lg:flex items-start gap-2 rounded-2xl bg-paper px-3 py-2 text-[12px] leading-snug text-ink-soft"><x-picto name="summary" :size="22" class="mt-0.5 text-ink-muted" /><span x-text="summary"></span></p>
            </form>

            {{-- ============================================================ Résultat --}}
            <div class="min-w-0">
                @if($result && $hasResult)
                    @php
                        $v3 = ($result['version'] ?? 2) >= 3;
                        $startsAt = \Illuminate\Support\Carbon::parse($result['starts_at']);
                        $endsAt = \Illuminate\Support\Carbon::parse($result['ends_at']);
                        $visitCount = count(array_filter($steps, fn ($s) => ($s['kind'] ?? 'visit') === 'visit'));
                        $km = (float) ($result['total_distance_km'] ?? 0);
                        $vibe = match (true) {
                            ($result['mode'] ?? 'walk') === 'bike' => ['pedal_bike', __('Sortie à vélo')],
                            ($result['mode'] ?? 'walk') === 'transit' => ['directions_subway', __('Journée métro-musées')],
                            $km <= 2.2 => ['self_improvement', __('Flânerie tranquille')],
                            $km <= 4.5 => ['directions_walk', __('Balade équilibrée')],
                            default => ['hiking', __('Journée sportive')],
                        };
                        $coords = collect($steps)->map(fn ($s) => $s['lat'] . ',' . $s['lng'])->all();
                        $origin = $result['start']['lat'] . ',' . $result['start']['lng'];
                        $destination = !empty($result['end']) ? $result['end']['lat'] . ',' . $result['end']['lng'] : end($coords);
                        $waypoints = !empty($result['end']) ? $coords : array_slice($coords, 0, -1);
                        $gmUrl = 'https://www.google.com/maps/dir/?api=1&origin=' . $origin . '&destination=' . $destination . ($waypoints ? '&waypoints=' . implode('|', $waypoints) : '') . '&travelmode=' . ($result['mode'] === 'bike' ? 'bicycling' : ($result['mode'] === 'transit' ? 'transit' : 'walking'));
                        $modeLabel = match ($result['mode']) { 'bike' => __('à vélo'), 'transit' => __('à pied et en transports'), default => __('à pied') };
                    @endphp

                    <button type="button" @click="showForm = true; step = 1" class="lg:hidden btn btn-md btn-soft w-full mb-3"><span class="material-symbols-outlined" style="font-size:18px">tune</span>{{ __('Modifier les critères') }}</button>

                    {{-- Ta journée : résumé --}}
                    <div class="rounded-4xl bg-ink text-white relative overflow-hidden result-pop">
                        <div class="absolute -right-20 -top-24 h-72 w-72 rounded-full bg-coral/40 blur-3xl"></div>
                        <div class="absolute -left-16 -bottom-24 h-64 w-64 rounded-full bg-teal/30 blur-3xl"></div>
                        <div class="absolute inset-0 opacity-[0.12]" style="background-image: radial-gradient(circle at 1px 1px, #fff 1px, transparent 0); background-size: 24px 24px;"></div>
                        <div class="relative p-5 sm:p-7">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="eyebrow">{{ ucfirst($startsAt->translatedFormat('l j F')) }} · {{ __('départ') }} {{ $startsAt->format('H\hi') }}</p>
                                    <h2 class="display text-3xl sm:text-4xl mt-1">{{ $result['title'] }}</h2>
                                    <p class="mt-2 text-sm text-white/75 flex flex-wrap items-center gap-x-1.5">
                                        <span class="material-symbols-outlined text-coral" style="font-size:16px">trip_origin</span>{{ $result['start']['label'] }}
                                        @if(!empty($result['end']))<span class="material-symbols-outlined text-white/50" style="font-size:16px">arrow_forward</span>{{ $result['end']['label'] }}@endif
                                    </p>
                                </div>
                                @if(!empty($result['weather']))
                                    <button type="button" @click="$dispatch('open-weather')" class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs hover:bg-white/20 transition">
                                        <span class="material-symbols-outlined filled text-sun" style="font-size:18px">{{ $result['weather']['icon'] }}</span>
                                        {{ __($result['weather']['label']) }}{{ $result['weather']['temp'] !== null ? ' · ' . round($result['weather']['temp']) . '°' : '' }}
                                    </button>
                                @endif
                            </div>

                            <div class="mt-5 flex flex-wrap gap-2">
                                @foreach([
                                    ['schedule', floor($result['total_minutes'] / 60) . ' h ' . str_pad($result['total_minutes'] % 60, 2, '0', STR_PAD_LEFT), __('fin vers') . ' ' . $endsAt->format('H\hi')],
                                    [$mobIcon, number_format($km, 1, ',', ' ') . ' km', $modeLabel],
                                    ['museum', $visitCount . ' ' . __('lieux'), $v3 && ($result['wait_minutes'] ?? 0) > 0 ? $result['wait_minutes'] . ' ' . __('min d\'attente') : __('sans attente')],
                                    ['payments', number_format($result['total_cost_eur'], 0, ',', ' ') . ' €', __('estimés')],
                                    [$vibe[0], $vibe[1], $v3 ? ($result['travel_share'] ?? 0) . ' % ' . __('en trajet') : ''],
                                ] as [$icon, $value, $label])
                                    <div class="flex items-center gap-2 rounded-2xl bg-white/10 px-3 py-2 min-w-0">
                                        <span class="material-symbols-outlined text-sun" style="font-size:20px">{{ $icon }}</span>
                                        <div class="leading-tight min-w-0"><p class="font-semibold text-sm whitespace-nowrap">{{ $value }}</p><p class="text-[10px] text-white/60 whitespace-nowrap">{{ $label }}</p></div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="mt-5 flex flex-wrap gap-2">
                                <a href="{{ route('itineraries.navigate') }}" class="btn btn-lg btn-primary"><span class="material-symbols-outlined">navigation</span>{{ __('Suivre le parcours') }}</a>
                                <a href="{{ $gmUrl }}" target="_blank" rel="noopener" class="btn btn-lg bg-white/10 text-white border border-white/15 hover:bg-white/20"><span class="material-symbols-outlined" style="font-size:20px">open_in_new</span>Google Maps</a>
                                <button type="button" @click="navigator.clipboard.writeText(@js($gmUrl)); $dispatch('toast', @js(__('Lien copié')))" class="btn btn-lg bg-white/10 text-white border border-white/15 hover:bg-white/20"><span class="material-symbols-outlined" style="font-size:20px">share</span>{{ __('Partager') }}</button>
                                @auth
                                    @if(!empty($result['itinerary_id']))
                                        <a href="{{ route('itineraries.show', $result['itinerary_id']) }}" class="btn btn-lg bg-white/10 text-white border border-white/15 hover:bg-white/20"><span class="material-symbols-outlined" style="font-size:20px">bookmark</span>{{ __('Enregistré') }}</a>
                                    @endif
                                @else
                                    <a href="{{ route('register') }}" class="btn btn-lg bg-white/10 text-white border border-white/15 hover:bg-white/20"><span class="material-symbols-outlined" style="font-size:20px">bookmark</span>{{ __('Créer un compte pour le garder') }}</a>
                                @endauth
                            </div>
                        </div>
                    </div>

                    {{-- Trois propositions --}}
                    @if(!empty($result['variants']) && count($result['variants']) > 1)
                        <div class="mt-3 grid grid-cols-3 gap-2">
                            @foreach($result['variants'] as $v)
                                <form method="POST" action="{{ route('itineraries.variant', $v['key']) }}">@csrf
                                    <button type="submit" class="w-full text-left rounded-2xl border-2 p-3 transition {{ $v['active'] ? 'border-ink bg-white shadow-card' : 'border-transparent bg-white/70 hover:bg-white' }}" @disabled($v['active'])>
                                        <span class="material-symbols-outlined {{ $v['active'] ? 'text-coral' : 'text-ink-muted' }}">{{ $v['icon'] }}</span>
                                        <p class="font-semibold text-sm leading-tight mt-1">{{ __($v['label']) }}</p>
                                        <p class="text-[11px] text-ink-muted mt-0.5">{{ $v['steps'] }} {{ __('lieux') }} · {{ floor($v['minutes'] / 60) }} h{{ $v['minutes'] % 60 ? str_pad($v['minutes'] % 60, 2, '0', STR_PAD_LEFT) : '' }} · {{ number_format($v['km'], 1, ',', ' ') }} km</p>
                                        <p class="text-[10px] text-ink-muted mt-1 line-clamp-2 hidden sm:block">{{ implode(' · ', $v['titles']) }}</p>
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    @endif

                    {{-- Carte --}}
                    <div class="card overflow-hidden mt-3 relative">
                        <div id="itinerary-map" class="h-[300px] sm:h-[440px]"></div>
                        <div class="absolute top-3 left-3 z-[500] pointer-events-none flex gap-1.5">
                            <span class="badge bg-white/95 shadow-card !text-[10px]"><span class="material-symbols-outlined" style="font-size:12px">{{ $mobIcon }}</span>{{ ($result['routing_source'] ?? '') === 'valhalla' ? __('Trajets réels') : __('Trajets estimés') }}</span>
                            @if(!empty($result['transit_used']))<span class="badge bg-white/95 shadow-card !text-[10px]"><span class="material-symbols-outlined" style="font-size:12px">directions_subway</span>{{ __('Transports') }}</span>@endif
                        </div>
                    </div>

                    @if(!empty($result['warnings']))
                        <div class="mt-3 space-y-1">
                            @foreach($result['warnings'] as $w)
                                <p class="text-xs text-amber-800 bg-sun-soft rounded-xl px-3 py-2 flex items-start gap-2"><span class="material-symbols-outlined shrink-0" style="font-size:16px">info</span>{{ __($w) }}</p>
                            @endforeach
                        </div>
                    @endif

                    {{-- Chapitres du parcours --}}
                    <div class="mt-5 flex items-end justify-between gap-3">
                        <div><p class="eyebrow">{{ __('Étape par étape') }}</p><h3 class="display text-2xl">{{ __('Le programme') }}</h3></div>
                        <p class="text-xs text-ink-muted hidden sm:block">{{ __('Tu peux réordonner, remplacer ou retirer chaque étape.') }}</p>
                    </div>
                    <ol class="mt-3 relative">
                        <div class="absolute left-[19px] top-6 bottom-6 w-0.5 bg-ink/10"></div>

                        <li class="relative pl-14 pb-4">
                            <span class="absolute left-0 top-0 h-10 w-10 rounded-full bg-coral text-white flex items-center justify-center shadow-card"><span class="material-symbols-outlined" style="font-size:18px">flag</span></span>
                            <div class="pt-2">
                                <p class="font-semibold">{{ $result['start']['label'] }}</p>
                                <p class="text-xs text-ink-muted">{{ __('Départ à') }} {{ $startsAt->format('H\hi') }}</p>
                            </div>
                        </li>

                        @foreach($steps as $step)
                            @php
                                $lunch = ($step['kind'] ?? 'visit') === 'lunch';
                                $h = $step['hours'] ?? null;
                                $i = $loop->index;
                                $last = $loop->last;
                                $chapter = $lunch ? __('Pause bien méritée') : ($loop->first ? __('On commence fort') : ($last && empty($result['end']) ? __('Dernière étape') : __('Étape') . ' ' . $step['order']));
                                $travelIcon = ($step['travel_mode'] ?? '') === 'transit' ? 'directions_subway' : ((($step['travel_mode'] ?? '') === 'bike') ? 'directions_bike' : 'directions_walk');
                            @endphp
                            {{-- Trajet --}}
                            <li class="relative pl-14 pb-3">
                                <span class="absolute left-[11px] top-0 h-[18px] w-[18px] rounded-full bg-paper border-2 border-ink/10 flex items-center justify-center"><span class="material-symbols-outlined text-ink-muted" style="font-size:11px">{{ $travelIcon }}</span></span>
                                @if(!empty($step['transit']) && !empty($step['transit']['sections']))
                                    <x-transit-sheet :transit="$step['transit']" :minutes="$step['travel_minutes']" class="max-w-xl" />
                                    @if(($step['wait_minutes'] ?? 0) > 0)<p class="mt-1 text-[11px] text-amber-700 inline-flex items-center gap-0.5"><span class="material-symbols-outlined" style="font-size:12px">hourglass_top</span>{{ $step['wait_minutes'] }} {{ __('min d\'attente') }}</p>@endif
                                @else
                                <div class="inline-flex flex-wrap items-center gap-x-2 gap-y-1 rounded-full bg-white border border-ink/5 px-3 py-1 text-[11px] text-ink-muted">
                                    <span class="font-semibold text-ink">{{ $step['travel_minutes'] }} min</span><span>{{ number_format($step['travel_km'], 1, ',', ' ') }} km</span>
                                    @if(($step['wait_minutes'] ?? 0) > 0)<span class="text-amber-700 inline-flex items-center gap-0.5"><span class="material-symbols-outlined" style="font-size:12px">hourglass_top</span>{{ $step['wait_minutes'] }} {{ __('min d\'attente') }}</span>@endif
                                </div>
                                @endif
                            </li>

                            {{-- Étape --}}
                            <li class="relative pl-14 pb-5" x-data="{ tools: false }">
                                <span class="absolute left-0 top-3 h-10 w-10 rounded-full flex items-center justify-center text-sm font-bold shadow-card {{ $lunch ? 'bg-sun text-ink' : 'bg-ink text-white' }}">@if($lunch)<span class="material-symbols-outlined" style="font-size:18px">restaurant</span>@else{{ $step['order'] }}@endif</span>
                                <div class="card overflow-hidden {{ $lunch ? 'border-sun/60' : '' }} {{ !empty($step['locked']) ? 'ring-2 ring-ink/80' : '' }}">
                                    <div class="flex">
                                        <a href="{{ route('places.show', $step['place_id']) }}" class="relative w-28 sm:w-36 shrink-0 placeholder-cover">
                                            @if($step['cover'])<img src="{{ $step['cover'] }}" alt="" loading="lazy" class="absolute inset-0 w-full h-full object-cover">@else<span class="absolute inset-0 flex items-center justify-center"><span class="material-symbols-outlined text-white/80" style="font-size:28px">{{ $lunch ? 'restaurant' : 'place' }}</span></span>@endif
                                            <span class="absolute bottom-1.5 left-1.5 rounded-full bg-ink/70 text-white text-[10px] font-semibold px-2 py-0.5 backdrop-blur">{{ $step['arrive_at'] }}</span>
                                        </a>
                                        <div class="min-w-0 flex-1 p-3">
                                            <p class="text-[10px] font-bold uppercase tracking-wider {{ $lunch ? 'text-amber-700' : 'text-coral' }}">{{ $chapter }} · {{ $lunch ? __('Pause déjeuner') : $step['category'] }}</p>
                                            <a href="{{ route('places.show', $step['place_id']) }}" class="block font-semibold leading-snug line-clamp-2 mt-0.5 hover:text-coral">{{ $step['title'] }}</a>
                                            <p class="text-xs text-ink-muted mt-1">{{ $step['arrive_at'] }} → {{ $step['leave_at'] }} · {{ $step['visit_minutes'] }} {{ __('min sur place') }}{{ $step['is_free'] ? ' · ' . __('gratuit') : ($step['cost_eur'] ? ' · ≈ ' . number_format($step['cost_eur'], 0) . ' €' : '') }}</p>
                                            <div class="mt-1.5 flex flex-wrap gap-1">
                                                @if($h && $h['status'] === 'open')<span class="badge badge-free !text-[10px]"><span class="material-symbols-outlined" style="font-size:12px">schedule</span>{{ $h['opens'] }}–{{ $h['closes'] }}</span>@elseif($h)<span class="badge bg-paper-deep text-ink-muted !text-[10px]"><span class="material-symbols-outlined" style="font-size:12px">help</span>{{ __('Horaires à vérifier') }}</span>@endif
                                                @if(array_key_exists('accessible', $step) && $step['accessible'] === true)<span class="badge badge-free !text-[10px]" title="{{ $step['accessibility_note'] ?? '' }}"><span class="material-symbols-outlined" style="font-size:12px">accessible</span>PMR</span>@elseif(!empty($result['accessible']) && ($step['accessible'] ?? null) === null)<span class="badge bg-paper-deep text-ink-muted !text-[10px]"><span class="material-symbols-outlined" style="font-size:12px">accessible</span>{{ __('Accès à vérifier') }}</span>@endif
                                                <span class="badge bg-teal-soft text-teal-dark !text-[10px]"><span class="material-symbols-outlined" style="font-size:12px">auto_awesome</span>{{ __($step['reason']) }}</span>
                                                @if(!empty($step['locked']))<span class="badge bg-ink text-white !text-[10px]"><span class="material-symbols-outlined filled" style="font-size:12px">lock</span>{{ __('Gardé') }}</span>@endif
                                            </div>
                                        </div>
                                        <button type="button" @click="tools = !tools" class="self-start m-2 h-8 w-8 rounded-full hover:bg-paper flex items-center justify-center text-ink-muted" :class="tools && 'bg-paper text-ink'" title="{{ __('Modifier cette étape') }}"><span class="material-symbols-outlined" style="font-size:20px">more_horiz</span></button>
                                    </div>

                                    @if(!empty($step['conflict']))
                                        <p class="mx-3 mb-2 text-[11px] text-coral-dark bg-coral-soft rounded-xl px-3 py-1.5 flex items-center gap-1.5"><span class="material-symbols-outlined" style="font-size:14px">warning</span>{{ __('La visite finirait après la fermeture') }} ({{ $step['hours']['closes'] ?? '' }}).</p>
                                    @endif
                                    @if(!empty($step['alternative']))
                                        <a href="{{ route('places.show', $step['alternative']['place_id']) }}" class="mx-3 mb-2 flex items-center gap-2 rounded-xl bg-sky-50 px-3 py-1.5 text-[11px] text-sky-800 hover:bg-sky-100">
                                            <span class="material-symbols-outlined" style="font-size:14px">umbrella</span>
                                            <span class="min-w-0 truncate">{{ __('Plan B s\'il pleut') }} : <span class="font-semibold">{{ $step['alternative']['title'] }}</span>{{ $step['alternative']['minutes_away'] !== null ? ' · ' . $step['alternative']['minutes_away'] . ' min' : '' }}</span>
                                        </a>
                                    @endif

                                    {{-- Outils --}}
                                    <div x-show="tools" x-cloak x-transition class="border-t border-ink/5 bg-paper/60 px-2 py-1.5 flex flex-wrap items-center gap-1">
                                        <form method="POST" action="{{ route('itineraries.step-move', $i) }}">@csrf<input type="hidden" name="direction" value="up"><button class="btn btn-icon btn-ghost !h-8 !w-8" title="{{ __('Monter') }}" @disabled($loop->first)><span class="material-symbols-outlined" style="font-size:18px">arrow_upward</span></button></form>
                                        <form method="POST" action="{{ route('itineraries.step-move', $i) }}">@csrf<input type="hidden" name="direction" value="down"><button class="btn btn-icon btn-ghost !h-8 !w-8" title="{{ __('Descendre') }}" @disabled($last)><span class="material-symbols-outlined" style="font-size:18px">arrow_downward</span></button></form>
                                        <span class="mx-1 h-5 w-px bg-ink/10"></span>
                                        <form method="POST" action="{{ route('itineraries.step-duration', $i) }}">@csrf<input type="hidden" name="delta" value="-15"><button class="btn btn-icon btn-ghost !h-8 !w-8" title="{{ __('15 min de moins') }}" @disabled($step['visit_minutes'] <= 15)><span class="material-symbols-outlined" style="font-size:18px">remove</span></button></form>
                                        <span class="text-[11px] font-semibold tabular-nums">{{ $step['visit_minutes'] }} min</span>
                                        <form method="POST" action="{{ route('itineraries.step-duration', $i) }}">@csrf<input type="hidden" name="delta" value="15"><button class="btn btn-icon btn-ghost !h-8 !w-8" title="{{ __('15 min de plus') }}"><span class="material-symbols-outlined" style="font-size:18px">add</span></button></form>
                                        <span class="flex-1"></span>
                                        @if(!$lunch)
                                            <form method="POST" action="{{ route('itineraries.step-lock', $i) }}">@csrf<button class="btn btn-sm !h-8 {{ !empty($step['locked']) ? 'btn-ink' : 'btn-ghost' }}" title="{{ !empty($step['locked']) ? __('Déverrouiller') : __('Garder ce lieu au recalcul') }}"><span class="material-symbols-outlined {{ !empty($step['locked']) ? 'filled' : '' }}" style="font-size:16px">{{ !empty($step['locked']) ? 'lock' : 'lock_open' }}</span><span class="hidden sm:inline">{{ __('Garder') }}</span></button></form>
                                        @endif
                                        <form method="POST" action="{{ route('itineraries.step-replace', $i) }}">@csrf<button class="btn btn-sm btn-ghost !h-8" title="{{ __('Remplacer par un lieu similaire') }}"><span class="material-symbols-outlined" style="font-size:16px">swap_horiz</span><span class="hidden sm:inline">{{ __('Remplacer') }}</span></button></form>
                                        <form method="POST" action="{{ route('itineraries.step-remove', $i) }}">@csrf<button class="btn btn-sm btn-ghost !h-8 hover:text-coral" title="{{ __('Retirer') }}" @disabled(count($steps) <= 1)><span class="material-symbols-outlined" style="font-size:16px">close</span><span class="hidden sm:inline">{{ __('Retirer') }}</span></button></form>
                                    </div>
                                </div>
                            </li>
                        @endforeach

                        {{-- Arrivée --}}
                        @if(!empty($result['end']))
                            @php $endTravelIcon = ($result['end']['travel_mode'] ?? '') === 'transit' ? 'directions_subway' : $mobIcon; @endphp
                            <li class="relative pl-14 pb-3">
                                <span class="absolute left-[11px] top-0 h-[18px] w-[18px] rounded-full bg-paper border-2 border-ink/10 flex items-center justify-center"><span class="material-symbols-outlined text-ink-muted" style="font-size:11px">{{ $endTravelIcon }}</span></span>
                                @if(!empty($result['end']['transit']) && !empty($result['end']['transit']['sections']))
                                    <x-transit-sheet :transit="$result['end']['transit']" :minutes="$result['end']['travel_minutes'] ?? null" class="max-w-xl" />
                                @else
                                <div class="inline-flex flex-wrap items-center gap-x-2 rounded-full bg-white border border-ink/5 px-3 py-1 text-[11px] text-ink-muted">
                                    <span class="font-semibold text-ink">{{ $result['end']['travel_minutes'] ?? 0 }} min</span><span>{{ number_format($result['end']['travel_km'] ?? 0, 1, ',', ' ') }} km</span>
                                </div>
                                @endif
                            </li>
                        @endif
                        <li class="relative pl-14">
                            <span class="absolute left-0 top-0 h-10 w-10 rounded-full bg-teal text-white flex items-center justify-center shadow-card"><span class="material-symbols-outlined" style="font-size:18px">sports_score</span></span>
                            <div class="pt-2">
                                <p class="font-semibold">{{ $result['end']['label'] ?? __('Fin du parcours') }}</p>
                                <p class="text-xs text-ink-muted">{{ __('vers') }} {{ $endsAt->format('H\hi') }} · {{ __('Belle journée !') }}</p>
                            </div>
                        </li>
                    </ol>

                    {{-- Barre d'action mobile --}}
                    <div class="lg:hidden fixed inset-x-3 bottom-[4.6rem] z-[900] flex gap-2 pointer-events-none">
                        <a href="{{ route('itineraries.navigate') }}" class="pointer-events-auto btn btn-lg btn-primary flex-1 shadow-float"><span class="material-symbols-outlined">navigation</span>{{ __('Suivre le parcours') }}</a>
                    </div>
                    <div class="lg:hidden h-16"></div>
                @elseif($result)
                    <button type="button" @click="showForm = true; step = 1" class="lg:hidden btn btn-md btn-soft w-full mb-3"><span class="material-symbols-outlined" style="font-size:18px">tune</span>{{ __('Modifier les critères') }}</button>
                    <div class="card p-8 text-center">
                        <span class="material-symbols-outlined text-4xl text-ink-muted">explore_off</span>
                        <p class="mt-3 font-semibold">{{ __('Aucun parcours possible avec ces paramètres.') }}</p>
                        <ul class="mt-2 text-sm text-ink-muted space-y-1">@foreach($result['warnings'] ?? [] as $w)<li>{{ __($w) }}</li>@endforeach</ul>
                        <p class="mt-3 text-sm text-ink-muted">{{ __('Élargis le rayon, augmente le temps ou le budget, change de jour ou de point de départ.') }}</p>
                    </div>
                @else
                    <div class="card p-8 sm:p-12 text-center">
                        <div class="mx-auto h-16 w-16 rounded-3xl bg-coral-soft text-coral flex items-center justify-center"><span class="material-symbols-outlined" style="font-size:32px">auto_awesome</span></div>
                        <h2 class="display text-2xl mt-4">{{ __('Prêt quand tu l\'es.') }}</h2>
                        <p class="mt-2 text-sm text-ink-muted max-w-md mx-auto">{{ __('Indique d\'où tu pars, quand, combien de temps tu as. CAMINO choisit des lieux ouverts à ce moment-là, optimise l\'ordre, calcule les vrais trajets et te donne l\'heure d\'arrivée à chaque étape.') }}</p>
                        <div class="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-3 text-left text-sm">
                            @foreach([['schedule', __('Horaires vérifiés'), __('Les lieux fermés ce jour-là sont écartés, l\'attente est calculée.')], ['route', __('Trajets réels'), __('Rues et durées OpenStreetMap, à pied, à vélo ou en transports, ordre optimisé.')], ['umbrella', __('Météo et plan B'), __('S\'il pleut, on privilégie le couvert et chaque étape dehors a une alternative.')]] as [$i, $t, $d])
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
                        <button type="button" @click="closeMap()" class="btn btn-icon btn-ghost" aria-label="{{ __('Fermer') }}"><span class="material-symbols-outlined">close</span></button>
                    </div>
                    <div class="relative h-[55vh] sm:h-[420px]">
                        <div x-ref="pickerMap" class="absolute inset-0"></div>
                        <div class="pointer-events-none absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-full z-[500]"><span class="material-symbols-outlined filled text-coral drop-shadow" style="font-size:44px">location_on</span></div>
                    </div>
                    <div class="p-4 flex items-center justify-between gap-3">
                        <p class="text-xs text-ink-muted min-w-0 truncate" x-text="picker.label || 'Déplace la carte, le repère reste au centre.'"></p>
                        <button type="button" @click="confirmMap()" class="btn btn-md btn-primary shrink-0"><span class="material-symbols-outlined" style="font-size:18px">check</span>{{ __('Valider') }}</button>
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
                // Nombre de lieux estimé : ~55 min de visite + ~20 min de trajet par étape.
                get estStops() { return Math.max(1, Math.round(this.duration / 75)); },
                preset(time, minutes) { this.time = time; this.duration = minutes; },
                dateLabel(d) { try { return new Date(d + 'T12:00:00').toLocaleDateString(@js(app()->getLocale() === 'zh' ? 'zh-CN' : app()->getLocale()), { weekday: 'short', day: 'numeric', month: 'short' }); } catch (e) { return d; } },
                // Phrase récapitulative qui se construit au fil des choix.
                get summary() {
                    const T = @js(['from' => __('Départ'), 'paris' => __('centre de Paris'), 'me' => __('ma position'), 'today' => __('aujourd\'hui'), 'tomorrow' => __('demain'), 'at' => __('à'), 'now' => __('dès maintenant'), 'loop' => __('retour au départ'), 'open' => __('arrivée libre'), 'to' => __('jusqu\'à'), 'walk' => __('à pied'), 'bike' => __('à vélo'), 'transit' => __('en transports'), 'choose' => __('choisis un point de départ')]);
                    const from = this.startMode === 'paris' ? T.paris : (this.start.label ? this.start.label : (this.startMode === 'me' ? T.me : T.choose));
                    const when = (this.date === this.today ? T.today : (this.date === this.tomorrow ? T.tomorrow : this.dateLabel(this.date))) + ' ' + (this.time ? T.at + ' ' + this.time.replace(':', 'h') : (this.date === this.today ? T.now : T.at + ' 10h'));
                    const end = this.endMode === 'loop' ? T.loop : (this.endMode === 'point' ? (this.end.label ? T.to + ' ' + this.end.label : '') : T.open);
                    return T.from + ' ' + from + ' · ' + when + ' · ' + this.label(this.duration) + ' ' + (T[this.mode] || T.walk) + (end ? ' · ' + end : '') + '.';
                },
                useMe() {
                    this.startMode = 'me'; this.locating = true;
                    window.Camino.locate().then(async (p) => {
                        this.start = { lat: +p.lat.toFixed(6), lng: +p.lng.toFixed(6), label: 'Ma position' };
                        this.locating = false;
                        try { const r = await fetch(`/api/v1/geocode/reverse?lat=${p.lat}&lng=${p.lng}`); const j = await r.json(); if (j.label) this.start.label = j.label; } catch (e) {}
                    }).catch(() => { this.locating = false; this.startMode = 'address'; this.$dispatch('toast', @js(__('Position indisponible : saisis une adresse.'))); });
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
                    this[target] = { lat: +c.lat.toFixed(6), lng: +c.lng.toFixed(6), label: @js(__('Point sur la carte')) };
                    if (target === 'start') this.startMode = 'map'; else this.endMode = 'point';
                    this.picker.open = false;
                    try { const r = await fetch(`/api/v1/geocode/reverse?lat=${c.lat}&lng=${c.lng}`); const j = await r.json(); if (j.label) this[target].label = j.label; } catch (e) {}
                },
                beforeSubmit(e) {
                    if (this.startMode !== 'paris' && !this.start.lat) { e.preventDefault(); this.$dispatch('toast', @js(__('Choisis un point de départ (adresse, position ou carte).'))); return; }
                    if (this.endMode === 'point' && !this.end.lat) { e.preventDefault(); this.$dispatch('toast', @js(__('Indique une adresse d\'arrivée.'))); return; }
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
            const full = L.polyline(pts, { color: '#12161C', weight: 7, opacity: 0.12 }).addTo(map);
            map.fitBounds(full.getBounds(), { padding: [30, 30] });
            L.marker([result.start.lat, result.start.lng], { icon: C.stepIcon(0, true) }).addTo(map).bindPopup(`<div class="p-3 text-sm font-semibold">${C.escapeHtml(result.start.label || 'Départ')}</div>`);

            // Le tracé se dessine tronçon par tronçon, et chaque étape « tombe » sur la carte à son tour.
            const legs = (result.legs && result.legs.length) ? result.legs.filter(l => l.shape && l.shape.length > 1) : [{ transit: false, shape: pts }];
            const walkColor = result.mode === 'bike' ? '#0F8B8D' : '#FF5A3C';
            const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            let legIndex = 0;
            const dropStep = (i) => { const s = result.steps[i]; if (!s) return; const m = L.marker([s.lat, s.lng], { icon: s.kind === 'lunch' ? C.placeIcon('restauration', { size: 30 }) : C.stepIcon(s.order) }).addTo(map).bindPopup(`<div class="p-3"><p class="text-sm font-semibold">${s.kind === 'lunch' ? '🍽️ ' : s.order + '. '}${C.escapeHtml(s.title)}</p><p class="text-xs text-ink-muted">Arrivée ${s.arrive}</p></div>`); m.getElement()?.querySelector('.camino-pin')?.classList.add('pin-pop'); };
            const drawLeg = () => {
                const leg = legs[legIndex];
                if (!leg) { if (result.end) L.marker([result.end.lat, result.end.lng], { icon: C.stepIcon('<span class="material-symbols-outlined" style="font-size:16px">sports_score</span>') }).addTo(map); return; }
                const color = leg.transit ? (leg.color || '#1D4ED8') : walkColor;
                const line = L.polyline([], { color, weight: leg.transit ? 5 : 4, opacity: 0.95, lineJoin: 'round', dashArray: leg.transit ? '8 8' : null }).addTo(map);
                if (reduce) { line.setLatLngs(leg.shape); dropStep(legIndex); legIndex++; drawLeg(); return; }
                let k = 0; const step = Math.max(1, Math.ceil(leg.shape.length / 40));
                const tick = () => { k = Math.min(leg.shape.length, k + step); line.setLatLngs(leg.shape.slice(0, k)); if (k < leg.shape.length) requestAnimationFrame(tick); else { dropStep(legIndex); legIndex++; setTimeout(drawLeg, 120); } };
                requestAnimationFrame(tick);
            };
            setTimeout(drawLeg, 250);
        });
        @endif
    </script>
    @endpush
</x-app-layout>
