@php
    $freeSunday = app(\App\Services\FreeSundayService::class);
    $isFirstSunday = $freeSunday->isFirstSunday(\Illuminate\Support\Carbon::now(config('app.timezone')));
    $mapData = [
        'apiPois' => url('/api/v1/pois'), 'apiAlerts' => url('/api/v1/alerts'), 'apiHistory' => url('/api/v1/history'),
        'placeUrl' => url('/lieux'), 'addUrl' => url('/parcours/ajouter-lieu'), 'removeUrl' => url('/parcours/retirer-lieu'), 'generateUrl' => route('itineraries.create'),
        'cart' => array_values(session('itinerary_place_ids', [])),
        'query' => request('q', ''), 'filter' => request('filtre', 'all'),
        'firstSunday' => $isFirstSunday, 'freeSundayLabel' => $freeSunday->label(), 'locale' => app()->getLocale(),
        'lang' => \App\Http\Middleware\SetLocale::speechLanguage(),
        'csrf' => csrf_token(),
        't' => [
            'loading' => __('Chargement…'), 'none' => __('Aucun lieu ici'), 'places' => __('lieux'), 'place' => __('lieu'), 'more' => __('120+ lieux (zoome pour affiner)'),
            'free' => __('Gratuit'), 'noPrice' => __('Tarif non renseigné'), 'openNow' => __('Ouvert'), 'closed' => __('Fermé'), 'opensAt' => __('Ouvre à'), 'closesAt' => __('ferme à'), 'hoursUnknown' => __('Horaires inconnus'),
            'openAt' => __('Ouvert à'), 'closedAt' => __('Fermé à'), 'walk' => __('à pied'), 'noPos' => __('Impossible de récupérer ta position.'), 'added' => __('Ajouté au parcours'), 'removed' => __('Retiré du parcours'),
            'full' => __('Ton parcours est complet (15 lieux).'), 'freeToday' => __('Gratuit aujourd\'hui'), 'freeSunday' => __('Gratuit le :date'), 'thenLabel' => __('Paris d\'hier'), 'historyNone' => __('Pas de photo ancienne dans cette zone. Rapproche-toi du centre de Paris.'),
        ],
    ];
