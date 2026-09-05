@php
    $current = $forecast['current'] ?? null;
    $demoPlaces = $heroPlaces->map(fn ($p) => ['id' => $p->id, 'title' => $p->title, 'category' => $p->category->name ?? '', 'slug' => $p->category->slug ?? null, 'cover' => $p->coverThumb(500), 'address' => $p->address, 'lat' => (float) $p->lat, 'lng' => (float) $p->lng, 'free' => (bool) $p->is_free, 'minutes' => $p->visit_duration_min ?? 60, 'url' => route('places.show', $p)])->values();
    $demoFocus = $demoPlaces->first();
    $demoRoute = $demoPlaces->take(3)->values();
@endphp
<x-app-layout description="CAMINO, le GPS culturel qui rend la ville fun : carte vivante, parcours générés selon ta météo et ton budget, bons plans de la communauté.">

    {{-- ================================================================ HERO --}}
    <section class="relative lg:min-h-[92vh] -mt-[4.6rem] flex items-center overflow-hidden grain">
        <div class="absolute inset-0 -z-10 bg-ink">
            <img src="{{ asset('images/photo_paris.avif') }}" alt="" class="kenburns hero-photo absolute inset-0 h-full w-full object-cover opacity-90" fetchpriority="high">
            <div class="absolute inset-0 bg-gradient-to-r from-ink/85 via-ink/55 to-ink/25"></div>
            <div class="absolute inset-x-0 bottom-0 h-56 bg-gradient-to-t from-paper to-transparent"></div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-28 pb-14 lg:pt-32 lg:pb-24 w-full grid grid-cols-1 lg:grid-cols-[1.05fr_0.95fr] gap-6 lg:gap-10 items-center"
             x-data="{ words: ['autrement.', 'à pied.', 'gratuitement.', 'sous le soleil.', 'entre amis.', 'sans plan.'], i: 0, flip: false }"
             x-init="setInterval(() => { i = (i + 1) % words.length; flip = !flip; }, 2400)">
            <div class="text-white animate-fade-up min-w-0">
                <div class="flex flex-wrap items-center gap-2 mb-5">
                    <span class="inline-flex items-center gap-2 rounded-full bg-white/10 backdrop-blur px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.16em] border border-white/15">
                        <span class="relative flex h-2 w-2"><span class="absolute inline-flex h-full w-full rounded-full bg-coral opacity-75 animate-ping"></span><span class="relative inline-flex h-2 w-2 rounded-full bg-coral"></span></span>
                        En direct · Île-de-France
                    </span>
                    @if($current)
                        <button type="button" @click="$dispatch('open-weather')" class="inline-flex items-center gap-1.5 rounded-full bg-white/10 backdrop-blur px-3 py-1.5 text-xs border border-white/15 hover:bg-white/20 transition">
                            <span class="material-symbols-outlined filled text-sun" style="font-size:16px">{{ $current['icon'] }}</span>{{ round($current['temp']) }}° · {{ $advice['title'] }}
                        </button>
                    @endif
                </div>

                <h1 class="display text-[44px] sm:text-6xl lg:text-[76px] leading-[1.02]">
                    Explore la ville<br>
                    <span class="text-coral italic" :class="flip ? 'word-a' : 'word-b'" x-text="words[i]">autrement.</span>
                </h1>
                <p class="mt-5 text-base sm:text-xl text-white/80 max-w-xl">
                    {{ number_format($stats['places'], 0, ',', ' ') }} lieux qui valent le détour, une carte qui bouge en temps réel, un parcours calculé en 10 secondes selon ton temps, ton budget et la météo.
                </p>

                <div class="mt-7 flex flex-wrap gap-3">
                    <a href="{{ route('map.index') }}" class="btn btn-lg btn-primary"><span class="material-symbols-outlined">map</span>Ouvrir la carte</a>
                    <a href="#generateur" class="btn btn-lg bg-white/10 text-white border border-white/20 backdrop-blur hover:bg-white/20"><span class="material-symbols-outlined">auto_awesome</span>Générer un parcours</a>
                </div>

                <form action="{{ route('map.index') }}" method="GET" class="mt-5 flex items-center gap-2 rounded-full bg-white/95 p-1.5 pl-4 max-w-lg shadow-float">
                    <span class="material-symbols-outlined text-ink-muted">search</span>
                    <input type="search" name="q" placeholder="Un lieu, un quartier, une envie…" class="flex-1 min-w-0 border-0 bg-transparent focus:ring-0 text-sm text-ink placeholder:text-ink-muted/70" autocomplete="off">
                    <button type="submit" class="btn btn-md btn-ink">Explorer</button>
                </form>

                <div class="mt-4 flex gap-2 overflow-x-auto hide-scrollbar -mx-4 px-4 sm:mx-0 sm:px-0 sm:flex-wrap">
                    @foreach([['musees', 'palette', 'Musées'], ['monuments', 'account_balance', 'Monuments'], ['parcs', 'park', 'Parcs'], ['free', 'loyalty', 'Gratuit'], ['evenements', 'celebration', 'Événements']] as [$f, $icon, $label])
                        <a href="{{ route('map.index', ['filtre' => $f]) }}" class="chip shrink-0 bg-white/10 text-white border-white/15 hover:bg-white/20 hover:text-white backdrop-blur"><span class="material-symbols-outlined" style="font-size:16px">{{ $icon }}</span>{{ $label }}</a>
                    @endforeach
                </div>
            </div>

            {{-- Démo produit : un vrai enchaînement d'écrans CAMINO --}}
            <div class="relative min-w-0 flex items-center gap-5 pl-4 sm:pl-8 lg:block lg:pl-10 lg:pr-0" x-data="heroDemo()">
                <div class="hidden lg:block absolute h-80 w-80 rounded-full bg-coral/25 blur-3xl right-10 top-10"></div>
                <div class="hidden lg:block absolute h-56 w-56 rounded-full bg-teal/25 blur-3xl right-40 bottom-0"></div>

                <div class="relative shrink-0 lg:mx-auto lg:w-fit">
                    <div class="phone phone-demo">
                        <div class="phone-screen">
                            {{-- Écran 0 : carte vivante --}}
                            <div class="demo-screen" :class="cls(0)">
                                <div x-ref="map" class="absolute inset-0 hero-map"></div>
                                <div class="absolute top-11 lg:top-12 inset-x-2.5 z-[400] flex items-center gap-2 rounded-full bg-white/95 px-3 py-1.5 lg:py-2 shadow-card text-[10px] lg:text-[11px] text-ink-muted">
                                    <span class="material-symbols-outlined shrink-0" style="font-size:15px">search</span><span class="truncate">Autour de moi</span>
                                    <span class="ml-auto h-5 w-5 lg:h-6 lg:w-6 rounded-full bg-coral text-white flex items-center justify-center"><span class="material-symbols-outlined" style="font-size:13px">my_location</span></span>
                                </div>
                                <template x-if="place">
                                    <div class="absolute bottom-2.5 inset-x-2.5 z-[400] card p-2 flex gap-2 items-center demo-item" :key="place.id">
                                        <div class="h-11 w-11 lg:h-14 lg:w-14 rounded-xl overflow-hidden shrink-0 placeholder-cover"><img :src="place.cover" alt="" class="h-full w-full object-cover"></div>
                                        <div class="min-w-0">
                                            <p class="text-[9px] lg:text-[10px] font-bold uppercase tracking-wider text-teal" x-text="place.category"></p>
                                            <p class="text-[11px] lg:text-xs font-semibold leading-snug line-clamp-2 text-ink" x-text="place.title"></p>
                                            <p class="text-[9px] lg:text-[10px] text-ink-muted" x-text="place.free ? 'Gratuit · à 6 min à pied' : 'À 6 min à pied'"></p>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            {{-- Écran 1 : fiche lieu --}}
                            <div class="demo-screen bg-paper" :class="cls(1)">
                                @if($demoFocus)
                                    <div class="relative h-[46%] overflow-hidden">
                                        <img src="{{ $demoFocus['cover'] }}" alt="" class="absolute inset-0 h-full w-full object-cover">
                                        <div class="absolute inset-0 bg-gradient-to-t from-ink/80 to-transparent"></div>
                                        <div class="absolute bottom-3 left-3 right-3 text-white demo-item">
                                            <p class="text-[9px] uppercase tracking-widest opacity-80">{{ $demoFocus['category'] }}</p>
                                            <p class="font-display text-base lg:text-lg leading-tight line-clamp-2">{{ $demoFocus['title'] }}</p>
                                        </div>
                                    </div>
                                    <div class="p-3 space-y-2 text-ink">
                                        <div class="flex gap-1.5 demo-item" style="animation-delay:.15s">
                                            <span class="badge {{ $demoFocus['free'] ? 'badge-free' : 'badge-paid' }} !text-[9px]">{{ $demoFocus['free'] ? 'Gratuit' : 'Payant' }}</span>
                                            <span class="badge badge-paid !text-[9px]">≈ {{ $demoFocus['minutes'] }} min</span>
                                            <span class="badge bg-amber-50 text-amber-700 !text-[9px]"><span class="material-symbols-outlined filled" style="font-size:11px">star</span>4,6</span>
                                        </div>
                                        <p class="text-[10px] text-ink-muted line-clamp-2 demo-item" style="animation-delay:.25s">{{ $demoFocus['address'] ?? 'Paris' }}</p>
                                        <div class="grid grid-cols-2 gap-1.5 demo-item" style="animation-delay:.35s">
                                            <span class="btn btn-sm btn-primary !text-[10px] !px-2"><span class="material-symbols-outlined" style="font-size:13px">favorite</span>Favori</span>
                                            <span class="btn btn-sm btn-ink !text-[10px] !px-2"><span class="material-symbols-outlined" style="font-size:13px">add_location_alt</span>Parcours</span>
                                        </div>
                                        <div class="rounded-xl bg-sun-soft p-2 text-[10px] demo-item" style="animation-delay:.5s"><span class="font-semibold text-amber-800">Léa</span> · « Concert gratuit dans la cour ce soir » · il y a 12 min</div>
                                    </div>
                                @endif
                            </div>

                            {{-- Écran 2 : parcours généré --}}
                            <div class="demo-screen bg-paper p-3 pt-11 lg:pt-12 text-ink" :class="cls(2)">
                                <p class="eyebrow !text-[9px] demo-item">Parcours généré</p>
                                <p class="font-display text-base lg:text-lg leading-tight demo-item" style="animation-delay:.1s">Balade musées & monuments</p>
                                <p class="text-[10px] text-ink-muted demo-item" style="animation-delay:.15s">3 h 10 · 4,2 km · à pied · {{ $current ? mb_strtolower($current['label']) : 'météo ok' }}</p>
                                <div class="mt-2 h-16 lg:h-20 rounded-2xl overflow-hidden relative" style="background: linear-gradient(135deg,#E9F5EA,#FCE8E1)">
                                    <svg class="absolute inset-0 w-full h-full" viewBox="0 0 240 80" fill="none"><path class="route-draw" d="M20 60 C 60 10, 110 70, 150 30 S 210 20, 225 40" stroke="#FF5A3C" stroke-width="3.5" stroke-linecap="round"/><circle cx="20" cy="60" r="5" fill="#FF5A3C"/><circle cx="150" cy="30" r="5" fill="#12161C"/><circle cx="225" cy="40" r="5" fill="#12161C"/></svg>
                                </div>
                                <div class="mt-2 space-y-1.5">
                                    @foreach($demoRoute as $i => $p)
                                        <div class="flex items-center gap-2 rounded-xl bg-white p-1.5 shadow-card demo-item" style="animation-delay:{{ 0.3 + $i * 0.2 }}s">
                                            <span class="h-6 w-6 rounded-full bg-ink text-white text-[10px] font-bold flex items-center justify-center">{{ $i + 1 }}</span>
                                            <div class="min-w-0 flex-1"><p class="text-[10px] font-semibold leading-tight truncate">{{ $p['title'] }}</p><p class="text-[9px] text-ink-muted">{{ $p['category'] }} · {{ $p['minutes'] }} min</p></div>
                                            <span class="text-[9px] font-semibold text-ink-muted">{{ ['10:15', '12:05', '13:10'][$i] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                                <span class="mt-2 btn btn-sm btn-primary w-full !text-[10px] demo-item" style="animation-delay:.9s"><span class="material-symbols-outlined" style="font-size:13px">navigation</span>Lancer dans Google Maps</span>
                            </div>

                            {{-- Écran 3 : espace perso --}}
                            <div class="demo-screen bg-ink text-white p-3 pt-11 lg:pt-12" :class="cls(3)">
                                <div class="flex items-center gap-2 demo-item">
                                    <span class="h-9 w-9 rounded-2xl bg-teal flex items-center justify-center font-display text-lg">L</span>
                                    <div><p class="text-[9px] uppercase tracking-widest text-coral font-bold">Niveau 3 · Explorateur</p><p class="font-display text-base leading-tight">Bonsoir Léa</p></div>
                                </div>
                                <div class="mt-2 rounded-2xl bg-white/10 p-2 demo-item" style="animation-delay:.15s">
                                    <div class="flex justify-between text-[9px] text-white/70"><span>92 pts</span><span>Guide local à 180</span></div>
                                    <div class="mt-1 h-1.5 rounded-full bg-white/15 overflow-hidden"><div class="h-full w-1/2 rounded-full bg-gradient-to-r from-coral to-sun"></div></div>
                                </div>
                                <div class="mt-2 grid grid-cols-3 gap-1.5 text-center demo-item" style="animation-delay:.3s">
                                    @foreach([['route', '12', 'parcours'], ['directions_walk', '38', 'km'], ['favorite', '21', 'favoris']] as [$ic, $v, $l])
                                        <div class="rounded-xl bg-white/10 p-1.5"><span class="material-symbols-outlined text-sun" style="font-size:14px">{{ $ic }}</span><p class="text-sm font-semibold leading-tight">{{ $v }}</p><p class="text-[8px] text-white/60 uppercase">{{ $l }}</p></div>
                                    @endforeach
                                </div>
                                <p class="mt-2 text-[9px] uppercase tracking-widest text-white/60 demo-item" style="animation-delay:.4s">Pour toi ce week-end</p>
                                <div class="mt-1 space-y-1.5">
                                    @foreach($demoPlaces->slice(1, 2)->values() as $i => $p)
                                        <div class="flex items-center gap-2 rounded-xl bg-white/10 p-1.5 demo-item" style="animation-delay:{{ 0.5 + $i * 0.15 }}s">
                                            <div class="h-8 w-8 rounded-lg overflow-hidden shrink-0"><img src="{{ $p['cover'] }}" alt="" class="h-full w-full object-cover"></div>
                                            <div class="min-w-0"><p class="text-[10px] font-semibold truncate">{{ $p['title'] }}</p><p class="text-[9px] text-white/60">{{ $p['category'] }}{{ $p['free'] ? ' · gratuit' : '' }}</p></div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Indicateur d'étapes (desktop) --}}
                    <div class="hidden lg:flex justify-center gap-2 mt-6">
                        <template x-for="(s, idx) in steps" :key="s">
                            <button @click="go(idx)" class="px-3 py-1.5 rounded-full text-[11px] font-semibold transition" :class="idx === screen ? 'bg-white text-ink' : 'bg-white/10 text-white/70 hover:bg-white/20'" x-text="s"></button>
                        </template>
                    </div>
                </div>

                {{-- Légende mobile, à côté du petit téléphone --}}
                <div class="lg:hidden flex-1 min-w-0 text-white">
                    <p class="text-[10px] uppercase tracking-[0.16em] text-white/60 font-bold">L'app en action</p>
                    <p class="font-display text-xl leading-tight mt-1" x-text="captions[screen]"></p>
                    <div class="mt-3 flex gap-1.5">
                        <template x-for="(s, idx) in steps" :key="'m' + s">
                            <span class="h-1.5 rounded-full transition-all duration-500" :class="idx === screen ? 'w-6 bg-coral' : 'w-2 bg-white/30'"></span>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ================================================================ Collections --}}
    <section id="detour" class="mt-4 sm:mt-12 overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 reveal">
            <x-section-heading eyebrow="Explorer par envie" title="Des collections qui changent avec la météo" :subtitle="$advice['indoor'] ? 'Il pleut, on te propose du couvert en premier.' : 'Beau temps : on commence dehors.'" :href="route('map.index')" link-label="Toute la carte" />
        </div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6">
            <div class="flex gap-4 overflow-x-auto snap-x snap-mandatory hide-scrollbar -mx-4 px-4 sm:mx-0 sm:px-0 pb-4 reveal">
                @foreach($collections as $c)
                    <a href="{{ route('map.index', ['filtre' => $c['filter']]) }}" class="group snap-start shrink-0 w-60 sm:w-72 card overflow-hidden card-hover">
                        <div class="mosaic h-44 sm:h-52 bg-paper-deep">
                            @foreach($c['places'] as $p)
                                <img src="{{ $p->coverThumb(500) }}" alt="" loading="lazy" class="transition-transform duration-700 group-hover:scale-105">
                            @endforeach
                        </div>
                        <div class="p-4 flex items-start gap-3">
                            <span class="h-10 w-10 rounded-2xl flex items-center justify-center shrink-0" style="background: {{ $c['color'] }}1A; color: {{ $c['color'] }}"><span class="material-symbols-outlined">{{ $c['icon'] }}</span></span>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold leading-snug">{{ $c['title'] }}</p>
                                <p class="text-xs text-ink-muted mt-0.5">{{ $c['subtitle'] }}</p>
                            </div>
                            <span class="material-symbols-outlined text-ink-muted group-hover:text-coral group-hover:translate-x-0.5 transition">arrow_forward</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ================================================================ Coups de cœur --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 mt-10 sm:mt-16">
        <div class="reveal"><x-section-heading eyebrow="Coups de cœur" title="Six adresses pour commencer" subtitle="Bien notées, avec photo et description. Ouvre la fiche, ajoute-les à ton parcours." /></div>
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
            @foreach($favorites as $i => $place)
                <div class="reveal reveal-delay-{{ $i % 3 }}"><x-place-card :place="$place" /></div>
            @endforeach
        </div>
    </section>

    {{-- ================================================================ Chiffres --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 mt-12 sm:mt-20 reveal">
        <div class="card p-2 grid grid-cols-2 md:grid-cols-5 divide-y md:divide-y-0 md:divide-x divide-ink/5">
            @foreach([
                ['museums', 'Musées', 'palette', '#7C3AED'],
                ['monuments', 'Monuments', 'account_balance', '#B45309'],
                ['parks', 'Parcs & jardins', 'park', '#15803D'],
                ['free', 'Lieux gratuits', 'loyalty', '#0F8B8D'],
                ['events', 'Événements à venir', 'celebration', '#F59E0B'],
            ] as [$key, $label, $icon, $color])
                <div class="flex items-center gap-3 px-4 py-3">
                    <span class="h-10 w-10 rounded-2xl flex items-center justify-center shrink-0" style="background: {{ $color }}1A; color: {{ $color }}"><span class="material-symbols-outlined">{{ $icon }}</span></span>
                    <div class="leading-tight">
                        <p class="text-xl font-semibold tabular-nums" data-count="{{ $stats[$key] }}">0</p>
                        <p class="text-xs text-ink-muted">{{ $label }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ================================================================ Générateur express --}}
    <section id="generateur" class="max-w-7xl mx-auto px-4 sm:px-6 mt-12 sm:mt-24 scroll-mt-24 reveal">
        <div class="rounded-4xl bg-ink text-white p-6 sm:p-10 grid grid-cols-1 lg:grid-cols-[1fr_1.2fr] gap-8 items-center relative overflow-hidden">
            <div class="absolute -right-20 -top-20 h-72 w-72 rounded-full bg-coral/30 blur-3xl"></div>
            <div class="absolute -left-24 -bottom-24 h-72 w-72 rounded-full bg-teal/30 blur-3xl"></div>
            <div class="relative">
                <p class="eyebrow">Le truc en plus</p>
                <h2 class="display text-3xl sm:text-4xl mt-2">Un parcours sur mesure en 10 secondes.</h2>
                <p class="mt-4 text-white/75">Temps dispo, budget, à pied ou à vélo, tes envies : l'algorithme choisit les lieux, optimise l'ordre et calcule les vrais temps de trajet dans les rues. S'il pleut, il te met à l'abri.</p>
                <ul class="mt-5 space-y-2 text-sm text-white/80">
                    <li class="flex items-center gap-2"><span class="material-symbols-outlined text-sun" style="font-size:18px">schedule</span>Heure d'arrivée à chaque étape</li>
                    <li class="flex items-center gap-2"><span class="material-symbols-outlined text-sun" style="font-size:18px">route</span>Tracé réel, export vers Google Maps</li>
                    <li class="flex items-center gap-2"><span class="material-symbols-outlined text-sun" style="font-size:18px">auto_awesome</span>Ça apprend de tes favoris</li>
                </ul>
            </div>
            <form method="POST" action="{{ route('itineraries.store') }}" class="relative card p-5 sm:p-6 text-ink space-y-4" x-data="{ mode: 'walk' }">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="hero-duration">Temps disponible</label>
                        <select id="hero-duration" name="duration_minutes" class="field">
                            @foreach([90 => '1 h 30', 120 => '2 h', 180 => '3 h', 240 => 'Une demi-journée', 360 => 'La journée'] as $v => $l)
                                <option value="{{ $v }}" @selected($v === 180)>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="hero-budget">Budget</label>
                        <select id="hero-budget" name="budget_eur" class="field">
                            <option value="0">Gratuit uniquement</option>
                            <option value="15">Jusqu'à 15 €</option>
                            <option value="40" selected>Jusqu'à 40 €</option>
                            <option value="">Sans limite</option>
                        </select>
                    </div>
                </div>
                <div>
                    <p class="label">Mobilité</p>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach(['walk' => ['directions_walk', 'À pied'], 'bike' => ['directions_bike', 'À vélo']] as $m => [$icon, $label])
                            <label class="cursor-pointer">
                                <input type="radio" name="mode" value="{{ $m }}" class="peer sr-only" x-model="mode">
                                <span class="flex items-center justify-center gap-2 rounded-2xl border border-ink/10 px-3 py-2.5 text-sm font-medium peer-checked:bg-ink peer-checked:text-white peer-checked:border-ink transition"><span class="material-symbols-outlined" style="font-size:18px">{{ $icon }}</span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <p class="label">Envies</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach(['musee' => 'Musées', 'monument' => 'Monuments', 'parc-jardin' => 'Parcs', 'lieu-culturel' => 'Scènes & galeries', 'street-art' => 'Street art', 'evenement-culturel' => 'Événements'] as $slug => $label)
                            <label class="cursor-pointer">
                                <input type="checkbox" name="interests[]" value="{{ $slug }}" class="peer sr-only" @checked(in_array($slug, ['musee', 'monument']))>
                                <span class="chip peer-checked:bg-ink peer-checked:text-white peer-checked:border-ink">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <button type="submit" class="btn btn-lg btn-primary w-full"><span class="material-symbols-outlined">auto_awesome</span>Générer mon parcours</button>
                <p class="text-center text-[11px] text-ink-muted">Départ : centre de Paris. Tu pourras utiliser ta position sur la page suivante.</p>
            </form>
        </div>
    </section>

    {{-- ================================================================ L'app dans ta poche --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 mt-12 sm:mt-24 reveal">
        <div class="rounded-4xl bg-ink text-white relative overflow-hidden grid grid-cols-1 lg:grid-cols-[1fr_1.05fr] items-center">
            <div class="absolute -left-24 -top-24 h-80 w-80 rounded-full bg-coral/30 blur-3xl"></div>
            <div class="absolute -right-10 -bottom-24 h-96 w-96 rounded-full bg-teal/25 blur-3xl"></div>
            <div class="absolute inset-0 opacity-[0.12]" style="background-image: radial-gradient(circle at 1px 1px, #fff 1px, transparent 0); background-size: 26px 26px;"></div>

            <div class="relative p-6 sm:p-10 order-2 lg:order-1">
                <p class="eyebrow">Bientôt dans ta poche</p>
                <h2 class="display text-3xl sm:text-5xl mt-2">Sur iPhone et Android.</h2>
                <p class="mt-4 text-white/75">Les apps natives arrivent. En attendant, CAMINO s'installe déjà depuis ton navigateur : icône sur l'écran d'accueil, plein écran, carte, alertes et parcours au même endroit.</p>
                <div class="mt-6 flex flex-wrap items-center gap-2.5">
                    <span class="btn btn-md bg-white text-ink cursor-default"><span class="material-symbols-outlined" style="font-size:20px">ios</span>App Store <span class="ml-1 rounded-full bg-sun text-ink text-[10px] px-2 py-0.5">bientôt</span></span>
                    <span class="btn btn-md bg-white text-ink cursor-default"><span class="material-symbols-outlined" style="font-size:20px">android</span>Google Play <span class="ml-1 rounded-full bg-sun text-ink text-[10px] px-2 py-0.5">bientôt</span></span>
                    <button type="button" data-install class="hidden btn btn-md btn-primary"><span class="material-symbols-outlined" style="font-size:18px">add_to_home_screen</span>Installer CAMINO</button>
                </div>
                <p data-ios-tip class="hidden mt-3 text-sm text-white/70"><span class="material-symbols-outlined align-middle text-sun" style="font-size:18px">ios_share</span> Sur iPhone : bouton Partager, puis « Sur l'écran d'accueil ».</p>
                <ul class="mt-6 grid grid-cols-3 gap-2 text-xs sm:text-sm">
                    @foreach([['my_location', 'Autour de toi'], ['campaign', 'Alertes live'], ['auto_awesome', 'Parcours réels']] as [$i, $t])
                        <li class="rounded-2xl bg-white/10 p-3 text-center"><span class="material-symbols-outlined text-sun">{{ $i }}</span><p class="font-semibold mt-1 leading-tight">{{ $t }}</p></li>
                    @endforeach
                </ul>
                <div class="hidden sm:flex mt-6 items-center gap-4 rounded-3xl bg-white/10 p-3 pr-5">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=140x140&margin=3&color=12161C&bgcolor=FFFFFF&data={{ urlencode(route('map.index', ['source' => 'qr'])) }}" alt="QR code vers la carte CAMINO" width="72" height="72" class="rounded-xl h-[72px] w-[72px] bg-white p-1 shrink-0" loading="lazy">
                    <p class="text-sm text-white/80"><span class="font-semibold text-white">Scanne pour l'ouvrir sur ton téléphone,</span> puis « Ajouter à l'écran d'accueil ». Ça marche déjà, sans passer par un store.</p>
                </div>
            </div>

            {{-- Deux téléphones : iPhone (carte) et Samsung (parcours) --}}
            <div class="relative order-1 lg:order-2 phone-duo">
                <div class="phone phone-duo-ios">
                    <div class="phone-screen">
                        <div class="absolute inset-0 overflow-hidden">
                            <div class="absolute -left-36 -top-16 w-[512px] h-[512px] grid grid-cols-2 opacity-95">
                                @foreach([[16596, 11270], [16597, 11270], [16596, 11271], [16597, 11271]] as [$tx, $ty])
                                    <img src="https://a.tile.openstreetmap.fr/osmfr/15/{{ $tx }}/{{ $ty }}.png" alt="" width="256" height="256" loading="lazy" class="block w-64 h-64">
                                @endforeach
                            </div>
                            @foreach([['musee', 'palette', '#7C3AED', '34%', '38%'], ['monument', 'account_balance', '#B45309', '62%', '30%'], ['parc-jardin', 'park', '#15803D', '48%', '56%'], ['lieu-culturel', 'theater_comedy', '#0369A1', '24%', '66%']] as [$slug, $icon, $color, $left, $top])
                                <span class="absolute h-7 w-7 rounded-full border-2 border-white shadow-card flex items-center justify-center text-white" style="left: {{ $left }}; top: {{ $top }}; background: {{ $color }}"><span class="material-symbols-outlined" style="font-size:15px">{{ $icon }}</span></span>
                            @endforeach
                            <span class="absolute left-[44%] top-[46%] h-4 w-4 rounded-full bg-coral border-2 border-white shadow-card"><span class="absolute inset-0 rounded-full bg-coral animate-ping opacity-60"></span></span>
                        </div>
                        <div class="absolute top-10 inset-x-2.5 flex items-center gap-2 rounded-full bg-white/95 px-3 py-1.5 shadow-card text-[10px] text-ink-muted"><span class="material-symbols-outlined shrink-0" style="font-size:14px">search</span><span class="truncate">Autour de moi</span><span class="ml-auto h-5 w-5 rounded-full bg-coral text-white flex items-center justify-center shrink-0"><span class="material-symbols-outlined" style="font-size:12px">my_location</span></span></div>
                        @if($demoFocus)
                            <div class="absolute bottom-2.5 inset-x-2.5 card p-2 flex gap-2 items-center text-ink">
                                <div class="h-11 w-11 rounded-xl overflow-hidden shrink-0 placeholder-cover"><img src="{{ $demoFocus['cover'] }}" alt="" class="h-full w-full object-cover" loading="lazy"></div>
                                <div class="min-w-0"><p class="text-[9px] font-bold uppercase tracking-wider text-teal">{{ $demoFocus['category'] }}</p><p class="text-[11px] font-semibold leading-snug line-clamp-2">{{ $demoFocus['title'] }}</p><p class="text-[9px] text-ink-muted">{{ $demoFocus['free'] ? 'Gratuit · ' : '' }}à 6 min à pied</p></div>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="phone phone-android phone-duo-android">
                    <div class="phone-screen bg-paper p-3 pt-9 text-ink">
                        <p class="eyebrow !text-[9px]">Parcours généré</p>
                        <p class="font-display text-base leading-tight">Balade du week-end</p>
                        <p class="text-[10px] text-ink-muted">3 h 10 · 4,2 km · à pied</p>
                        <div class="mt-2 h-14 rounded-2xl overflow-hidden relative" style="background: linear-gradient(135deg,#E9F5EA,#FCE8E1)">
                            <svg class="absolute inset-0 w-full h-full" viewBox="0 0 240 80" fill="none"><path d="M20 60 C 60 10, 110 70, 150 30 S 210 20, 225 40" stroke="#FF5A3C" stroke-width="3.5" stroke-linecap="round" stroke-dasharray="6 5"/><circle cx="20" cy="60" r="5" fill="#FF5A3C"/><circle cx="150" cy="30" r="5" fill="#12161C"/><circle cx="225" cy="40" r="5" fill="#12161C"/></svg>
                        </div>
                        <div class="mt-2 space-y-1.5">
                            @foreach($demoRoute as $i => $p)
                                <div class="flex items-center gap-2 rounded-xl bg-white p-1.5 shadow-card">
                                    <span class="h-6 w-6 rounded-full bg-ink text-white text-[10px] font-bold flex items-center justify-center shrink-0">{{ $i + 1 }}</span>
                                    <div class="min-w-0 flex-1"><p class="text-[10px] font-semibold leading-tight truncate">{{ $p['title'] }}</p><p class="text-[9px] text-ink-muted truncate">{{ $p['category'] }} · {{ $p['minutes'] }} min</p></div>
                                    <span class="text-[9px] font-semibold text-ink-muted shrink-0">{{ ['10:15', '12:05', '13:10'][$i] }}</span>
                                </div>
                            @endforeach
                        </div>
                        <span class="mt-2 btn btn-sm btn-primary w-full !text-[10px]"><span class="material-symbols-outlined" style="font-size:13px">navigation</span>Lancer</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ================================================================ Événements + alertes --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 mt-12 sm:mt-24 grid grid-cols-1 lg:grid-cols-[1.2fr_0.8fr] gap-6">
        <div class="reveal">
            <x-section-heading eyebrow="En ce moment" title="Événements à venir" :href="route('map.index', ['filtre' => 'evenements'])" />
            <div class="space-y-3">
                @forelse($events as $event)
                    <a href="{{ route('places.show', $event) }}" class="card card-hover p-3 flex gap-4 items-center">
                        <div class="w-24 h-20 rounded-2xl overflow-hidden shrink-0"><x-cover :place="$event" class="h-full" /></div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-semibold text-amber-700">
                                @if($event->event_start_at && $event->event_end_at && !$event->event_start_at->isSameDay($event->event_end_at))
                                    Du {{ $event->event_start_at->translatedFormat('j M') }} au {{ $event->event_end_at->translatedFormat('j M Y') }}
                                @else
                                    Le {{ ($event->event_start_at ?? $event->event_end_at)->translatedFormat('j F Y') }}
                                @endif
                            </p>
                            <p class="font-semibold leading-snug line-clamp-2">{{ $event->title }}</p>
                            <p class="text-xs text-ink-muted line-clamp-1">{{ $event->address }}</p>
                        </div>
                        <span class="material-symbols-outlined text-ink-muted hidden sm:block">arrow_forward</span>
                    </a>
                @empty
                    <div class="card p-6 text-sm text-ink-muted">Aucun événement daté pour l'instant.</div>
                @endforelse
            </div>
        </div>
        <div class="reveal reveal-delay-1">
            <x-section-heading eyebrow="Communauté" title="Alertes en direct" />
            <div class="card p-2 space-y-1">
                @forelse($alerts as $alert)
                    <div class="flex gap-3 p-3 rounded-2xl hover:bg-paper">
                        <span class="h-9 w-9 rounded-full flex items-center justify-center shrink-0" style="background: {{ $alert->type_color }}22; color: {{ $alert->type_color }}"><span class="material-symbols-outlined" style="font-size:18px">{{ $alert->type_icon }}</span></span>
                        <div class="min-w-0 text-sm">
                            <p class="font-semibold leading-snug">{{ $alert->title }}</p>
                            <p class="text-xs text-ink-muted">{{ $alert->type_label }}{{ $alert->place ? ' · ' . $alert->place->title : '' }} · expire {{ $alert->expires_at->diffForHumans() }}</p>
                        </div>
                    </div>
                @empty
                    <div class="p-5 text-sm text-ink-muted">
                        <p>Calme plat pour l'instant.</p>
                        <p class="mt-1">Sur la carte, signale un concert gratuit, une file d'attente ou une fermeture : tout le monde le voit, comme sur Waze.</p>
                    </div>
                @endforelse
                <a href="{{ route('map.index') }}" class="btn btn-sm btn-soft w-full mt-1"><span class="material-symbols-outlined" style="font-size:16px">campaign</span>Signaler quelque chose</a>
            </div>
        </div>
    </section>

    {{-- ================================================================ Comment ça marche --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 mt-12 sm:mt-24">
        <div class="reveal"><x-section-heading eyebrow="Simple comme bonjour" title="Comment ça marche" /></div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach([
                ['explore', 'Explore', 'Filtre par envie, budget ou distance. Les événements et alertes de la communauté apparaissent en temps réel.'],
                ['auto_awesome', 'Génère', 'Ton temps, ton budget, tes envies : CAMINO optimise l\'ordre et calcule les trajets réels, météo comprise.'],
                ['groups', 'Partage', 'Une photo, un avis, un concert gratuit à signaler, un lieu que personne ne connaît : enrichis la carte.'],
            ] as $i => [$icon, $title, $text])
                <div class="card p-6 reveal reveal-delay-{{ $i }}">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="h-11 w-11 rounded-2xl bg-coral-soft text-coral flex items-center justify-center"><span class="material-symbols-outlined">{{ $icon }}</span></span>
                        <span class="font-display text-3xl text-ink/20">0{{ $i + 1 }}</span>
                    </div>
                    <p class="font-semibold text-lg">{{ $title }}</p>
                    <p class="mt-2 text-sm text-ink-muted">{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ================================================================ CTA --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 mt-12 sm:mt-24 reveal">
        <div class="rounded-4xl bg-coral text-white p-8 sm:p-12 text-center relative overflow-hidden">
            <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle at 1px 1px, #fff 1px, transparent 0); background-size: 22px 22px;"></div>
            <div class="relative max-w-2xl mx-auto">
                <h2 class="display text-3xl sm:text-5xl">Ton profil culturel, qui apprend de toi.</h2>
                <p class="mt-4 text-white/85">Favoris, avis, parcours : plus tu joues avec CAMINO, plus les recommandations te ressemblent. Gratuit, sans pub, sans prise de tête.</p>
                <div class="mt-6 flex flex-wrap justify-center gap-3">
                    <a href="{{ route('register') }}" class="btn btn-lg bg-white text-ink hover:-translate-y-0.5">Créer mon compte</a>
                    <a href="{{ route('map.index') }}" class="btn btn-lg bg-ink/20 text-white hover:bg-ink/30">Continuer sans compte</a>
                </div>
            </div>
        </div>
    </section>

    @push('scripts')
    <script>
        function heroDemo() {
            const C = window.Camino;
            let map = null;
            return {
                places: @js($demoPlaces), place: null, screen: 0, prev: -1, idx: 0,
                steps: ['Explore', 'Découvre', 'Génère', 'Ton espace'],
                captions: ['La carte vivante, autour de toi', 'Une fiche, des avis, des alertes', 'Un parcours calculé pour de vrai', 'Ton espace qui apprend de toi'],
                init() {
                    const pts = this.places.filter(p => p.lat && p.lng);
                    if (pts.length) this.place = pts[0];
                    const el = this.$refs.map;
                    const start = () => {
                        if (map || !window.L || !el) return;
                        map = L.map(el, { zoomControl: false, attributionControl: false, dragging: false, scrollWheelZoom: false, doubleClickZoom: false, touchZoom: false, keyboard: false }).setView([48.858, 2.345], window.innerWidth < 1024 ? 12 : 13);
                        C.tileLayer().addTo(map);
                        pts.forEach((p, i) => setTimeout(() => { const m = L.marker([p.lat, p.lng], { icon: C.placeIcon(p.slug, { size: 26 }) }).addTo(map); m.getElement()?.querySelector('.camino-pin')?.classList.add('pin-pop'); }, 500 + i * 200));
                        let t = 0; setInterval(() => { t += 0.004; map.panTo([48.858 + Math.sin(t) * 0.012, 2.345 + Math.cos(t * 0.8) * 0.02], { animate: true, duration: 1.5, easeLinearity: 0.2 }); }, 1600);
                    };
                    if (el && el.clientHeight > 0) start();
                    else if (window.ResizeObserver && el) { const ro = new ResizeObserver(() => { if (el.clientHeight > 0) { ro.disconnect(); start(); } }); ro.observe(el); }
                    // La fiche de la carte tourne, puis les écrans s'enchaînent.
                    setInterval(() => { if (this.screen !== 0 || !pts.length) return; this.idx = (this.idx + 1) % pts.length; this.place = null; setTimeout(() => this.place = pts[this.idx], 60); }, 3200);
                    setInterval(() => { if (document.hidden) return; this.go((this.screen + 1) % this.steps.length); }, 4600);
                },
                go(n) { this.prev = this.screen; this.screen = n; if (n === 0 && map) setTimeout(() => map.invalidateSize(), 100); },
                cls(n) { return n === this.screen ? '' : (n === this.prev ? 'is-leaving' : 'is-hidden'); },
            };
        }
        document.addEventListener('DOMContentLoaded', () => {
            const io = new IntersectionObserver((entries) => entries.forEach(en => { if (en.isIntersecting) { en.target.classList.add('is-visible'); io.unobserve(en.target); } }), { threshold: 0.05, rootMargin: '0px 0px -4% 0px' });
            document.querySelectorAll('.reveal').forEach(el => io.observe(el));
            const co = new IntersectionObserver((entries) => entries.forEach(en => {
                if (!en.isIntersecting) return; co.unobserve(en.target);
                const el = en.target, target = parseInt(el.dataset.count, 10) || 0, start = performance.now(), dur = 1400;
                const step = (now) => { const p = Math.min(1, (now - start) / dur), v = Math.round(target * (1 - Math.pow(1 - p, 3))); el.textContent = v.toLocaleString('fr-FR'); if (p < 1) requestAnimationFrame(step); };
                requestAnimationFrame(step);
            }), { threshold: 0.5 });
            document.querySelectorAll('[data-count]').forEach(el => co.observe(el));
            let deferredInstall = null;
            window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); deferredInstall = e; document.querySelectorAll('[data-install]').forEach(b => b.classList.remove('hidden')); });
            document.querySelectorAll('[data-install]').forEach(b => b.addEventListener('click', async () => { if (!deferredInstall) return; deferredInstall.prompt(); await deferredInstall.userChoice; deferredInstall = null; b.classList.add('hidden'); }));
            if (/iPhone|iPad|iPod/.test(navigator.userAgent) && !window.navigator.standalone) document.querySelectorAll('[data-ios-tip]').forEach(t => t.classList.remove('hidden'));
        });
    </script>
    @endpush
</x-app-layout>
