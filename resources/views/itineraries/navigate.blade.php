@php
    $steps = $result['steps'] ?? [];
    $nav = [
        'mode' => $result['mode'] ?? 'walk',
        'start' => $result['start'],
        'end' => $result['end'] ?? null,
        'legs' => $result['legs'] ?? [],
        'geometry' => $result['geometry'] ?? [],
        'steps' => collect($steps)->map(fn ($s) => [
            'lat' => $s['lat'], 'lng' => $s['lng'], 'title' => $s['title'], 'cover' => $s['cover'], 'category' => $s['category'],
            'visit' => $s['visit_minutes'], 'arrive' => $s['arrive_at'], 'kind' => $s['kind'] ?? 'visit', 'slug' => $s['category_slug'],
            'url' => route('places.show', $s['place_id']), 'hours' => $s['hours'] ?? null, 'id' => $s['place_id'], 'visitUrl' => route('places.visit', $s['place_id']),
            'narration' => $narrations[$s['place_id']] ?? null,
        ])->values()->all(),
        'title' => $result['title'],
        'backUrl' => $backUrl,
        'simulate' => max(0, (int) request()->query('simulate', 0)),
        'auth' => auth()->check(),
        'itineraryId' => $result['itinerary_id'] ?? null,
        'journalUrl' => $journalUrl ?? (! empty($result['itinerary_id']) && auth()->check() ? route('itineraries.journal', $result['itinerary_id']) : null),
        'lang' => \App\Http\Middleware\SetLocale::speechLanguage(),
        'transit' => app(\App\Services\TransitService::class)->enabled(),
        't' => [
            'started' => __('Guidage lancé.'), 'follow' => __('Suivez le tracé.'), 'in' => __('Dans'), 'meters' => __('mètres'), 'arrived' => __('Vous êtes arrivé à'), 'visit' => __('Visite prévue :'), 'minutes' => __('minutes'),
            'direction' => __('Direction'), 'lastStep' => __('Dernière étape : retour au point de départ.'), 'rerouted' => __('Itinéraire recalculé.'), 'done' => __('Parcours terminé. Bravo !'), 'voiceOn' => __('Guidage vocal activé.'),
            'headTo' => __('Dirige-toi vers'), 'headToVerbal' => __('Dirigez-vous vers'), 'backToStart' => __('Retourne au point de départ'), 'backToStartVerbal' => __('Retournez au point de départ.'), 'now' => __('Maintenant'), 'onRoute' => __('En route'), 'inShort' => __('Dans'),
            'stepOf' => __('Étape'), 'returnArrival' => __('Retour · arrivée'), 'imminent' => __('arrivée imminente'), 'about' => __('environ'), 'min' => __('min'), 'finish' => __('Terminer'), 'towards' => __('Vers'), 'returnStart' => __('Retour au départ'), 'nextStep' => __('la prochaine étape'),
            'walked' => __('Tu as parcouru :d. Bravo.'), 'bravo' => __('Bravo.'), 'plannedVisit' => __('Visite prévue :'), 'open' => __('ouvert'),
            'gpsUnavailable' => __('Géolocalisation indisponible sur cet appareil.'), 'gpsDenied' => __('Autorise la localisation pour être guidé.'), 'gpsWeak' => __('Signal GPS faible, on réessaie…'),
            'onboard' => __('À bord'), 'getOffAt' => __('Descends à'), 'stops' => __('arrêts'), 'stop' => __('arrêt'), 'plannedArrival' => __('arrivée prévue'), 'gotOff' => __('Je suis descendu'),
            'nextStopVerbal' => __('Prochain arrêt : :stop. Préparez-vous à descendre.'), 'transitLive' => __('Horaires en temps réel'), 'transitRecalc' => __('Trajet en transports recalculé à l\'heure réelle.'),
            'departureAt' => __('Départ'), 'arrival' => __('Arrivée'), 'walk' => __('Marche'), 'wait' => __('Attente'), 'details' => __('Détail du trajet'), 'searching' => __('Recherche du meilleur trajet…'),
            'then' => __('Ensuite'), 'arriveAt' => __('Arrivée à'),
            'listen' => __('Écouter la présentation'), 'stopListen' => __('Arrêter'), 'audioguideIntro' => __('Un mot sur ce lieu.'),
        ],
    ];