@endphp
<x-app-layout title="{{ __('Carte culturelle') }}" :fullscreen="true">

    <div id="map-page" class="absolute inset-0" x-data="caminoMap(@js($mapData))" @keydown.escape.window="closeSheet()">
        {{-- Carte --}}
        <div id="camino-map" class="absolute inset-0 z-0"></div>

        {{-- Recherche, collections et catégories (haut) --}}
        <div class="absolute top-[4.6rem] inset-x-0 z-[500] pointer-events-none">
            <div class="max-w-7xl mx-auto px-3 sm:px-6">
                <div class="md:max-w-2xl space-y-2 pointer-events-auto">
                    <div class="card flex items-center gap-1.5 pl-4 pr-1.5 py-1">
                        <span class="material-symbols-outlined text-ink-muted">search</span>
                        <input x-model.debounce.400ms="query" @input="load()" type="search" placeholder="{{ __('Musée gratuit Marais, une adresse, un lieu…') }}" class="flex-1 min-w-0 border-0 bg-transparent focus:ring-0 text-sm placeholder:text-ink-muted/70 !bg-transparent" aria-label="{{ __('Rechercher') }}">
                        <span x-show="loading" class="material-symbols-outlined text-ink-muted animate-spin" style="font-size:18px">progress_activity</span>
                        <button @click="toggleTime()" class="btn btn-icon btn-ghost !h-9 !w-9" :class="time.enabled && '!bg-ink !text-white'" title="{{ __('À quelle heure ?') }}"><span class="material-symbols-outlined" style="font-size:20px">schedule</span></button>
                        <button @click="locate(true)" class="btn btn-icon btn-ghost !h-9 !w-9" :class="user && '!text-teal'" title="{{ __('Autour de moi') }}"><span class="material-symbols-outlined" style="font-size:20px">my_location</span></button>
                    </div>

                    {{-- Curseur d'heure --}}
                    <div x-show="time.enabled" x-cloak x-transition class="card px-4 py-2.5 flex items-center gap-3">
                        <span class="material-symbols-outlined text-ink-muted" style="font-size:18px">schedule</span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between text-[11px] text-ink-muted"><span>{{ __('Ce qui sera ouvert à') }}</span><span class="font-display text-base text-ink" x-text="timeLabel"></span></div>
                            <input type="range" min="8" max="23" step="0.5" x-model.number="time.hour" @input.debounce.300ms="load()" class="w-full accent-coral" aria-label="{{ __('Heure') }}">
                        </div>
                        <button @click="toggleTime()" class="text-ink-muted hover:text-ink" aria-label="{{ __('Fermer') }}"><span class="material-symbols-outlined" style="font-size:18px">close</span></button>
                    </div>

                    {{-- Collections vivantes --}}
                    <div class="flex gap-1.5 overflow-x-auto hide-scrollbar pb-0.5">
                        <template x-for="c in collections" :key="c.key">
                            <button @click="setCollection(c.key)" class="chip shrink-0 !py-1.5" :data-active="collection === c.key" :class="c.key === 'free_sunday' && collection !== c.key && 'border-amber-300 text-amber-700 dark:text-amber-300'">
                                <span class="material-symbols-outlined" style="font-size:16px" x-text="c.icon"></span>
                                <span x-text="c.label"></span>
                            </button>
                        </template>
                    </div>
                    {{-- Catégories --}}
                    <div class="flex gap-1.5 overflow-x-auto hide-scrollbar pb-1">
                        <template x-for="f in filters" :key="f.key">
                            <button @click="setFilter(f.key)" class="chip shrink-0 !py-1.5" :data-active="filter === f.key" :style="filter === f.key && f.color ? `background:${f.color};border-color:${f.color}` : ''">
                                <span class="material-symbols-outlined" style="font-size:16px" x-text="f.icon"></span>
                                <span x-text="f.label"></span>
                            </button>
                        </template>
                        <button @click="layers.open = !layers.open" class="chip shrink-0 !py-1.5" :data-active="layers.open"><span class="material-symbols-outlined" style="font-size:16px">layers</span>{{ __('Couches') }}</button>
                    </div>
                    <div x-show="layers.open" x-cloak x-transition class="card px-3 py-2 flex flex-wrap gap-1.5 text-xs">
                        <label class="option-row !py-1.5 !px-2.5 !bg-transparent border border-ink/10"><span class="material-symbols-outlined text-coral" style="font-size:16px">campaign</span>{{ __('Alertes de la communauté') }}<input type="checkbox" class="switch !h-5 !w-9" x-model="layers.alerts" @change="render()"></label>
                        <label class="option-row !py-1.5 !px-2.5 !bg-transparent border border-ink/10"><span class="material-symbols-outlined text-amber-700" style="font-size:16px">history</span>{{ __('Paris d\'hier') }} <span class="text-[10px] text-ink-muted">{{ __('photos anciennes') }}</span><input type="checkbox" class="switch !h-5 !w-9" x-model="layers.history" @change="loadHistory()"></label>
                    </div>
                </div>
            </div>
        </div>

        {{-- Panneau latéral (desktop) --}}
        <aside class="hidden md:flex flex-col absolute top-[10.5rem] bottom-6 right-6 z-[500] w-[400px] card overflow-hidden">
            <div class="px-5 pt-4 pb-3 border-b border-ink/5 flex items-center justify-between gap-3">
                <div>
                    <p class="eyebrow">{{ __('Dans cette zone') }}</p>
                    <p class="text-sm font-semibold" x-text="countLabel()"></p>
                </div>
                <div class="flex gap-1">
                    <button @click="listMode = 'places'" class="btn btn-sm" :class="listMode === 'places' ? 'btn-ink' : 'btn-ghost'">{{ __('Lieux') }}</button>
                    <button @click="listMode = 'alerts'" class="btn btn-sm" :class="listMode === 'alerts' ? 'btn-ink' : 'btn-ghost'">{{ __('Alertes') }} <span x-show="alerts.length" class="ml-1 rounded-full bg-coral text-white px-1.5 text-[10px]" x-text="alerts.length"></span></button>
                </div>
            </div>
            <div class="flex-1 overflow-y-auto p-3 space-y-2" x-ref="list">
                <template x-if="listMode === 'places'">
                    <div class="space-y-2">
                        <template x-for="p in places" :key="p.id">
                            <div @mouseenter="highlight(p.id)" @mouseleave="highlight(null)" class="rounded-2xl hover:bg-paper transition group" :class="active === p.id && 'bg-paper'">
                                <button @click="openPlace(p)" class="w-full text-left flex gap-3 p-2">
                                    <div class="w-24 h-20 rounded-xl overflow-hidden shrink-0 placeholder-cover flex items-center justify-center relative" :style="`--c1:${style(p).color};--c2:#12161C`">
                                        <template x-if="p.media && p.media.cover"><img :src="p.media.cover" :alt="p.title" loading="lazy" class="w-full h-full object-cover"></template>
                                        <template x-if="!(p.media && p.media.cover)"><span class="material-symbols-outlined text-white/80" x-text="style(p).icon"></span></template>
                                        <span x-show="p.rating" class="absolute top-1 left-1 rounded-full bg-white/95 text-amber-700 text-[10px] font-bold px-1.5 py-0.5 inline-flex items-center gap-0.5"><span class="material-symbols-outlined filled" style="font-size:11px">star</span><span x-text="p.rating"></span></span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[10px] font-semibold" :style="`color:${style(p).color}`" x-text="p.category ? p.category.name : ''"></p>
                                        <p class="text-sm font-semibold leading-snug line-clamp-2 group-hover:text-coral transition" x-text="p.title"></p>
                                        <p class="text-[11px] mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                            <span :class="hoursClass(p)" x-text="hoursLabel(p)"></span>
                                            <span x-show="p.walk_min" class="text-ink-muted" x-text="p.walk_min + ' min ' + data.t.walk"></span>
                                        </p>
                                        <div class="flex flex-wrap items-center gap-1 mt-1 text-[10px]">
                                            <span x-show="p.is_free" class="badge badge-free !py-0.5">{{ __('Gratuit') }}</span>
                                            <span x-show="p.free_sunday" class="badge badge-event !py-0.5" x-text="freeSundayBadge()"></span>
                                            <span x-show="p.accessible === true" class="badge badge-free !py-0.5" title="{{ __('Accessible PMR') }}"><span class="material-symbols-outlined" style="font-size:12px">accessible</span></span>
                                            <span x-show="!p.is_free && p.price_level" class="text-ink-muted font-semibold" x-text="'€'.repeat(p.price_level || 0)"></span>
                                            <span x-show="p.alerts" class="badge badge-alert !py-0.5"><span class="material-symbols-outlined" style="font-size:12px">campaign</span><span x-text="p.alerts"></span></span>
                                            <span x-show="p.event" class="badge badge-event !py-0.5">{{ __('Événement') }}</span>
                                        </div>
                                    </div>
                                    <button @click.stop="toggleCart(p)" class="self-center h-9 w-9 rounded-full flex items-center justify-center shrink-0 transition" :class="inCart(p.id) ? 'bg-ink text-white' : 'bg-paper text-ink-muted hover:text-ink'" :title="inCart(p.id) ? @js(__('Retirer du parcours')) : @js(__('Ajouter au parcours'))"><span class="material-symbols-outlined" style="font-size:18px" x-text="inCart(p.id) ? 'check' : 'add'"></span></button>
                                </button>
                            </div>
                        </template>
                        <p x-show="!loading && !places.length" class="p-6 text-center text-sm text-ink-muted">{{ __('Aucun lieu ici avec ces filtres. Déplace la carte ou change de filtre.') }}</p>
                    </div>
                </template>
                <template x-if="listMode === 'alerts'">
                    <div class="space-y-2">
                        <template x-for="a in alerts" :key="a.id">
                            <button @click="focusAlert(a)" class="w-full text-left flex gap-3 p-3 rounded-2xl hover:bg-paper transition">
                                <span class="h-9 w-9 rounded-full flex items-center justify-center shrink-0" :style="`background:${a.color}22;color:${a.color}`"><span class="material-symbols-outlined" style="font-size:18px" x-text="a.icon"></span></span>
                                <div class="min-w-0 text-sm">
                                    <p class="font-semibold leading-snug" x-text="a.title"></p>
                                    <p class="text-xs text-ink-muted"><span x-text="a.label"></span><template x-if="a.place"><span> · <span x-text="a.place.title"></span></span></template> {{ __('· expire dans') }} <span x-text="a.expires_in"></span></p>
                                </div>
                            </button>
                        </template>
                        <div x-show="!alerts.length" class="p-6 text-center text-sm text-ink-muted">
                            <p>{{ __('Aucune alerte dans cette zone.') }}</p>
                            <button @click="$dispatch('open-alert', center())" class="btn btn-sm btn-primary mt-3"><span class="material-symbols-outlined" style="font-size:16px">campaign</span>{{ __('Signaler ici') }}</button>
                        </div>
                    </div>
                </template>
            </div>
        </aside>

        {{-- Boutons flottants --}}
        <div class="absolute right-3 md:right-[424px] z-[500] flex flex-col gap-2 transition-all" :class="cart.length ? 'bottom-[15.5rem] md:bottom-20' : 'bottom-[12.5rem] md:bottom-6'">
            <button @click="$dispatch('open-alert', center())" class="btn btn-md btn-primary shadow-float !px-3 md:!px-5" title="{{ __('Signaler un événement, une affluence, une fermeture') }}">
                <span class="material-symbols-outlined">campaign</span><span class="hidden sm:inline">{{ __('Signaler') }}</span>
            </button>
            <button @click="zoomIn()" class="btn btn-icon btn-soft hidden md:inline-flex"><span class="material-symbols-outlined">add</span></button>
            <button @click="zoomOut()" class="btn btn-icon btn-soft hidden md:inline-flex"><span class="material-symbols-outlined">remove</span></button>
        </div>

        {{-- Barre « construire un parcours » --}}
        <div x-show="cart.length" x-cloak x-transition class="absolute left-3 right-3 md:left-6 md:right-[424px] z-[500] bottom-[8.75rem] md:bottom-6 pointer-events-none">
            <div class="mx-auto max-w-md card !rounded-full pl-4 pr-1.5 py-1.5 flex items-center gap-3 pointer-events-auto shadow-float">
                <span class="material-symbols-outlined text-coral">route</span>
                <p class="text-sm font-semibold flex-1 min-w-0 truncate"><span x-text="cart.length"></span> <span x-text="cart.length > 1 ? data.t.places : data.t.place"></span> <span class="font-normal text-ink-muted hidden sm:inline">{{ __('choisis sur la carte') }}</span></p>
                <button @click="clearCart()" class="text-xs text-ink-muted hover:text-ink px-1" title="{{ __('Vider') }}"><span class="material-symbols-outlined" style="font-size:18px">delete</span></button>
                <a :href="data.generateUrl" class="btn btn-sm btn-ink"><span class="material-symbols-outlined" style="font-size:16px">auto_awesome</span>{{ __('Générer le parcours') }}</a>
            </div>
        </div>

        {{-- Mobile : cartes qui défilent en bas, synchronisées avec la carte --}}
        <div class="md:hidden absolute inset-x-0 bottom-0 z-[500] pb-[5.25rem] pointer-events-none">
            <p class="px-4 pb-1 text-[11px] font-semibold text-ink-soft flex items-center justify-between pointer-events-auto"><span x-text="countLabel()"></span><span x-show="user" class="text-ink-muted">{{ __('distances depuis ta position') }}</span></p>
            <div x-ref="carousel" @scroll.debounce.120ms="onCarouselScroll()" class="flex gap-3 overflow-x-auto hide-scrollbar snap-x snap-mandatory px-4 pointer-events-auto">
                <template x-for="p in places" :key="'c' + p.id">
                    <div class="snap-center shrink-0 w-[78vw] max-w-xs card overflow-hidden flex" :class="active === p.id && 'ring-2 ring-coral'">
                        <button @click="openPlace(p)" class="w-24 shrink-0 relative placeholder-cover flex items-center justify-center" :style="`--c1:${style(p).color};--c2:#12161C`">
                            <template x-if="p.media && p.media.cover"><img :src="p.media.cover" alt="" loading="lazy" class="absolute inset-0 w-full h-full object-cover"></template>
                            <template x-if="!(p.media && p.media.cover)"><span class="material-symbols-outlined text-white/80" x-text="style(p).icon"></span></template>
                            <span x-show="p.rating" class="absolute top-1 left-1 rounded-full bg-white/95 text-amber-700 text-[10px] font-bold px-1.5 py-0.5 inline-flex items-center gap-0.5"><span class="material-symbols-outlined filled" style="font-size:11px">star</span><span x-text="p.rating"></span></span>
                        </button>
                        <button @click="openPlace(p)" class="min-w-0 flex-1 p-2.5 text-left">
                            <p class="text-[10px] font-semibold" :style="`color:${style(p).color}`" x-text="p.category ? p.category.name : ''"></p>
                            <p class="text-sm font-semibold leading-snug line-clamp-2" x-text="p.title"></p>
                            <p class="text-[11px] mt-0.5 flex flex-wrap items-center gap-x-2"><span :class="hoursClass(p)" x-text="hoursLabel(p)"></span><span x-show="p.walk_min" class="text-ink-muted" x-text="p.walk_min + ' min ' + data.t.walk"></span></p>
                            <p class="flex items-center gap-1 mt-1 text-[10px] whitespace-nowrap overflow-hidden"><span x-show="p.is_free" class="badge badge-free !py-0.5">{{ __('Gratuit') }}</span><span x-show="p.free_sunday" class="badge badge-event !py-0.5 truncate" x-text="freeSundayBadge()"></span><span x-show="!p.is_free && p.price_level" class="text-ink-muted font-semibold" x-text="'€'.repeat(p.price_level || 0)"></span></p>
                        </button>
                        <button @click.stop="toggleCart(p)" class="self-center mr-2 h-9 w-9 rounded-full flex items-center justify-center shrink-0" :class="inCart(p.id) ? 'bg-ink text-white' : 'bg-paper text-ink-muted'"><span class="material-symbols-outlined" style="font-size:18px" x-text="inCart(p.id) ? 'check' : 'add'"></span></button>
                    </div>
                </template>
                <div x-show="!loading && !places.length" class="shrink-0 w-[78vw] card p-4 text-sm text-ink-muted">{{ __('Aucun lieu ici avec ces filtres. Déplace la carte ou change de filtre.') }}</div>
            </div>
        </div>

        {{-- Fiche rapide lieu / alerte / photo ancienne --}}
        <div x-cloak x-show="selected || selectedAlert || selectedHistory" x-transition.opacity.duration.300ms @click="closeSheet()" class="absolute inset-0 z-[600] bg-ink/35 backdrop-blur-[3px]"></div>
        <div x-cloak x-show="selected || selectedAlert || selectedHistory" class="absolute inset-0 z-[610] flex items-end sm:items-center justify-center p-3 pb-24 sm:p-6 md:pb-6 pointer-events-none">
            <template x-if="selected">
                <div class="card w-full max-w-sm overflow-hidden pointer-events-auto sheet-pop max-h-[calc(100vh-9rem)] sm:max-h-[85vh] overflow-y-auto" @click.stop>
                    <div class="relative h-44 sm:h-52 placeholder-cover flex items-center justify-center" :style="`--c1:${style(selected).color};--c2:#12161C`">
                        <template x-if="selected.media && (selected.media.cover_large || selected.media.cover)"><img :src="selected.media.cover_large || selected.media.cover" :alt="selected.title" class="absolute inset-0 w-full h-full object-cover"></template>
                        <template x-if="!(selected.media && selected.media.cover)"><span class="material-symbols-outlined text-white/80" style="font-size:44px" x-text="style(selected).icon"></span></template>
                        <div class="absolute inset-0 bg-gradient-to-t from-ink-fixed/75 via-transparent to-transparent"></div>
                        <button @click="closeSheet()" class="absolute top-3 right-3 h-9 w-9 rounded-full bg-white/90 text-ink-fixed flex items-center justify-center hover:bg-white" aria-label="{{ __('Fermer') }}"><span class="material-symbols-outlined" style="font-size:18px">close</span></button>
                        <div class="absolute bottom-3 left-4 right-4 text-white">
                            <p class="text-[10px] uppercase tracking-widest opacity-90" x-text="selected.category ? selected.category.name : ''"></p>
                            <p class="font-display text-xl leading-tight" x-text="selected.title"></p>
                        </div>
                    </div>
                    <div class="p-4 space-y-3">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                            <span class="inline-flex items-center gap-1 font-semibold" :class="hoursClass(selected)"><span class="material-symbols-outlined" style="font-size:16px">schedule</span><span x-text="hoursLabel(selected)"></span></span>
                            <span x-show="selected.walk_min" class="inline-flex items-center gap-1 text-ink-muted"><span class="material-symbols-outlined" style="font-size:16px">directions_walk</span><span x-text="selected.walk_min + ' min · ' + formatDistance(selected.distance_m)"></span></span>
                            <span x-show="selected.rating" class="inline-flex items-center gap-1 text-amber-700"><span class="material-symbols-outlined filled" style="font-size:16px">star</span><span x-text="selected.rating"></span></span>
                        </div>
                        <p class="text-sm text-ink-muted flex items-start gap-1.5"><span class="material-symbols-outlined" style="font-size:18px">location_on</span><span x-text="selected.address || @js(__('Adresse non renseignée'))"></span></p>
                        <div class="flex flex-wrap gap-1.5">
                            <span class="badge" :class="selected.is_free ? 'badge-free' : 'badge-paid'" x-text="priceLabel(selected)"></span>
                            <span x-show="selected.free_sunday" class="badge badge-event" x-text="freeSundayBadge()"></span>
                            <span x-show="selected.accessible === true" class="badge badge-free"><span class="material-symbols-outlined" style="font-size:14px">accessible</span>{{ __('Accessible PMR') }}</span>
                            <span x-show="selected.accessible === false" class="badge badge-alert"><span class="material-symbols-outlined" style="font-size:14px">accessible</span>{{ __('Accès difficile') }}</span>
                            <span class="badge badge-paid"><span class="material-symbols-outlined" style="font-size:14px">timer</span><span x-text="'≈ ' + (selected.visit_duration_min || 60) + ' min'"></span></span>
                            <span x-show="selected.alerts" class="badge badge-alert"><span class="material-symbols-outlined" style="font-size:14px">campaign</span><span x-text="selected.alerts"></span></span>
                            <span x-show="selected.event" class="badge badge-event">{{ __('Événement') }}</span>
                        </div>
                        <p x-show="selected.description_short" class="text-sm text-ink-soft" x-text="selected.description_short"></p>
                        <div class="grid grid-cols-2 gap-2 pt-1">
                            <a :href="`${data.placeUrl}/${selected.id}`" class="btn btn-md btn-ink col-span-2"><span class="material-symbols-outlined" style="font-size:18px">open_in_new</span>{{ __('Voir la fiche') }}</a>
                            <button @click="toggleCart(selected)" class="btn btn-md w-full" :class="inCart(selected.id) ? 'btn-teal' : 'btn-soft'"><span class="material-symbols-outlined" style="font-size:18px" x-text="inCart(selected.id) ? 'check' : 'add_location_alt'"></span><span x-text="inCart(selected.id) ? @js(__('Dans le parcours')) : @js(__('Au parcours'))"></span></button>
                            <a :href="gmaps(selected)" target="_blank" rel="noopener" class="btn btn-md btn-soft"><span class="material-symbols-outlined" style="font-size:18px">navigation</span>{{ __('Y aller') }}</a>
                        </div>
                    </div>
                </div>
            </template>
            <template x-if="selectedAlert">
                <div class="card w-full max-w-sm overflow-hidden pointer-events-auto sheet-pop p-5" @click.stop>
                    <div class="flex items-start gap-3">
                        <span class="h-12 w-12 rounded-2xl flex items-center justify-center shrink-0 text-white" :style="`background:${selectedAlert.color}`"><span class="material-symbols-outlined" x-text="selectedAlert.icon"></span></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] font-bold uppercase tracking-widest" :style="`color:${selectedAlert.color}`" x-text="selectedAlert.label"></p>
                            <p class="font-display text-xl leading-tight" x-text="selectedAlert.title"></p>
                            <p x-show="selectedAlert.message" class="text-sm text-ink-soft mt-2" x-text="selectedAlert.message"></p>
                            <p class="text-xs text-ink-muted mt-2"><span x-show="selectedAlert.place" x-text="selectedAlert.place ? selectedAlert.place.title + ' · ' : ''"></span>{{ __('expire dans') }} <span x-text="selectedAlert.expires_in"></span></p>
                        </div>
                        <button @click="closeSheet()" class="h-9 w-9 rounded-full bg-paper text-ink flex items-center justify-center" aria-label="{{ __('Fermer') }}"><span class="material-symbols-outlined" style="font-size:18px">close</span></button>
                    </div>
                    <template x-if="selectedAlert.place"><a :href="`${data.placeUrl}/${selectedAlert.place.id}`" class="btn btn-md btn-ink w-full mt-4">{{ __('Voir le lieu') }}</a></template>
                </div>
            </template>
            <template x-if="selectedHistory">
                <div class="card w-full max-w-md overflow-hidden pointer-events-auto sheet-pop max-h-[calc(100vh-9rem)] sm:max-h-[85vh] overflow-y-auto" @click.stop>
                    <div class="relative bg-ink-fixed">
                        <img :src="selectedHistory.image" :alt="selectedHistory.title" class="w-full max-h-[52vh] object-contain sepia-[.25]">
                        <button @click="closeSheet()" class="absolute top-3 right-3 h-9 w-9 rounded-full bg-white/90 text-ink-fixed flex items-center justify-center" aria-label="{{ __('Fermer') }}"><span class="material-symbols-outlined" style="font-size:18px">close</span></button>
                        <span class="absolute top-3 left-3 rounded-full bg-amber-400 text-ink-fixed text-xs font-bold px-2.5 py-1" x-text="selectedHistory.year || '?'"></span>
                    </div>
                    <div class="p-4">
                        <p class="eyebrow">{{ __('Paris d\'hier') }}</p>
                        <p class="font-display text-lg leading-tight" x-text="selectedHistory.title"></p>
                        <p x-show="selectedHistory.author" class="text-xs text-ink-muted mt-1" x-text="selectedHistory.author"></p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <a :href="selectedHistory.url" target="_blank" rel="noopener" class="btn btn-sm btn-soft"><span class="material-symbols-outlined" style="font-size:16px">open_in_new</span>Wikimedia Commons</a>
                            <button @click="flyTo(selectedHistory.lat, selectedHistory.lng, 17); closeSheet()" class="btn btn-sm btn-ink"><span class="material-symbols-outlined" style="font-size:16px">my_location</span>{{ __('Voir aujourd\'hui') }}</button>
                        </div>
                        <p class="mt-3 text-[10px] text-ink-muted" x-text="selectedHistory.license || ''"></p>
                    </div>
                </div>
            </template>
        </div>

        <x-alert-modal :types="$alertTypes" />
    </div>

    @push('scripts')
    <script>
        function caminoMap(data) {
            const C = window.Camino;
            let map = null, cluster = null, alertLayer = null, historyLayer = null, userMarker = null; // objets Leaflet hors du proxy réactif d'Alpine
            const markers = {};
            const distance = (a, b) => { const x = (b[1] - a[1]) * Math.PI / 180 * Math.cos((a[0] + b[0]) / 2 * Math.PI / 180); const y = (b[0] - a[0]) * Math.PI / 180; return Math.sqrt(x * x + y * y) * 6371000; };
            return {
                data, places: [], alerts: [], history: [], loading: false, loadSeq: 0, selected: null, selectedAlert: null, selectedHistory: null, active: null,
                query: data.query, filter: data.filter, collection: 'all', listMode: 'places', user: null,
                time: { enabled: false, hour: (() => { const d = new Date(); return Math.min(23, Math.max(8, d.getHours() + (d.getMinutes() >= 30 ? 0.5 : 0))); })() },
                layers: { open: false, alerts: true, history: false },
                cart: data.cart.slice(),
                filters: [
                    { key: 'all', label: @js(__('Tous')), icon: 'apps' },
                    { key: 'musees', label: @js(__('Musées')), icon: 'palette', slug: 'musee', color: '#7C3AED' },
                    { key: 'monuments', label: @js(__('Monuments')), icon: 'account_balance', slug: 'monument', color: '#B45309' },
                    { key: 'parcs', label: @js(__('Parcs')), icon: 'park', slug: 'parc-jardin', color: '#15803D' },
                    { key: 'culturels', label: @js(__('Scènes & galeries')), icon: 'theater_comedy', slug: 'lieu-culturel', color: '#0369A1' },
                    { key: 'evenements', label: @js(__('Événements')), icon: 'celebration', slug: 'evenement-culturel', color: '#D97706' },
                    { key: 'street', label: @js(__('Street art')), icon: 'brush', slug: 'street-art', color: '#E11D48' },
                    { key: 'restauration', label: @js(__('Restauration')), icon: 'restaurant', slug: 'restauration', color: '#DB2777' },
                    { key: 'itineraires', label: @js(__('Balades')), icon: 'route', slug: 'itineraire', color: '#0F766E' },
                    { key: 'librairies', label: @js(__('Librairies')), icon: 'menu_book', slug: 'librairies-bibliotheques', color: '#1D4ED8' },
                    { key: 'ateliers', label: @js(__('Ateliers')), icon: 'handyman', slug: 'ateliers-artisans', color: '#9A3412' },
                ],
                get collections() {
                    return [
                        { key: 'all', label: @js(__('Tout')), icon: 'explore' },
                        { key: 'open', label: @js(__('Ouvert maintenant')), icon: 'schedule' },
                        { key: 'near', label: @js(__('À 10 min à pied')), icon: 'directions_walk' },
                        { key: 'free', label: @js(__('Gratuit')), icon: 'loyalty' },
                        { key: 'free_sunday', label: data.firstSunday ? data.t.freeToday : data.t.freeSunday.replace(':date', data.freeSundayLabel), icon: 'confirmation_number' },
                        { key: 'rated', label: @js(__('Bien noté')), icon: 'star' },
                        { key: 'events', label: @js(__('Événements du moment')), icon: 'celebration' },
                    ];
                },
                get timeLabel() { const h = Math.floor(this.time.hour), m = this.time.hour % 1 ? '30' : '00'; return h + 'h' + m; },

                init() {
                    if (!window.L) return;
                    const el = document.getElementById('camino-map');
                    const start = () => {
                        if (map) return;
                        const params = new URLSearchParams(window.location.search);
                        const lat = parseFloat(params.get('lat')) || 48.8566, lng = parseFloat(params.get('lng')) || 2.3522, z = parseInt(params.get('z')) || 13;
                        map = L.map(el, { zoomControl: false, attributionControl: true }).setView([lat, lng], z);
                        C.tileLayer().addTo(map);
                        cluster = L.markerClusterGroup({ maxClusterRadius: 44, disableClusteringAtZoom: 16, spiderfyOnMaxZoom: true, showCoverageOnHover: false, iconCreateFunction: (c) => L.divIcon({ className: 'camino-marker', html: `<div class="camino-cluster">${c.getChildCount()}</div>`, iconSize: [36, 36], iconAnchor: [18, 18] }) });
                        map.addLayer(cluster);
                        alertLayer = L.layerGroup().addTo(map);
                        historyLayer = L.layerGroup().addTo(map);
                        map.on('moveend', C.debounce(() => { this.load(); if (this.layers.history) this.loadHistory(); }, 250));
                        const fix = () => { map.invalidateSize(); this.load(); };
                        if (window.ResizeObserver) new ResizeObserver(C.debounce(fix, 150)).observe(el);
                        window.addEventListener('load', () => setTimeout(fix, 50));
                        window.addEventListener('camino-theme', () => this.render());
                        this.load();
                        if (params.get('locate')) this.locate(true);
                        if (params.get('filtre') === 'free') { this.filter = 'all'; this.collection = 'free'; this.load(); }
                    };
                    if (el.clientHeight > 0) start();
                    else if (window.ResizeObserver) { const ro = new ResizeObserver(() => { if (el.clientHeight > 0) { ro.disconnect(); start(); } }); ro.observe(el); }
                    else setTimeout(start, 300);
                },
                style(p) { return C.categoryStyle(p.category ? p.category.slug : null); },
                center() { if (!map) return {}; const c = map.getCenter(); return { lat: c.lat, lng: c.lng }; },
                zoomIn() { map.zoomIn(); }, zoomOut() { map.zoomOut(); },
                flyTo(lat, lng, z) { map.flyTo([lat, lng], z || Math.max(map.getZoom(), 15), { duration: 0.8 }); },
                formatDistance(m) { if (m === null || m === undefined) return ''; return m >= 1000 ? (m / 1000).toFixed(1).replace('.', ',') + ' km' : Math.round(m / 10) * 10 + ' m'; },

                // ---------------------------------------------------------------- horaires
                hoursLabel(p) {
                    const h = p.hours || {};
                    if (this.time.enabled) return (h.open === true ? data.t.openAt : (h.open === false ? data.t.closedAt : data.t.hoursUnknown + ' ·')) + ' ' + this.timeLabel + (h.open === true && h.closes ? ' · ' + data.t.closesAt + ' ' + h.closes : '');
                    if (h.open === true) return data.t.openNow + (h.closes ? ' · ' + data.t.closesAt + ' ' + h.closes : '');
                    if (h.open === false) return h.opens && h.status === 'open' ? data.t.opensAt + ' ' + h.opens : data.t.closed;
                    return data.t.hoursUnknown;
                },
                hoursClass(p) { const o = (p.hours || {}).open; return o === true ? 'text-emerald-700' : (o === false ? 'text-coral-dark' : 'text-ink-muted'); },
                toggleTime() { this.time.enabled = !this.time.enabled; this.load(); },
                freeSundayBadge() { return data.firstSunday ? data.t.freeToday : data.t.freeSunday.replace(':date', data.freeSundayLabel); },
                priceLabel(p) { return p.is_free ? data.t.free : (p.price_level ? '€'.repeat(p.price_level) : data.t.noPrice); },
                gmaps(p) { return `https://www.google.com/maps/dir/?api=1&destination=${p.lat},${p.lng}&travelmode=walking`; },

                // ---------------------------------------------------------------- filtres
                setFilter(key) { this.filter = key; this.load(); },
                setCollection(key) {
                    this.collection = this.collection === key ? 'all' : key;
                    if (this.collection === 'near' && !this.user) { this.locate(true); return; }
                    this.load();
                },
                countLabel() {
                    const n = this.places.length;
                    if (this.loading && !n) return data.t.loading;
                    if (!n) return data.t.none;
                    return n >= 120 ? data.t.more : `${n} ${n > 1 ? data.t.places : data.t.place}`;
                },
                async locate(recenter = false) {
                    try {
                        const p = await C.locate();
                        this.user = [p.lat, p.lng];
                        if (!userMarker) userMarker = L.marker(this.user, { icon: L.divIcon({ className: 'camino-marker', html: '<div class="camino-user-dot"></div>', iconSize: [18, 18], iconAnchor: [9, 9] }), zIndexOffset: 900 }).addTo(map);
                        else userMarker.setLatLng(this.user);
                        if (recenter) map.setView(this.user, Math.max(map.getZoom(), 15));
                        this.load();
                    } catch (e) { if (recenter) alert(data.t.noPos); if (this.collection === 'near') this.collection = 'all'; }
                },
                async load() {
                    if (!map || map.getSize().x < 50 || map.getSize().y < 50) return;
                    // Plusieurs chargements peuvent se chevaucher (redimensionnement, déplacement) : seule la dernière réponse compte.
                    const seq = ++this.loadSeq;
                    const b = map.getBounds();
                    const bbox = `${b.getSouth()},${b.getWest()},${b.getNorth()},${b.getEast()}`;
                    const params = new URLSearchParams({ bbox, limit: '120' });
                    const f = this.filters.find(x => x.key === this.filter);
                    if (f && f.slug) params.set('category_slugs', f.slug);
                    if (this.filter === 'evenements' || this.collection === 'events') params.set('events', '1');
                    if (this.collection === 'free') params.set('free', '1');
                    if (this.collection === 'free_sunday') params.set('free_sunday', '1');
                    if (this.collection === 'open') params.set('open_now', '1');
                    if (this.collection === 'rated') { params.set('rated', '1'); params.set('sort', 'rating'); }
                    if (this.time.enabled) params.set('at', this.timeLabel.replace('h', ':'));
                    if (this.user) { params.set('lat', this.user[0]); params.set('lng', this.user[1]); if (this.collection === 'near') { params.set('near_m', '800'); params.set('sort', 'distance'); } }
                    if (this.query) params.set('q', this.query);
                    this.loading = true;
                    try {
                        const [rp, ra] = await Promise.all([fetch(`${data.apiPois}?${params}`), fetch(`${data.apiAlerts}?bbox=${bbox}`)]);
                        const jp = await rp.json(); const ja = await ra.json();
                        if (seq !== this.loadSeq) return;
                        this.places = jp.data || []; this.alerts = ja.data || [];
                        if (jp.meta) { data.firstSunday = !!jp.meta.first_sunday; data.freeSundayLabel = jp.meta.next_first_sunday_label || data.freeSundayLabel; }
                        this.render();
                    } catch (e) { console.error(e); } finally { this.loading = false; }
                },

                // ---------------------------------------------------------------- rendu
                markerSize(p) { return p.rating >= 4.5 ? 42 : (p.rating >= 4 ? 38 : 34); },
                render() {
                    cluster.clearLayers(); Object.keys(markers).forEach(k => delete markers[k]);
                    alertLayer.clearLayers();
                    this.places.forEach(p => {
                        if (!p.lat || !p.lng) return;
                        const m = L.marker([p.lat, p.lng], { icon: C.placeIcon(p.category ? p.category.slug : null, { size: this.markerSize(p) }), opacity: (this.time.enabled || this.collection === 'open') && p.hours && p.hours.open === false ? 0.4 : 1 });
                        if (p.media && p.media.cover) m.bindTooltip(`<div class="camino-tip"><img src="${C.escapeHtml(p.media.cover)}" alt=""><span>${C.escapeHtml(p.title)}</span></div>`, { direction: 'top', offset: [0, -18], opacity: 1, className: 'camino-tooltip' });
                        m.on('click', () => this.openPlace(p));
                        markers[p.id] = m;
                        cluster.addLayer(m);
                    });
                    if (this.layers.alerts) this.alerts.forEach(a => {
                        const m = L.marker([a.lat, a.lng], { icon: C.alertIcon(a.color, a.icon), zIndexOffset: 500 });
                        m.on('click', () => this.openAlert(a));
                        alertLayer.addLayer(m);
                    });
                    if (this.active && !markers[this.active]) this.active = null;
                },
                highlight(id) {
                    Object.entries(markers).forEach(([k, m]) => { const el = m.getElement(); if (!el) return; el.style.zIndex = String(k == id ? 1000 : 0); const pin = el.querySelector('.camino-pin'); if (pin) pin.style.transform = k == id ? 'scale(1.25)' : ''; });
                },
                onCarouselScroll() {
                    const el = this.$refs.carousel; if (!el || !this.places.length) return;
                    const idx = Math.round(el.scrollLeft / (el.firstElementChild ? el.firstElementChild.offsetWidth + 12 : 1));
                    const p = this.places[Math.max(0, Math.min(this.places.length - 1, idx))];
                    if (!p || this.active === p.id) return;
                    this.active = p.id; this.highlight(p.id);
                    if (p.lat && p.lng) map.panTo([p.lat, p.lng], { animate: true, duration: 0.5 });
                },
                openPlace(p) {
                    this.selectedAlert = null; this.selectedHistory = null; this.selected = p; this.active = p.id;
                    document.getElementById('camino-map').classList.add('map-3d');
                    map.flyTo([p.lat, p.lng], Math.max(map.getZoom(), 15), { duration: 0.8 });
                },
                openAlert(a) { this.selected = null; this.selectedHistory = null; this.selectedAlert = a; document.getElementById('camino-map').classList.add('map-3d'); map.flyTo([a.lat, a.lng], Math.max(map.getZoom(), 15), { duration: 0.8 }); },
                focusAlert(a) { this.openAlert(a); },
                closeSheet() { this.selected = null; this.selectedAlert = null; this.selectedHistory = null; document.getElementById('camino-map').classList.remove('map-3d'); },

                // ---------------------------------------------------------------- parcours construit depuis la carte
                inCart(id) { return this.cart.includes(id); },
                async toggleCart(p) {
                    const remove = this.inCart(p.id);
                    if (!remove && this.cart.length >= 15) { this.$dispatch('toast', data.t.full); return; }
                    try {
                        const r = await fetch(`${remove ? data.removeUrl : data.addUrl}/${p.id}`, { method: remove ? 'DELETE' : 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': data.csrf, 'X-Requested-With': 'XMLHttpRequest' } });
                        const j = await r.json();
                        if (j && j.ids) this.cart = j.ids;
                        this.$dispatch('toast', remove ? data.t.removed : data.t.added);
                    } catch (e) { console.error(e); }
                },
                async clearCart() {
                    for (const id of this.cart.slice()) { try { await fetch(`${data.removeUrl}/${id}`, { method: 'DELETE', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': data.csrf, 'X-Requested-With': 'XMLHttpRequest' } }); } catch (e) {} }
                    this.cart = [];
                },

                // ---------------------------------------------------------------- Paris d'hier (photos anciennes géolocalisées, Wikimedia Commons)
                async loadHistory() {
                    historyLayer.clearLayers();
                    if (!this.layers.history || !map) return;
                    if (map.getZoom() < 13) { this.$dispatch('toast', @js(__('Zoome un peu pour voir les photos anciennes.'))); return; }
                    const c = map.getCenter();
                    try {
                        const r = await fetch(`${data.apiHistory}?lat=${c.lat.toFixed(4)}&lng=${c.lng.toFixed(4)}&radius=${Math.min(2000, Math.round(map.getBounds().getNorthEast().distanceTo(map.getBounds().getSouthWest()) / 2))}`);
                        const j = await r.json();
                        this.history = j.data || [];
                        if (!this.history.length) this.$dispatch('toast', data.t.historyNone);
                        this.history.forEach(h => {
                            const m = L.marker([h.lat, h.lng], { icon: L.divIcon({ className: 'camino-marker', html: `<div class="camino-pin camino-pin-history" style="background-image:url('${C.escapeHtml(h.thumb)}')"><span>${h.year || ''}</span></div>`, iconSize: [40, 40], iconAnchor: [20, 20] }), zIndexOffset: 300 });
                            m.on('click', () => { this.selected = null; this.selectedAlert = null; this.selectedHistory = h; });
                            historyLayer.addLayer(m);
                        });
                    } catch (e) { console.error(e); }
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