@endphp
<x-app-layout :title="__('Guidage') . ' · ' . $result['title']" :fullscreen="true" :bottom-nav="false">
    <div class="absolute inset-0 overflow-hidden" x-data="caminoNav(@js($nav))" @keydown.escape.window="if (!started) quit()">
        <div id="nav-map" class="absolute inset-0 z-0 bg-paper-deep"></div>

        {{-- Bandeau instruction (haut) --}}
        <div class="absolute top-[4.6rem] inset-x-3 z-[600] pointer-events-none">
            {{-- Consigne de rue --}}
            <div :class="{ hidden: !started || onboard }" class="hidden nav-card rounded-3xl bg-ink text-white p-3.5 pointer-events-auto">
                <div class="flex items-center gap-3">
                    <span class="h-16 w-16 rounded-2xl bg-white/10 flex flex-col items-center justify-center shrink-0">
                        <span class="material-symbols-outlined nav-turn-icon" x-text="icon"></span>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-[11px] uppercase tracking-[0.14em] text-white/60 font-bold" x-text="distanceLabel"></p>
                        <p class="font-semibold text-[17px] leading-snug line-clamp-2" x-text="instruction || @js(__('Suis le tracé'))"></p>
                        <p x-show="street" class="text-xs text-white/60 truncate" x-text="street"></p>
                        <template x-if="nextLine"><p class="mt-1 flex items-center gap-1.5 text-xs text-white/80"><span class="rounded-md px-1.5 py-0.5 text-[10px] font-bold leading-none" :style="'background:' + nextLine.color + ';color:' + nextLine.text_color" x-text="lineLabel(nextLine)"></span><span x-show="nextDepart" x-text="data.t.departureAt + ' ' + nextDepart"></span></p></template>
                    </div>
                    <button type="button" @click="toggleMute()" class="h-10 w-10 rounded-full bg-white/10 flex items-center justify-center shrink-0" :aria-label="muted ? @js(__('Activer la voix')) : @js(__('Couper la voix'))"><span class="material-symbols-outlined" style="font-size:20px" x-text="muted ? 'volume_off' : 'volume_up'"></span></button>
                </div>
                {{-- Manœuvre suivante, pour anticiper --}}
                <template x-if="thenManeuver"><p class="mt-2 pt-2 border-t border-white/10 text-xs text-white/70 flex items-center gap-1.5 truncate"><span class="text-white/50" x-text="data.t.then"></span><span class="material-symbols-outlined" style="font-size:16px" x-text="thenManeuver.icon"></span><span class="truncate" x-text="thenManeuver.text"></span><span class="text-white/50 shrink-0" x-text="'· ' + formatDistance(thenManeuver.d)"></span></p></template>
            </div>

            {{-- À bord d'un transport --}}
            <div :class="{ hidden: !started || !onboard }" class="hidden nav-card rounded-3xl text-white p-3.5 pointer-events-auto" :style="'background:' + (section && section.color ? section.color : '#1D4ED8')">
                <div class="flex items-center gap-3">
                    <span class="h-14 w-14 rounded-2xl bg-white/15 flex items-center justify-center shrink-0"><span class="material-symbols-outlined" style="font-size:32px" x-text="section && section.mode === 'Bus' ? 'directions_bus' : (section && section.mode === 'Tram' ? 'tram' : (section && (section.mode === 'RER' || section.mode === 'Train') ? 'train' : 'subway'))"></span></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-[11px] uppercase tracking-[0.14em] text-white/70 font-bold"><span x-text="data.t.onboard"></span> · <span x-text="section ? lineLabel(section) : ''"></span></p>
                        <p class="font-semibold leading-snug"><span x-text="data.t.getOffAt"></span> <span x-text="section ? section.to : ''"></span></p>
                        <p class="text-xs text-white/80" x-text="onboardLabel"></p>
                    </div>
                    <button type="button" @click="toggleMute()" class="h-10 w-10 rounded-full bg-white/15 flex items-center justify-center shrink-0" :aria-label="muted ? @js(__('Activer la voix')) : @js(__('Couper la voix'))"><span class="material-symbols-outlined" style="font-size:20px" x-text="muted ? 'volume_off' : 'volume_up'"></span></button>
                </div>
                <button type="button" @click="alight()" class="mt-2.5 w-full rounded-2xl bg-white/20 hover:bg-white/30 px-3 py-2 text-sm font-semibold inline-flex items-center justify-center gap-1.5"><span class="material-symbols-outlined" style="font-size:18px">exit_to_app</span><span x-text="data.t.gotOff"></span></button>
            </div>

            <div class="mt-2 flex flex-wrap gap-2">
                <div x-show="offRoute && started" x-cloak class="inline-flex items-center gap-2 rounded-full bg-amber-500 text-ink px-3 py-1.5 text-xs font-semibold pointer-events-auto"><span class="material-symbols-outlined" style="font-size:16px" :class="rerouting && 'animate-spin'" x-text="rerouting ? 'progress_activity' : 'alt_route'"></span><span x-text="rerouting ? @js(__('Recalcul de l\'itinéraire…')) : @js(__('Tu t\'éloignes du tracé'))"></span></div>
                <div x-show="transitLoading" x-cloak class="inline-flex items-center gap-2 rounded-full bg-white text-ink px-3 py-1.5 text-xs font-semibold pointer-events-auto shadow-card"><span class="material-symbols-outlined animate-spin" style="font-size:16px">progress_activity</span><span x-text="data.t.searching"></span></div>
                <div x-show="gpsError" x-cloak class="inline-flex items-center gap-2 rounded-full bg-coral text-white px-3 py-1.5 text-xs font-semibold pointer-events-auto"><span class="material-symbols-outlined" style="font-size:16px">location_off</span><span x-text="gpsError"></span></div>
            </div>
        </div>

        {{-- Boutons carte --}}
        <div class="absolute right-3 z-[600] flex flex-col gap-2" :style="'bottom:' + (sheetHeight + 20) + 'px'">
            <button type="button" x-show="started && !follow" x-cloak @click="recenter()" class="h-11 w-11 rounded-full bg-ink text-white shadow-card flex items-center justify-center" aria-label="{{ __('Recentrer') }}"><span class="material-symbols-outlined filled">navigation</span></button>
            <button type="button" @click="toggleOverview()" class="h-11 w-11 rounded-full bg-white shadow-card flex items-center justify-center text-ink" aria-label="{{ __('Voir tout le parcours') }}"><span class="material-symbols-outlined" x-text="overview ? 'my_location' : 'zoom_out_map'"></span></button>
            <button type="button" x-show="Math.abs(bearing) > 2" x-cloak @click="northUp()" class="h-11 w-11 rounded-full bg-white shadow-card flex items-center justify-center text-ink" aria-label="{{ __('Nord en haut') }}"><span class="material-symbols-outlined" :style="'transform: rotate(' + (-bearing) + 'deg)'">explore</span></button>
        </div>

        {{-- Feuille basse --}}
        <div class="absolute inset-x-0 bottom-0 z-[600] px-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]" x-ref="sheet">
            {{-- Avant le départ --}}
            <div :class="{ hidden: started || done }" class="nav-card card p-4 sm:p-5">
                <div class="flex items-start gap-3">
                    <span class="h-11 w-11 rounded-2xl bg-coral-soft text-coral flex items-center justify-center shrink-0"><span class="material-symbols-outlined">navigation</span></span>
                    <div class="min-w-0 flex-1">
                        <p class="eyebrow">{{ __('Guidage') }}</p>
                        <p class="font-display text-xl leading-tight truncate">{{ $result['title'] }}</p>
                        <p class="text-xs text-ink-muted mt-1">{{ trans_choice(':n étape|:n étapes', count($steps), ['n' => count($steps)]) }} · {{ number_format($result['total_distance_km'] ?? 0, 1, ',', ' ') }} km {{ match($result['mode'] ?? 'walk') { 'bike' => __('à vélo'), 'transit' => __('à pied et en transports'), default => __('à pied') } }} · {{ __('instructions vocales') }}</p>
                    </div>
                </div>
                <div class="mt-3 flex gap-2">
                    <button type="button" @click="start()" class="btn btn-lg btn-primary flex-1 min-w-0"><span class="material-symbols-outlined">play_arrow</span><span class="truncate">{{ __('Démarrer le guidage') }}</span></button>
                    <button type="button" @click="toggleMute()" class="btn btn-lg btn-soft !px-4 shrink-0" :aria-label="muted ? @js(__('Activer la voix')) : @js(__('Couper la voix'))"><span class="material-symbols-outlined" x-text="muted ? 'volume_off' : 'volume_up'"></span></button>
                </div>
                <label class="mt-3 option-row !py-2 text-sm"><span class="material-symbols-outlined text-coral" style="font-size:20px">headphones</span><span class="flex-1"><span class="font-semibold">{{ __('Audioguide') }}</span><span class="block text-[11px] text-ink-muted">{{ __('À chaque arrivée, CAMINO te raconte le lieu à voix haute.') }}</span></span><input type="checkbox" class="switch" x-model="audioguide"></label>
                <p class="mt-2 text-[11px] text-ink-muted">{{ __('CAMINO utilise ta position uniquement pendant le guidage, rien n\'est enregistré. Garde l\'écran allumé, on s\'en occupe.') }}</p>
                <a :href="backUrl" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-ink-muted hover:text-ink"><span class="material-symbols-outlined" style="font-size:14px">arrow_back</span>{{ __('Retour') }}</a>
            </div>

            {{-- Pendant le guidage : étape en cours --}}
            <div :class="{ hidden: !started || arrived || done }" class="hidden nav-card card p-3.5 sm:p-4">
                <div class="flex items-center gap-3">
                    <div class="h-14 w-14 rounded-2xl overflow-hidden shrink-0 placeholder-cover flex items-center justify-center">
                        <template x-if="target && target.cover"><img :src="target.cover" alt="" class="h-full w-full object-cover"></template>
                        <template x-if="!target || !target.cover"><span class="material-symbols-outlined text-white/80" x-text="target && target.kind === 'end' ? 'sports_score' : 'place'"></span></template>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-ink-muted" x-text="progressLabel"></p>
                        <p class="font-semibold leading-snug line-clamp-1" x-text="target ? target.title : ''"></p>
                        <p class="text-xs text-ink-muted truncate"><span x-text="formatDistance(remaining)"></span> · <span x-text="etaLabel"></span></p>
                    </div>
                    <button type="button" @click="confirmArrival()" class="btn btn-sm btn-ink shrink-0" title="{{ __('Je suis arrivé') }}"><span class="material-symbols-outlined" style="font-size:16px">check</span></button>
                </div>
                <div class="mt-2.5 h-1.5 rounded-full bg-paper overflow-hidden"><div class="h-full rounded-full bg-coral transition-all duration-500" :style="'width:' + progressPct + '%'"></div></div>

                {{-- Trajet en transports : sections, section courante en relief --}}
                <template x-if="leg && leg.transit && leg.sections && leg.sections.length">
                    <div class="mt-2.5">
                        <button type="button" @click="sheet = !sheet; measure()" class="w-full flex items-center gap-2 text-left">
                            <span class="flex items-center gap-1 flex-1 min-w-0 overflow-x-auto no-scrollbar">
                                <template x-for="(s, i) in leg.sections" :key="i">
                                    <span class="inline-flex items-center gap-0.5 shrink-0" :class="i === sectionIdx ? 'opacity-100' : 'opacity-50'">
                                        <template x-if="s.type === 'walk'"><span class="inline-flex items-center text-ink-muted"><span class="material-symbols-outlined" style="font-size:14px">directions_walk</span><span class="text-[10px]" x-text="s.minutes"></span></span></template>
                                        <template x-if="s.type === 'wait'"><span class="material-symbols-outlined text-ink-muted" style="font-size:14px">hourglass_top</span></template>
                                        <template x-if="s.type === 'pt'"><span class="rounded-md px-1.5 py-0.5 text-[10px] font-bold leading-none" :class="i === sectionIdx && 'ring-2 ring-offset-1 ring-ink'" :style="'background:' + s.color + ';color:' + s.text_color" x-text="lineLabel(s)"></span></template>
                                        <span x-show="i < leg.sections.length - 1" class="material-symbols-outlined text-ink/30" style="font-size:12px">chevron_right</span>
                                    </span>
                                </template>
                            </span>
                            <span class="text-[10px] text-ink-muted inline-flex items-center gap-0.5 shrink-0" x-show="leg.live"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span><span x-text="data.t.transitLive"></span></span>
                            <span class="material-symbols-outlined text-ink-muted shrink-0 transition-transform" :class="sheet && 'rotate-180'" style="font-size:18px">expand_more</span>
                        </button>
                        <div x-show="sheet" x-cloak class="mt-2 max-h-[34vh] overflow-y-auto rounded-2xl bg-paper/70 px-3 py-2">
                            <ol class="space-y-1.5">
                                <template x-for="(s, i) in leg.sections" :key="'d' + i">
                                    <li class="flex items-start gap-2 text-xs rounded-xl px-2 py-1.5" :class="i === sectionIdx ? 'bg-white shadow-card' : ''">
                                        <template x-if="s.type === 'walk'"><span class="material-symbols-outlined text-ink-muted shrink-0" style="font-size:16px">directions_walk</span></template>
                                        <template x-if="s.type === 'wait'"><span class="material-symbols-outlined text-ink-muted shrink-0" style="font-size:16px">hourglass_top</span></template>
                                        <template x-if="s.type === 'pt'"><span class="rounded-md px-1.5 py-0.5 text-[10px] font-bold leading-none shrink-0 mt-0.5" :style="'background:' + s.color + ';color:' + s.text_color" x-text="lineLabel(s)"></span></template>
                                        <span class="min-w-0 flex-1">
                                            <template x-if="s.type === 'walk'"><span><span x-text="data.t.walk + ' ' + s.minutes + ' ' + data.t.min"></span><span class="text-ink-muted" x-show="s.to" x-text="' → ' + s.to"></span></span></template>
                                            <template x-if="s.type === 'wait'"><span class="text-ink-muted" x-text="data.t.wait + ' ' + s.minutes + ' ' + data.t.min"></span></template>
                                            <template x-if="s.type === 'pt'"><span><span class="font-semibold" x-text="s.from"></span><span class="text-ink-muted" x-text="' → ' + s.to + ' · ' + s.stops + ' ' + (s.stops > 1 ? data.t.stops : data.t.stop)"></span><template x-if="s.alerts && s.alerts.length"><span class="block text-amber-700 mt-0.5" x-text="s.alerts.map(a => a.title + (a.text ? ' · ' + a.text : '')).join(' — ')"></span></template></span></template>
                                        </span>
                                        <span class="tabular-nums text-ink-muted shrink-0" x-text="s.depart_at || ''"></span>
                                    </li>
                                </template>
                            </ol>
                        </div>
                    </div>
                </template>

                <div class="mt-2.5 flex items-center justify-between text-[11px] text-ink-muted">
                    <span x-show="accuracy" x-text="'GPS ± ' + Math.round(accuracy) + ' m'"></span>
                    <span x-show="simulate" class="text-coral font-semibold">{{ __('Simulation') }}</span>
                    <button type="button" @click="quit()" class="font-semibold text-ink-muted hover:text-coral inline-flex items-center gap-1"><span class="material-symbols-outlined" style="font-size:14px">close</span>{{ __('Quitter') }}</button>
                </div>
            </div>

            {{-- Arrivée à une étape --}}
            <div :class="{ hidden: !arrived || done }" class="hidden nav-card card p-4 sm:p-5">
                <div class="flex items-center gap-3">
                    <span class="h-11 w-11 rounded-2xl bg-teal-soft text-teal flex items-center justify-center shrink-0"><span class="material-symbols-outlined">check_circle</span></span>
                    <div class="min-w-0 flex-1">
                        <p class="eyebrow">{{ __('Tu es arrivé') }}</p>
                        <p class="font-display text-xl leading-tight line-clamp-2 break-words" x-text="target ? target.title : ''"></p>
                        <p class="text-xs text-ink-muted mt-0.5 truncate" x-text="target && target.visit ? data.t.plannedVisit + ' ' + target.visit + ' ' + data.t.min + (target.hours && target.hours.status === 'open' ? ' · ' + data.t.open + ' ' + target.hours.opens + '–' + target.hours.closes : '') : ''"></p>
                    </div>
                </div>
                {{-- Audioguide : présentation du lieu, lue à voix haute --}}
                <template x-if="target && target.narration">
                    <div class="mt-3 rounded-2xl bg-paper px-3 py-2.5" x-data="{ open: false }">
                        <div class="flex items-center gap-2">
                            <button type="button" @click="toggleNarration()" class="btn btn-sm shrink-0" :class="narrating ? 'btn-ink' : 'btn-soft'"><span class="material-symbols-outlined" style="font-size:16px" x-text="narrating ? 'stop_circle' : 'headphones'"></span><span x-text="narrating ? data.t.stopListen : data.t.listen"></span></button>
                            <button type="button" @click="open = !open" class="ml-auto text-xs font-semibold text-ink-muted hover:text-ink inline-flex items-center gap-1"><span class="material-symbols-outlined transition-transform" :class="open && 'rotate-180'" style="font-size:16px">expand_more</span>{{ __('Lire') }}</button>
                        </div>
                        <p x-show="open" x-cloak class="mt-2 text-xs leading-relaxed text-ink-soft max-h-40 overflow-y-auto" x-text="target.narration"></p>
                    </div>
                </template>
                <div class="mt-3 flex gap-2">
                    <button type="button" @click="continueRoute()" class="btn btn-md btn-primary flex-1 min-w-0"><span class="material-symbols-outlined shrink-0" style="font-size:18px">arrow_forward</span><span class="truncate" x-text="nextLabel"></span></button>
                    <template x-if="target && target.url"><a :href="target.url" class="btn btn-md btn-soft shrink-0"><span class="material-symbols-outlined" style="font-size:18px">info</span>{{ __('Fiche') }}</a></template>
                </div>
            </div>

            {{-- Fin --}}
            <div :class="{ hidden: !done }" class="hidden nav-card card p-4 sm:p-5 text-center">
                <span class="material-symbols-outlined text-coral" style="font-size:40px">celebration</span>
                <p class="font-display text-2xl mt-1">{{ __('Parcours terminé !') }}</p>
                <p class="text-sm text-ink-muted mt-1" x-text="walked > 0 ? data.t.walked.replace(':d', formatDistance(walked)) : data.t.bravo"></p>
                <div class="mt-3 flex flex-wrap gap-2 justify-center">
                    <template x-if="data.journalUrl"><a :href="data.journalUrl" class="btn btn-md btn-primary"><span class="material-symbols-outlined" style="font-size:18px">auto_stories</span>{{ __('Carnet de voyage') }}</a></template>
                    <a :href="backUrl" class="btn btn-md" :class="data.journalUrl ? 'btn-soft' : 'btn-primary'"><span class="material-symbols-outlined" style="font-size:18px">arrow_back</span>{{ __('Retour au parcours') }}</a>
                    <a href="{{ route('map.index') }}" class="btn btn-md btn-soft">{{ __('Carte') }}</a>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function caminoNav(data) {
            const C = window.Camino;
            const R = 6371000;
            const toRad = d => d * Math.PI / 180;
            const dist = (a, b) => { const x = toRad(b[1] - a[1]) * Math.cos(toRad((a[0] + b[0]) / 2)); const y = toRad(b[0] - a[0]); return Math.sqrt(x * x + y * y) * R; };
            const bearing = (a, b) => { const y = Math.sin(toRad(b[1] - a[1])) * Math.cos(toRad(b[0])); const x = Math.cos(toRad(a[0])) * Math.sin(toRad(b[0])) - Math.sin(toRad(a[0])) * Math.cos(toRad(b[0])) * Math.cos(toRad(b[1] - a[1])); return (Math.atan2(y, x) * 180 / Math.PI + 360) % 360; };
            // Projection d'un point sur un segment [a,b] : retourne {t, d, p}
            const project = (p, a, b) => { const ax = a[1], ay = a[0], bx = b[1], by = b[0], px = p[1], py = p[0]; const kx = Math.cos(toRad(ay)); const dx = (bx - ax) * kx, dy = by - ay; const len2 = dx * dx + dy * dy; let t = len2 === 0 ? 0 : (((px - ax) * kx * dx + (py - ay) * dy) / len2); t = Math.max(0, Math.min(1, t)); const q = [ay + (by - ay) * t, ax + (bx - ax) * t]; return { t, d: dist(p, q), p: q }; };
            const ICONS = { 1: 'straight', 2: 'straight', 3: 'straight', 4: 'sports_score', 5: 'sports_score', 6: 'sports_score', 7: 'straight', 8: 'straight', 9: 'turn_slight_right', 10: 'turn_right', 11: 'turn_sharp_right', 12: 'u_turn_right', 13: 'u_turn_left', 14: 'turn_sharp_left', 15: 'turn_left', 16: 'turn_slight_left', 17: 'straight', 18: 'turn_slight_right', 19: 'turn_slight_left', 20: 'turn_slight_right', 21: 'turn_slight_left', 22: 'straight', 23: 'turn_slight_right', 24: 'turn_slight_left', 25: 'merge', 26: 'roundabout_right', 27: 'roundabout_right', 37: 'merge', 38: 'merge', 40: 'directions_subway', 41: 'exit_to_app' };
            const todayAt = hhmm => { if (!hhmm) return null; const [h, m] = hhmm.split(':').map(Number); const d = new Date(); d.setHours(h, m, 0, 0); return d.getTime(); };
            let nav = null, watchId = null, wakeLock = null, simTimer = null, boardTimer = null;
            let cum = [];
            let lastFix = 0, speedMs = 0, lastCamera = 0;
            const targets = data.steps.map(s => ({ ...s, kind: s.kind || 'visit' }));
            if (data.end) targets.push({ lat: data.end.lat, lng: data.end.lng, title: data.end.label || data.t.arrival, cover: null, kind: 'end', visit: 0, url: null, hours: null });
            const allPoints = [[data.start.lat, data.start.lng], ...targets.map(t => [t.lat, t.lng])];

            return {
                data,
                started: false, done: false, arrived: false, muted: false, follow: true, overview: false, bearing: 0, simulate: data.simulate > 0, simSpeed: Math.max(1, data.simulate || 1),
                legIndex: 0, pos: null, heading: 0, accuracy: null, gpsError: null,
                leg: null, segIdx: 0, along: 0, instruction: '', street: '', icon: 'straight', distToManeuver: null, maneuverIdx: -1, spokenIdx: -1, spokenApproach: -1, thenManeuver: null,
                remaining: 0, offRoute: false, offRouteCount: 0, rerouting: false, walked: 0, lastPos: null,
                sectionIdx: -1, sheet: false, transitLoading: false, spokenAlight: -1, sheetHeight: 200,
                audioguide: true, narrating: false,
                backUrl: data.backUrl,

                get target() { return targets[this.legIndex] || null; },
                get progressLabel() { const n = targets.length; const t = this.target; return t && t.kind === 'end' ? data.t.returnArrival : data.t.stepOf + ' ' + (this.legIndex + 1) + ' / ' + (data.end ? n - 1 : n); },
                get progressPct() { const total = this.legTotal(); return total > 0 ? Math.max(0, Math.min(100, Math.round((total - this.remaining) / total * 100))) : 0; },
                get etaLabel() {
                    let min;
                    if (this.leg && this.leg.transit && this.leg.arrive_at) { min = Math.max(0, Math.round((todayAt(this.leg.arrive_at) - Date.now()) / 60000)); }
                    else { const speed = data.mode === 'bike' ? 3.6 : 1.3; min = Math.round(this.remaining / speed / 60); }
                    const eta = new Date(Date.now() + min * 60000);
                    return (min <= 1 ? data.t.imminent : data.t.about + ' ' + min + ' ' + data.t.min) + ' · ' + data.t.arriveAt + ' ' + eta.getHours() + 'h' + String(eta.getMinutes()).padStart(2, '0');
                },
                get distanceLabel() { if (this.distToManeuver === null) return data.t.onRoute; if (this.distToManeuver < 15) return data.t.now; return data.t.inShort + ' ' + this.formatDistance(this.distToManeuver); },
                get nextLabel() { const next = targets[this.legIndex + 1]; if (!next) return data.t.finish; return next.kind === 'end' ? data.t.returnStart : data.t.towards + ' ' + next.title; },
                get section() { return this.leg && this.leg.sections ? (this.leg.sections[this.sectionIdx] || null) : null; },
                get onboard() { const s = this.section; return !!(this.started && !this.arrived && s && s.type === 'pt'); },
                get onboardLabel() {
                    const s = this.section; if (!s) return '';
                    const parts = [s.stops + ' ' + (s.stops > 1 ? data.t.stops : data.t.stop)];
                    if (s.arrive_at) { const min = Math.max(0, Math.round((todayAt(s.arrive_at) - Date.now()) / 60000)); parts.push(data.t.plannedArrival + ' ' + s.arrive_at + (min > 0 ? ' (' + min + ' ' + data.t.min + ')' : '')); }
                    return parts.join(' · ');
                },
                get nextLine() { if (!this.leg || !this.leg.transit || !this.leg.sections) return null; for (let i = Math.max(0, this.sectionIdx); i < this.leg.sections.length; i++) { const s = this.leg.sections[i]; if (s.type === 'pt') return i === this.sectionIdx ? null : s; } return null; },
                get nextDepart() { const s = this.nextLine; return s && s.depart_at ? s.depart_at : ''; },
                lineLabel(l) { return ((l.mode === 'Métro' ? 'M' : l.mode) + ' ' + (l.code || '')).trim(); },

                async init() {
                    const NavMap = await C.loadNavMap();
                    nav = new NavMap(document.getElementById('nav-map'), { dark: document.documentElement.classList.contains('dark'), center: [data.start.lat, data.start.lng], zoom: 14, bottomPadding: this.sheetHeight, onDrag: () => { this.follow = false; } });
                    window.caminoNavMap = nav;
                    nav.map.on('rotate', () => { this.bearing = nav.getBearing(); });
                    const full = data.geometry && data.geometry.length > 1 ? data.geometry : allPoints;
                    nav.setRoute(full);
                    nav.addMarker([data.start.lat, data.start.lng], C.stepPinHtml(0, true));
                    targets.forEach((t, i) => nav.addMarker([t.lat, t.lng], t.kind === 'end' ? C.stepPinHtml('<span class="material-symbols-outlined" style="font-size:16px">sports_score</span>') : (t.kind === 'lunch' ? C.placePinHtml('restauration') : C.stepPinHtml(i + 1))));
                    this.$nextTick(() => this.measure());
                    window.addEventListener('resize', () => this.measure());
                    this.loadLeg(0, null);
                    nav.whenReady(() => { if (!this.started) this.fitAll(); else this.recenter(); });
                    if ('speechSynthesis' in window) window.speechSynthesis.getVoices();
                },
                // Hauteur de la feuille basse : la caméra garde la position au-dessus, les boutons carte se placent au-dessus.
                measure() { this.$nextTick(() => { const h = this.$refs.sheet ? this.$refs.sheet.offsetHeight : 200; this.sheetHeight = h; if (nav) nav.setBottomPadding(h + 24); }); },
                fitAll() { if (!nav) return; this.overview = true; this.follow = false; nav.fit(allPoints); },
                fitLeg() { if (!nav || !this.leg) return; this.overview = true; this.follow = false; nav.fit(this.pos ? [this.pos, ...this.leg.shape] : this.leg.shape); },
                toggleOverview() { if (this.overview) { this.recenter(); } else { this.started && this.leg ? this.fitLeg() : this.fitAll(); } },
                recenter() { this.follow = true; this.overview = false; if (this.pos && nav) nav.follow(this.pos, this.heading, { zoom: this.zoomFor(), duration: 700 }); },
                northUp() { if (nav) nav.northUp(); },
                zoomFor() { if (data.mode === 'bike') return speedMs > 6 ? 16.2 : 16.8; return speedMs > 2.2 ? 17 : 17.6; },
                toggleMute() { this.muted = !this.muted; if (this.muted && 'speechSynthesis' in window) window.speechSynthesis.cancel(); else this.speak(data.t.voiceOn); },
                legTotal() { return cum.length ? cum[cum.length - 1] : 0; },
                formatDistance(m) { if (m === null || m === undefined) return ''; if (m >= 1000) return (m / 1000).toFixed(1).replace('.', ',') + ' km'; return Math.max(0, Math.round(m / 10) * 10) + ' m'; },

                // ---------------------------------------------------------------- tronçon courant
                loadLeg(index, fromPos) {
                    const t = targets[index];
                    if (!t) { this.finish(); return; }
                    const stored = data.legs[index];
                    let shape = stored && stored.shape && stored.shape.length > 1 ? stored.shape : null;
                    let maneuvers = stored && stored.maneuvers ? stored.maneuvers : [];
                    if (!shape) {
                        const from = fromPos || (index === 0 ? [data.start.lat, data.start.lng] : [targets[index - 1].lat, targets[index - 1].lng]);
                        shape = [from, [t.lat, t.lng]];
                        maneuvers = [t.kind === 'end' ? { type: 8, text: data.t.backToStart, verbal: data.t.backToStartVerbal, street: '', begin: 0, end: 1 } : { type: 8, text: data.t.headTo + ' ' + t.title, verbal: data.t.headToVerbal + ' ' + t.title + '.', street: '', begin: 0, end: 1 }];
                    }
                    this.setLeg(shape, maneuvers, !!(stored && stored.transit), stored && stored.transit ? stored : null);
                },
                setLeg(shape, maneuvers, transit = false, info = null) {
                    this.leg = { transit, shape, maneuvers: maneuvers.filter(m => m.type !== 4 && m.type !== 5 && m.type !== 6).concat(maneuvers.filter(m => m.type === 4 || m.type === 5 || m.type === 6).slice(0, 1)), sections: info && info.sections ? info.sections : [], arrive_at: info ? info.arrive_at || null : null, live: !!(info && info.live) };
                    cum = [0];
                    for (let i = 1; i < shape.length; i++) cum[i] = cum[i - 1] + dist(shape[i - 1], shape[i]);
                    if (nav) nav.setLeg(shape, { transit, color: transit ? (data.mode === 'transit' && info && info.lines && info.lines[0] ? '#1D4ED8' : '#1D4ED8') : (data.mode === 'bike' ? '#0F8B8D' : '#FF5A3C') });
                    this.segIdx = 0; this.along = 0; this.remaining = this.legTotal(); this.maneuverIdx = -1; this.spokenIdx = -1; this.spokenApproach = -1; this.spokenAlight = -1; this.offRoute = false; this.offRouteCount = 0; this.sheet = false;
                    this.updateSection(0);
                    this.updateInstruction(0);
                    this.measure();
                },
                updateSection(along) {
                    if (!this.leg || !this.leg.transit || !this.leg.sections.length) { this.sectionIdx = -1; return; }
                    let idx = -1;
                    this.leg.sections.forEach((s, i) => { if (s.begin === undefined) return; const a = cum[s.begin] ?? 0, b = cum[s.end] ?? a; if (along >= a - 1 && (along <= b + 1 || i === this.leg.sections.length - 1)) idx = idx === -1 ? i : idx; });
                    if (idx === -1) idx = along <= 0 ? 0 : this.leg.sections.length - 1;
                    if (idx !== this.sectionIdx) { this.sectionIdx = idx; this.measure(); }
                },
                alight() {
                    const s = this.section; if (!s || s.type !== 'pt') return;
                    const endIdx = s.end;
                    this.segIdx = Math.max(0, endIdx - 1);
                    this.along = cum[endIdx] ?? this.along;
                    this.remaining = Math.max(0, this.legTotal() - this.along);
                    if (nav) nav.setDone(this.leg.shape.slice(0, endIdx + 1));
                    this.sectionIdx = Math.min(this.leg.sections.length - 1, this.sectionIdx + 1);
                    while (this.section && this.section.type === 'wait' && this.sectionIdx < this.leg.sections.length - 1) this.sectionIdx++;
                    this.spokenIdx = -1;
                    this.updateInstruction(this.along + 0.1);
                    const m = this.leg.maneuvers[this.maneuverIdx];
                    if (m) this.speak(m.verbal);
                    this.measure();
                },
                watchOnboard() {
                    if (boardTimer) clearInterval(boardTimer);
                    boardTimer = setInterval(() => {
                        if (!this.onboard) return;
                        const s = this.section; const at = todayAt(s.arrive_at);
                        if (at && Date.now() >= at - 70000 && this.spokenAlight !== this.sectionIdx) { this.spokenAlight = this.sectionIdx; this.speak(data.t.nextStopVerbal.replace(':stop', s.to)); if (navigator.vibrate) navigator.vibrate([80, 40, 80]); }
                    }, 15000);
                },

                // ---------------------------------------------------------------- démarrage
                async start() {
                    this.started = true; this.follow = true; this.overview = false;
                    this.measure();
                    try { if ('wakeLock' in navigator) wakeLock = await navigator.wakeLock.request('screen'); } catch (e) {}
                    document.addEventListener('visibilitychange', async () => { if (document.visibilityState === 'visible' && 'wakeLock' in navigator && this.started) { try { wakeLock = await navigator.wakeLock.request('screen'); } catch (e) {} } });
                    if (data.mode === 'transit' && data.transit && data.legs[0] && data.legs[0].transit && !this.simulate) await this.refreshTransit(0, [data.start.lat, data.start.lng]);
                    this.speak(data.t.started + ' ' + (this.leg.maneuvers[0] ? this.leg.maneuvers[0].verbal : data.t.follow));
                    this.watchOnboard();
                    if (this.simulate) { this.runSimulation(); return; }
                    if (!navigator.geolocation) { this.gpsError = data.t.gpsUnavailable; return; }
                    watchId = navigator.geolocation.watchPosition(
                        p => { this.gpsError = null; this.onPosition([p.coords.latitude, p.coords.longitude], p.coords.heading, p.coords.accuracy); },
                        e => { this.gpsError = e.code === 1 ? data.t.gpsDenied : data.t.gpsWeak; },
                        { enableHighAccuracy: true, maximumAge: 1000, timeout: 15000 }
                    );
                },
                quit() { this.stopTracking(); window.location.href = this.backUrl; },
                stopTracking() { if (watchId !== null) navigator.geolocation.clearWatch(watchId); if (simTimer) clearInterval(simTimer); if (boardTimer) clearInterval(boardTimer); if (wakeLock) { try { wakeLock.release(); } catch (e) {} } if ('speechSynthesis' in window) window.speechSynthesis.cancel(); },

                // ---------------------------------------------------------------- position
                onPosition(latlng, heading, accuracy) {
                    const now = Date.now();
                    if (this.lastPos) {
                        const d = dist(this.lastPos, latlng);
                        if (d < 200) this.walked += d;
                        if (lastFix) { const dt = (now - lastFix) / 1000; if (dt > 0.5) speedMs = 0.7 * speedMs + 0.3 * (d / dt); }
                        // Cap : celui du GPS s'il est fiable, sinon la direction du déplacement (au-dessus de 3 m), sinon la direction du tracé.
                        if (d > 3 && (heading === null || heading === undefined || isNaN(heading))) heading = bearing(this.lastPos, latlng);
                    }
                    lastFix = now;
                    this.lastPos = latlng; this.pos = latlng; this.accuracy = accuracy;
                    if (this.arrived || this.done) { if (nav) nav.setUser(latlng, this.heading, accuracy); return; }
                    // Projection sur le tronçon courant
                    const shape = this.leg.shape;
                    let best = { d: Infinity, i: 0, t: 0, p: latlng };
                    const from = Math.max(0, this.segIdx - 3);
                    for (let i = from; i < shape.length - 1; i++) { const pr = project(latlng, shape[i], shape[i + 1]); if (pr.d < best.d - 0.5 || (pr.d < best.d + 0.5 && i >= this.segIdx)) best = { d: pr.d, i, t: pr.t, p: pr.p }; }
                    if ((heading === null || heading === undefined || isNaN(heading)) && best.d < 25) heading = bearing(shape[best.i], shape[best.i + 1]);
                    if (heading !== null && heading !== undefined && !isNaN(heading)) this.heading = heading;
                    // Sur le tracé, la flèche est posée sur la ligne (plus lisible) ; sinon à la position brute.
                    const shown = best.d < Math.max(12, (accuracy || 0) * 0.6) ? best.p : latlng;
                    if (nav) nav.setUser(shown, this.heading, accuracy);
                    if (this.follow && nav && now - lastCamera > 400) { lastCamera = now; nav.follow(shown, this.heading, { zoom: this.zoomFor(), duration: this.simulate ? 900 : 800 }); }
                    const target = this.target;
                    const dTarget = dist(latlng, [target.lat, target.lng]);
                    if (dTarget < 30 || (dTarget < 45 && best.i >= shape.length - 2)) { this.onArrival(); return; }
                    if (best.d > Math.max(45, (accuracy || 0) * 1.5) && !this.leg.transit) {
                        this.offRouteCount++;
                        if (this.offRouteCount >= 3 && !this.rerouting) this.reroute();
                        this.offRoute = this.offRouteCount >= 2;
                        return;
                    }
                    this.offRouteCount = 0; this.offRoute = false;
                    const newAlong = cum[best.i] + (cum[best.i + 1] - cum[best.i]) * best.t;
                    if (this.onboard && newAlong < this.along) return;
                    this.segIdx = best.i;
                    this.along = newAlong;
                    this.remaining = Math.max(0, this.legTotal() - this.along);
                    if (nav) nav.setDone([...shape.slice(0, best.i + 1), best.p]);
                    this.updateSection(this.along);
                    this.updateInstruction(this.along);
                },
                updateInstruction(along) {
                    const ms = this.leg.maneuvers;
                    if (!ms.length) { this.instruction = this.target && this.target.kind === 'end' ? data.t.backToStart : data.t.headTo + ' ' + (this.target ? this.target.title : data.t.nextStep); this.street = ''; this.icon = 'straight'; this.distToManeuver = this.remaining; this.thenManeuver = null; return; }
                    let idx = ms.findIndex(m => (cum[m.begin] ?? 0) > along + 8);
                    if (idx === -1) idx = ms.length - 1;
                    const m = ms[idx];
                    const d = Math.max(0, (cum[m.begin] ?? this.legTotal()) - along);
                    this.distToManeuver = idx === 0 && along < 5 ? null : d;
                    this.instruction = m.text; this.street = m.street || ''; this.icon = ICONS[m.type] || 'straight';
                    const next = ms[idx + 1];
                    this.thenManeuver = next ? { icon: ICONS[next.type] || 'straight', text: next.text, d: Math.max(0, (cum[next.begin] ?? this.legTotal()) - (cum[m.begin] ?? 0)) } : null;
                    if (nav) nav.setTurn(idx < ms.length - 1 || this.leg.shape.length > 2 ? this.leg.shape[Math.min(m.begin, this.leg.shape.length - 1)] : null);
                    if (idx !== this.maneuverIdx) { this.maneuverIdx = idx; this.spokenApproach = -1; }
                    if (this.onboard) return;
                    const approach = data.mode === 'bike' ? 150 : 80;
                    if (d <= approach && d > 25 && this.spokenApproach !== idx) { this.spokenApproach = idx; this.speak(data.t.in + ' ' + Math.round(d / 10) * 10 + ' ' + data.t.meters + ', ' + this.lower(m.verbal)); }
                    else if (d <= 25 && this.spokenIdx !== idx) { this.spokenIdx = idx; this.speak(m.verbal); }
                },
                lower(s) { return s ? s.charAt(0).toLowerCase() + s.slice(1) : ''; },

                // ---------------------------------------------------------------- arrivée / suite
                onArrival() {
                    if (this.arrived) return;
                    this.arrived = true;
                    this.measure();
                    const t = this.target;
                    if (t.kind === 'end') { this.finish(); return; }
                    // Annonce d'arrivée, puis l'audioguide raconte le lieu (même énoncé : la synthèse vocale enchaîne sans coupure).
                    const arrival = data.t.arrived + ' ' + t.title + '.' + (t.visit ? ' ' + data.t.visit + ' ' + t.visit + ' ' + data.t.minutes + '.' : '');
                    if (this.audioguide && t.narration && !this.muted) { this.narrating = true; this.speak(arrival + ' ' + data.t.audioguideIntro + ' ' + t.narration, () => { this.narrating = false; }); }
                    else this.speak(arrival);
                    if (navigator.vibrate) navigator.vibrate([120, 60, 120]);
                    if (nav && this.pos) { this.overview = true; this.follow = false; nav.fit([this.pos, [t.lat, t.lng]], { padding: 80 }); }
                    this.recordVisit(t);
                },
                confirmArrival() { this.onArrival(); },
                toggleNarration() {
                    if (this.narrating) { if ('speechSynthesis' in window) window.speechSynthesis.cancel(); this.narrating = false; return; }
                    const t = this.target; if (!t || !t.narration) return;
                    const wasMuted = this.muted; this.muted = false;
                    this.narrating = true;
                    this.speak(t.title + '. ' + t.narration, () => { this.narrating = false; this.muted = wasMuted; });
                },
                async recordVisit(t) {
                    if (!data.auth || this.simulate || !t.visitUrl) return;
                    try {
                        const token = document.querySelector('meta[name=csrf-token]')?.content;
                        await fetch(t.visitUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token }, body: JSON.stringify({ source: 'guidage', minutes: t.visit || null, itinerary_id: data.itineraryId }) });
                    } catch (e) {}
                },
                async continueRoute() {
                    const next = this.legIndex + 1;
                    this.arrived = false;
                    if (!targets[next]) { this.finish(); return; }
                    this.legIndex = next;
                    const stored = data.legs[next];
                    const from = this.pos && !this.simulate ? this.pos : [targets[next - 1].lat, targets[next - 1].lng];
                    if (data.mode === 'transit' && data.transit && !this.simulate && (stored && stored.transit)) { await this.refreshTransit(next, from); }
                    else if (this.pos && !this.simulate) { await this.reroute(true); }
                    else { this.loadLeg(next, this.pos); }
                    this.speak((targets[next].kind === 'end' ? data.t.lastStep + ' ' : data.t.direction + ' ' + targets[next].title + '. ') + (this.leg.maneuvers[0] ? this.leg.maneuvers[0].verbal : ''));
                    this.recenter();
                    if (this.simulate) this.runSimulation();
                },
                async refreshTransit(index, from) {
                    const t = targets[index];
                    this.transitLoading = true;
                    try {
                        const r = await fetch('/api/v1/transit', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ from: { lat: from[0], lng: from[1] }, to: { lat: t.lat, lng: t.lng } }) });
                        const j = await r.json();
                        const journey = j.journeys && j.journeys[0];
                        if (journey && journey.shape && journey.shape.length > 1) { this.setLeg(journey.shape, journey.maneuvers || [], true, { ...journey, live: true }); data.legs[index] = { ...journey, transit: true, live: true }; }
                        else this.loadLeg(index, from);
                    } catch (e) { this.loadLeg(index, from); }
                    this.transitLoading = false;
                },
                finish() { this.done = true; this.arrived = false; this.stopTracking(); this.speak(data.t.done); this.measure(); this.fitAll(); },
                async reroute(silent = false) {
                    if (!this.pos) { this.loadLeg(this.legIndex, null); return; }
                    this.rerouting = true;
                    try {
                        const r = await fetch('/api/v1/route', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ points: [{ lat: this.pos[0], lng: this.pos[1] }, { lat: this.target.lat, lng: this.target.lng }], mode: data.mode }) });
                        const j = await r.json();
                        const leg = j.legs && j.legs[0];
                        if (leg && leg.shape && leg.shape.length > 1) { this.setLeg(leg.shape, leg.maneuvers || [], false); if (!silent) this.speak(data.t.rerouted + ' ' + (this.leg.maneuvers[0] ? this.leg.maneuvers[0].verbal : '')); }
                        else this.loadLeg(this.legIndex, this.pos);
                    } catch (e) { this.loadLeg(this.legIndex, this.pos); }
                    this.rerouting = false; this.offRoute = false; this.offRouteCount = 0;
                },

                // ---------------------------------------------------------------- voix
                speak(text, onEnd = null) {
                    if (this.narrating && !onEnd) this.narrating = false; // une consigne interrompt la présentation en cours
                    if (this.muted || !('speechSynthesis' in window) || !text) { if (onEnd) onEnd(); return; }
                    const u = new SpeechSynthesisUtterance(text.replace(/\s+/g, ' '));
                    u.lang = data.lang; u.rate = 1.0;
                    const voice = window.speechSynthesis.getVoices().find(v => v.lang && v.lang.toLowerCase().startsWith(data.lang.slice(0, 2).toLowerCase()));
                    if (voice) u.voice = voice;
                    if (onEnd) { u.onend = onEnd; u.onerror = onEnd; }
                    window.speechSynthesis.cancel();
                    window.speechSynthesis.speak(u);
                },

                // ---------------------------------------------------------------- simulation (test sans GPS)
                runSimulation() {
                    if (simTimer) clearInterval(simTimer);
                    const shape = this.leg.shape; let i = 0, t = 0; const base = (data.mode === 'bike' ? 4.5 : 1.6) * this.simSpeed;
                    simTimer = setInterval(() => {
                        if (this.arrived || this.done) { clearInterval(simTimer); return; }
                        if (i >= shape.length - 1) { this.onPosition(shape[shape.length - 1], null, 8); clearInterval(simTimer); return; }
                        const speed = this.onboard ? base * 8 : base;
                        const segLen = dist(shape[i], shape[i + 1]) || 1;
                        t += speed / segLen;
                        while (t >= 1 && i < shape.length - 1) { t -= 1; i++; if (i >= shape.length - 1) break; }
                        const a = shape[Math.min(i, shape.length - 1)], b = shape[Math.min(i + 1, shape.length - 1)];
                        const p = [a[0] + (b[0] - a[0]) * Math.min(t, 1), a[1] + (b[1] - a[1]) * Math.min(t, 1)];
                        this.onPosition(p, bearing(a, b), 8);
                    }, 1000);
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
