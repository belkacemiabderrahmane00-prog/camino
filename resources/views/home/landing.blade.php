@php
    $current = $forecast['current'] ?? null;
    $rows = $marquee->chunk((int) ceil(max(1, $marquee->count()) / 2));
    $spot = $marquee->take(8)->map(fn ($p) => ['id' => $p->id, 'title' => $p->title, 'category' => $p->category->name ?? '', 'slug' => $p->category->slug ?? null, 'cover' => $p->coverThumb(1200), 'address' => $p->address, 'lat' => (float) $p->lat, 'lng' => (float) $p->lng, 'free' => (bool) $p->is_free, 'rating' => isset($p->reviews_avg_rating) && $p->reviews_avg_rating ? round((float) $p->reviews_avg_rating, 1) : null, 'url' => route('places.show', $p)])->values();
    $heroPlaces = $featured->map(fn ($p) => ['id' => $p->id, 'title' => $p->title, 'category' => $p->category->name ?? '', 'slug' => $p->category->slug ?? null, 'cover' => $p->coverThumb(500), 'lat' => (float) $p->lat, 'lng' => (float) $p->lng, 'free' => (bool) $p->is_free, 'url' => route('places.show', $p)])->values();
@endphp
<x-app-layout description="CAMINO, le GPS culturel qui rend la ville fun : carte vivante, parcours générés selon ta météo et ton budget, bons plans de la communauté.">

    {{-- ================================================================ HERO --}}
    <section class="relative lg:min-h-[92vh] -mt-[4.6rem] flex items-center overflow-hidden grain">
        <div class="absolute inset-0 -z-10 bg-ink">
            <img src="{{ asset('images/photo_paris.avif') }}" alt="" class="kenburns hero-photo absolute inset-0 h-full w-full object-cover opacity-90" fetchpriority="high">
            <div class="absolute inset-0 bg-gradient-to-r from-ink/85 via-ink/55 to-ink/20"></div>
            <div class="absolute inset-x-0 bottom-0 h-56 bg-gradient-to-t from-paper to-transparent"></div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-28 pb-16 lg:pt-32 lg:pb-24 w-full grid lg:grid-cols-[1.1fr_0.9fr] gap-8 lg:gap-10 items-center"
             x-data="{ words: ['autrement.', 'à pied.', 'gratuitement.', 'sous le soleil.', 'entre amis.', 'sans plan.'], i: 0, flip: false }"
             x-init="setInterval(() => { i = (i + 1) % words.length; flip = !flip; }, 2400)">
            <div class="text-white animate-fade-up">
                <div class="flex flex-wrap items-center gap-2 mb-6">
                    <span class="inline-flex items-center gap-2 rounded-full bg-white/10 backdrop-blur px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.16em] border border-white/15">
                        <span class="relative flex h-2 w-2"><span class="absolute inline-flex h-full w-full rounded-full bg-coral opacity-75 animate-ping"></span><span class="relative inline-flex h-2 w-2 rounded-full bg-coral"></span></span>
                        En direct · Île-de-France
                    </span>
                    @if($current)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 backdrop-blur px-3 py-1.5 text-xs border border-white/15">
                            <span class="material-symbols-outlined filled text-sun" style="font-size:16px">{{ $current['icon'] }}</span>{{ round($current['temp']) }}° · {{ $current['label'] }} à Paris
                        </span>
                    @endif
                </div>

                <h1 class="display text-[46px] sm:text-6xl lg:text-[76px] leading-[1.02]">
                    Explore la ville<br>
                    <span class="text-coral italic" :class="flip ? 'word-a' : 'word-b'" x-text="words[i]">autrement.</span>
                </h1>
                <p class="mt-6 text-lg sm:text-xl text-white/80 max-w-xl">
                    {{ number_format($stats['places'], 0, ',', ' ') }} lieux qui valent le détour, une carte qui bouge en temps réel, et un parcours calculé en 10 secondes selon ton temps, ton budget et la météo. La ville comme un terrain de jeu.
                </p>

                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('map.index') }}" class="btn btn-lg btn-primary"><span class="material-symbols-outlined">map</span>Ouvrir la carte</a>
                    <a href="#generateur" class="btn btn-lg bg-white/10 text-white border border-white/20 backdrop-blur hover:bg-white/20"><span class="material-symbols-outlined">auto_awesome</span>Générer un parcours</a>
                </div>

                <form action="{{ route('map.index') }}" method="GET" class="mt-6 flex items-center gap-2 rounded-full bg-white/95 p-1.5 pl-4 max-w-lg shadow-float">
                    <span class="material-symbols-outlined text-ink-muted">search</span>
                    <input type="search" name="q" placeholder="Un lieu, un quartier, une envie…" class="flex-1 border-0 bg-transparent focus:ring-0 text-sm text-ink placeholder:text-ink-muted/70" autocomplete="off">
                    <button type="submit" class="btn btn-md btn-ink">Explorer</button>
                </form>

                <div class="mt-5 flex flex-wrap gap-2">
                    @foreach([['musees', 'palette', 'Musées'], ['monuments', 'account_balance', 'Monuments'], ['parcs', 'park', 'Parcs'], ['free', 'loyalty', 'Gratuit'], ['evenements', 'celebration', 'Événements']] as [$f, $icon, $label])
                        <a href="{{ route('map.index', ['filtre' => $f]) }}" class="chip bg-white/10 text-white border-white/15 hover:bg-white/20 hover:text-white backdrop-blur"><span class="material-symbols-outlined" style="font-size:16px">{{ $icon }}</span>{{ $label }}</a>
                    @endforeach
                </div>
            </div>

            {{-- Téléphone vivant --}}
            <div class="relative flex flex-col items-center lg:flex lg:justify-end lg:pr-6" x-data="heroPhone()" @mousemove.window="tilt($event)">
                <div class="absolute h-72 w-72 rounded-full bg-coral/30 blur-3xl"></div>
                <div class="absolute -bottom-10 right-10 h-56 w-56 rounded-full bg-teal/30 blur-3xl"></div>
                <div class="phone phone-hero lg:float-soft" :style="`--tilt:${rot}deg`">
                    <div class="phone-screen hero-map">
                        <div x-ref="map" class="absolute inset-0"></div>
                        <div class="absolute top-12 inset-x-3 z-[400] flex items-center gap-2 rounded-full bg-white/95 px-3 py-2 shadow-card text-[11px] text-ink-muted">
                            <span class="material-symbols-outlined" style="font-size:16px">search</span>Autour de moi
                            <span class="ml-auto h-6 w-6 rounded-full bg-coral text-white flex items-center justify-center"><span class="material-symbols-outlined" style="font-size:14px">my_location</span></span>
                        </div>
                        <template x-if="current">
                            <a :href="current.url" class="absolute bottom-3 inset-x-3 z-[400] card p-2 flex gap-2.5 items-center animate-fade-up" :key="current.id">
                                <div class="h-14 w-14 rounded-xl overflow-hidden shrink-0 placeholder-cover flex items-center justify-center">
                                    <template x-if="current.cover"><img :src="current.cover" alt="" class="h-full w-full object-cover"></template>
                                    <template x-if="!current.cover"><span class="material-symbols-outlined text-white/80" style="font-size:20px">place</span></template>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-teal" x-text="current.category"></p>
                                    <p class="text-xs font-semibold leading-snug line-clamp-2 text-ink" x-text="current.title"></p>
                                    <p class="text-[10px] text-ink-muted" x-text="current.free ? 'Gratuit · à 6 min à pied' : 'À 6 min à pied'"></p>
                                </div>
                            </a>
                        </template>
                    </div>
                </div>
                <div class="hidden lg:flex lg:absolute lg:-left-4 lg:bottom-10 card px-3 py-2 items-center gap-2 text-xs animate-fade-up" style="animation-delay:.5s">
                    <span class="h-8 w-8 rounded-full bg-sun-soft text-amber-600 flex items-center justify-center"><span class="material-symbols-outlined" style="font-size:18px">celebration</span></span>
                    <div><p class="font-semibold leading-tight">Concert gratuit ce soir</p><p class="text-[10px] text-ink-muted">Signalé par la communauté · il y a 12 min</p></div>
                </div>
            </div>
        </div>

        <a href="#detour" class="hidden lg:flex absolute bottom-6 left-1/2 -translate-x-1/2 text-ink-muted hover:text-ink flex-col items-center text-[10px] uppercase tracking-widest">
            Découvrir <span class="material-symbols-outlined animate-bounce">keyboard_arrow_down</span>
        </a>
    </section>

    {{-- ================================================================ À la une --}}
    <section id="detour" class="max-w-7xl mx-auto px-4 sm:px-6 mt-6 sm:mt-14">
        <div class="reveal">
            <x-section-heading eyebrow="À la une" title="Des lieux qu'on ne trouve pas dans les guides" subtitle="Une sélection qui change toutes les cinq secondes. Touche une vignette pour choisir." :href="route('map.index')" link-label="Tout voir sur la carte" />
        </div>
        <div x-data="spotlight(@js($spot))" @mouseenter="pause()" @mouseleave="resume()" @keydown.arrow-right.window="next()" @keydown.arrow-left.window="prev()" class="reveal grid lg:grid-cols-[1.65fr_1fr] gap-4">
            {{-- Grande scène --}}
            <div class="relative rounded-4xl overflow-hidden h-[360px] sm:h-[480px] bg-ink shadow-float select-none" @touchstart.passive="touchStart($event)" @touchend.passive="touchEnd($event)">
                <template x-for="(p, idx) in items" :key="p.id">
                    <img x-show="idx === i" x-transition.opacity.duration.700ms :src="p.cover" :alt="p.title" class="absolute inset-0 h-full w-full object-cover kenburns" loading="lazy">
                </template>
                <div class="absolute inset-0 bg-gradient-to-t from-ink/90 via-ink/25 to-transparent"></div>
                <div class="absolute inset-0 bg-gradient-to-r from-ink/40 to-transparent"></div>
                <button @click="prev()" class="absolute left-3 top-1/2 -translate-y-1/2 h-11 w-11 rounded-full bg-white/15 backdrop-blur text-white flex items-center justify-center hover:bg-white/30 transition" aria-label="Précédent"><span class="material-symbols-outlined">chevron_left</span></button>
                <button @click="next()" class="absolute right-3 top-1/2 -translate-y-1/2 h-11 w-11 rounded-full bg-white/15 backdrop-blur text-white flex items-center justify-center hover:bg-white/30 transition" aria-label="Suivant"><span class="material-symbols-outlined">chevron_right</span></button>
                <div class="absolute inset-x-0 bottom-0 p-5 sm:p-8 text-white">
                    <template x-if="cur">
                        <div :key="cur.id" class="animate-fade-up">
                            <div class="flex flex-wrap items-center gap-2 mb-3">
                                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold text-white" :style="`background:${style(cur).color}`"><span class="material-symbols-outlined" style="font-size:14px" x-text="style(cur).icon"></span><span x-text="cur.category"></span></span>
                                <span x-show="cur.free" class="badge badge-free">Gratuit</span>
                                <span x-show="cur.rating" class="badge bg-white/20 text-white"><span class="material-symbols-outlined filled" style="font-size:14px">star</span><span x-text="cur.rating"></span></span>
                            </div>
                            <h3 class="display text-3xl sm:text-5xl leading-[1.02] max-w-2xl" x-text="cur.title"></h3>
                            <p class="mt-2 text-sm text-white/80 line-clamp-1" x-text="cur.address"></p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <a :href="cur.url" class="btn btn-md bg-white text-ink hover:-translate-y-0.5"><span class="material-symbols-outlined" style="font-size:18px">open_in_new</span>Découvrir</a>
                                <a :href="`{{ route('map.index') }}?lat=${cur.lat}&lng=${cur.lng}&z=16`" class="btn btn-md bg-white/15 text-white border border-white/20 backdrop-blur hover:bg-white/25"><span class="material-symbols-outlined" style="font-size:18px">map</span>Sur la carte</a>
                            </div>
                        </div>
                    </template>
                </div>
                {{-- Barre de progression --}}
                <div class="absolute top-0 inset-x-0 h-1 bg-white/20"><div class="h-full bg-coral transition-[width] duration-100 ease-linear" :style="`width:${progress}%`"></div></div>
                <div class="absolute top-4 right-4 text-[11px] font-semibold text-white/80 bg-ink/30 backdrop-blur rounded-full px-2.5 py-1" x-text="(i + 1) + ' / ' + items.length"></div>
            </div>

            {{-- Vignettes : grille bento sur grand écran, bande défilante sur mobile --}}
            <div class="grid grid-cols-2 gap-3 max-lg:flex max-lg:overflow-x-auto max-lg:snap-x max-lg:hide-scrollbar max-lg:-mx-4 max-lg:px-4 max-lg:pb-1">
                <template x-for="(p, idx) in items" :key="'t' + p.id">
                    <button @click="go(idx)" class="relative rounded-3xl overflow-hidden group text-left max-lg:w-40 max-lg:shrink-0 max-lg:snap-start h-32 sm:h-36 lg:h-auto transition-all duration-300" :class="idx === i ? 'ring-4 ring-coral ring-offset-2 ring-offset-paper' : 'hover:-translate-y-1'">
                        <img :src="p.cover" :alt="p.title" class="absolute inset-0 h-full w-full object-cover transition-transform duration-700 group-hover:scale-110" loading="lazy">
                        <div class="absolute inset-0 bg-gradient-to-t from-ink/80 to-transparent"></div>
                        <div class="absolute bottom-2.5 left-3 right-3 text-white">
                            <p class="text-[10px] uppercase tracking-widest opacity-80" x-text="p.category"></p>
                            <p class="text-sm font-semibold leading-tight line-clamp-2" x-text="p.title"></p>
                        </div>
                    </button>
                </template>
            </div>
        </div>
    </section>
    {{-- ================================================================ Chiffres --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 mt-14 reveal">
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
    <section id="generateur" class="max-w-7xl mx-auto px-4 sm:px-6 mt-16 sm:mt-24 scroll-mt-24 reveal">
        <div class="rounded-4xl bg-ink text-white p-6 sm:p-10 grid lg:grid-cols-[1fr_1.2fr] gap-8 items-center relative overflow-hidden">
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

    {{-- ================================================================ Mobile --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 mt-16 sm:mt-28 grid lg:grid-cols-2 gap-10 items-center">
        <div class="reveal">
            <p class="eyebrow">Bientôt dans ta poche</p>
            <h2 class="display text-3xl sm:text-5xl mt-2">L'app CAMINO arrive sur iOS et Android.</h2>
            <p class="mt-4 text-ink-soft">En attendant, CAMINO est déjà une vraie app sur ton téléphone : ouvre le site, ajoute-le à ton écran d'accueil et retrouve la carte, les alertes et tes parcours en plein écran.</p>
            <div class="mt-6 flex flex-wrap items-center gap-3">
                <span class="btn btn-md btn-ink opacity-90 cursor-default"><span class="material-symbols-outlined" style="font-size:20px">ios</span>App Store <span class="ml-1 rounded-full bg-sun text-ink text-[10px] px-2 py-0.5">bientôt</span></span>
                <span class="btn btn-md btn-ink opacity-90 cursor-default"><span class="material-symbols-outlined" style="font-size:20px">android</span>Google Play <span class="ml-1 rounded-full bg-sun text-ink text-[10px] px-2 py-0.5">bientôt</span></span>
                <button type="button" data-install class="hidden btn btn-md btn-teal"><span class="material-symbols-outlined" style="font-size:18px">add_to_home_screen</span>Installer CAMINO</button>
                <span data-ios-tip class="hidden text-xs text-ink-muted"><span class="material-symbols-outlined align-middle" style="font-size:16px">ios_share</span> Sur iPhone : Partager, puis « Sur l'écran d'accueil ».</span>
            </div>
            <div class="mt-6 flex items-center gap-4 card p-4 max-w-md">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&margin=4&color=12161C&bgcolor=FFFFFF&data={{ urlencode(route('map.index', ['source' => 'qr'])) }}" alt="QR code vers la carte CAMINO" width="96" height="96" class="rounded-xl shrink-0 h-24 w-24" loading="lazy">
                <div class="text-sm">
                    <p class="font-semibold">Scanne pour l'ouvrir sur ton téléphone</p>
                    <p class="text-ink-muted text-xs mt-1">Puis « Ajouter à l'écran d'accueil » dans le menu du navigateur. Ça marche déjà sur iPhone et Android.</p>
                </div>
            </div>
            <ul class="mt-6 grid sm:grid-cols-3 gap-3 text-sm">
                @foreach([['my_location', 'Autour de toi', 'Les lieux apparaissent selon ta position.'], ['campaign', 'Alertes live', 'Événement gratuit, affluence, fermeture.'], ['offline_bolt', 'Léger', 'Pas de compte obligatoire pour explorer.']] as [$i, $t, $d])
                    <li class="rounded-2xl bg-white/70 border border-ink/5 p-3"><span class="material-symbols-outlined text-teal">{{ $i }}</span><p class="font-semibold mt-1">{{ $t }}</p><p class="text-xs text-ink-muted">{{ $d }}</p></li>
                @endforeach
            </ul>
        </div>

        <div class="relative reveal reveal-delay-1" x-data="{ dev: 'ios', desktop: window.matchMedia('(min-width: 1024px)').matches }" x-init="window.matchMedia('(min-width: 1024px)').addEventListener('change', e => desktop = e.matches)">
            <div class="absolute h-80 w-80 rounded-full bg-coral/20 blur-3xl left-1/2 -translate-x-1/2 top-10"></div>
            <div class="lg:hidden flex justify-center gap-2 mb-5 relative">
                <button @click="dev = 'ios'" class="chip" :data-active="dev === 'ios'"><span class="material-symbols-outlined" style="font-size:16px">ios</span>iPhone</button>
                <button @click="dev = 'android'" class="chip" :data-active="dev === 'android'"><span class="material-symbols-outlined" style="font-size:16px">android</span>Android</button>
            </div>
            <div class="relative flex justify-center items-start gap-8">
                <div x-show="desktop || dev === 'ios'" x-transition.opacity class="text-center">
                    <span class="hidden lg:inline-flex items-center gap-1.5 mb-3 badge badge-paid"><span class="material-symbols-outlined" style="font-size:14px">ios</span>iPhone · iOS</span>
                    <div class="phone phone-ios lg:-rotate-6">
                        <div class="phone-screen">
                            <div class="absolute inset-0" style="background: radial-gradient(circle at 30% 30%, #E9F5EA 0, transparent 35%), radial-gradient(circle at 70% 60%, #FCE8E1 0, transparent 40%), repeating-linear-gradient(90deg, rgba(18,22,28,0.05) 0 1px, transparent 1px 34px), repeating-linear-gradient(0deg, rgba(18,22,28,0.05) 0 1px, transparent 1px 34px), #F3EFE6;"></div>
                            <svg class="absolute inset-0 w-full h-full" viewBox="0 0 270 560" fill="none"><path d="M-10 200 C 60 160, 120 260, 200 220 S 300 180, 320 220" stroke="#fff" stroke-width="14" stroke-linecap="round"/><path d="M60 -10 C 90 120, 40 260, 110 400 S 140 520, 130 580" stroke="#fff" stroke-width="10"/><path d="M-10 380 C 80 350, 160 420, 290 360" stroke="#fff" stroke-width="10"/></svg>
                            @foreach([[78, 180, '#7C3AED', 'palette'], [150, 240, '#B45309', 'account_balance'], [200, 150, '#15803D', 'park'], [110, 330, '#0369A1', 'theater_comedy'], [190, 380, '#DB2777', 'restaurant']] as $i => [$x, $y, $c, $ic])
                                <div class="absolute camino-pin pin-pop" style="left:{{ $x }}px; top:{{ $y }}px; background:{{ $c }}; width:34px; height:34px; animation-delay: {{ 0.3 + $i * 0.25 }}s"><span class="material-symbols-outlined" style="font-size:17px">{{ $ic }}</span></div>
                            @endforeach
                            <div class="absolute camino-pin camino-pin-alert pin-pop" style="left:150px; top:300px; background:#F59E0B; color:#F59E0B; animation-delay:1.7s"><span class="material-symbols-outlined" style="font-size:15px">celebration</span></div>
                            <div class="absolute top-12 inset-x-3 flex gap-1.5 overflow-hidden">
                                <span class="chip chip-active !py-1 !px-2.5 text-[10px]">Tous</span><span class="chip !py-1 !px-2.5 text-[10px]">Musées</span><span class="chip !py-1 !px-2.5 text-[10px]">Gratuit</span>
                            </div>
                            <div class="absolute bottom-3 inset-x-3 card p-2.5">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-amber-600">Événement gratuit · il y a 4 min</p>
                                <p class="text-xs font-semibold leading-snug">Concert dans la cour du musée à 19h</p>
                                <p class="text-[10px] text-ink-muted mt-0.5">Signalé par Léa · expire dans 20 h</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div x-show="desktop || dev === 'android'" x-transition.opacity class="text-center lg:mt-14">
                    <span class="hidden lg:inline-flex items-center gap-1.5 mb-3 badge badge-paid"><span class="material-symbols-outlined" style="font-size:14px">android</span>Galaxy · Android</span>
                    <div class="phone phone-android lg:rotate-3">
                        <div class="phone-screen p-3 pt-12 space-y-2 text-ink text-left">
                            <p class="eyebrow">Parcours généré</p>
                            <p class="font-display text-lg leading-tight">Balade musées & monuments</p>
                            <p class="text-[10px] text-ink-muted">3 h 10 · 4,2 km · 12 € · plutôt dégagé</p>
                            <div class="h-20 rounded-2xl overflow-hidden relative" style="background: linear-gradient(135deg,#E9F5EA,#FCE8E1)">
                                <svg class="absolute inset-0 w-full h-full" viewBox="0 0 240 80" fill="none"><path d="M20 60 C 60 10, 110 70, 150 30 S 210 20, 225 40" stroke="#FF5A3C" stroke-width="3.5" stroke-linecap="round" stroke-dasharray="200" stroke-dashoffset="200"><animate attributeName="stroke-dashoffset" from="200" to="0" dur="2.5s" repeatCount="indefinite"/></path><circle cx="20" cy="60" r="5" fill="#FF5A3C"/><circle cx="150" cy="30" r="5" fill="#12161C"/><circle cx="225" cy="40" r="5" fill="#12161C"/></svg>
                            </div>
                            @foreach([['10:15', 'Musée Rodin', 'Musée · 1 h 30', '#7C3AED'], ['12:05', 'Hôtel des Invalides', 'Monument · 45 min', '#B45309'], ['13:10', 'Pont Alexandre III', 'Monument · 20 min', '#B45309']] as $i => [$t, $n, $d, $c])
                                <div class="flex items-center gap-2 rounded-xl bg-white p-2 shadow-card">
                                    <span class="h-7 w-7 rounded-full text-white text-[11px] font-bold flex items-center justify-center" style="background:{{ $c }}">{{ $i + 1 }}</span>
                                    <div class="min-w-0 flex-1"><p class="text-[11px] font-semibold leading-tight truncate">{{ $n }}</p><p class="text-[10px] text-ink-muted">{{ $d }}</p></div>
                                    <span class="text-[10px] font-semibold text-ink-muted">{{ $t }}</span>
                                </div>
                            @endforeach
                            <span class="btn btn-sm btn-primary w-full !text-[11px]"><span class="material-symbols-outlined" style="font-size:14px">navigation</span>Lancer</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    {{-- ================================================================ Événements + alertes --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 mt-16 sm:mt-28 grid lg:grid-cols-[1.2fr_0.8fr] gap-6">
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
    <section class="max-w-7xl mx-auto px-4 sm:px-6 mt-16 sm:mt-28">
        <div class="reveal"><x-section-heading eyebrow="Simple comme bonjour" title="Comment ça marche" /></div>
        <div class="grid md:grid-cols-3 gap-4">
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
    <section class="max-w-7xl mx-auto px-4 sm:px-6 mt-16 sm:mt-28 reveal">
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
        function spotlight(items) {
            const C = window.Camino;
            return {
                items, i: 0, progress: 0, timer: null, paused: false, tx: 0,
                get cur() { return this.items[this.i] || null; },
                style(p) { return C.categoryStyle(p.slug); },
                init() { this.timer = setInterval(() => { if (this.paused || document.hidden) return; this.progress += 1; if (this.progress >= 100) this.next(); }, 50); },
                go(idx) { this.i = (idx + this.items.length) % this.items.length; this.progress = 0; },
                next() { this.go(this.i + 1); }, prev() { this.go(this.i - 1); },
                pause() { this.paused = true; }, resume() { this.paused = false; },
                touchStart(e) { this.tx = e.changedTouches[0].clientX; },
                touchEnd(e) { const dx = e.changedTouches[0].clientX - this.tx; if (Math.abs(dx) > 40) dx < 0 ? this.next() : this.prev(); },
            };
        }
        function heroPhone() {
            const C = window.Camino;
            return {
                places: @js($heroPlaces), current: null, rot: -3, idx: 0,
                init() {
                    if (!window.L || !this.$refs.map) return;
                    const el = this.$refs.map;
                    let started = false;
                    const start = () => { if (started) return; started = true; this.boot(el); };
                    if (el.clientHeight > 0) start();
                    else if (window.ResizeObserver) { const ro = new ResizeObserver(() => { if (el.clientHeight > 0) { ro.disconnect(); start(); } }); ro.observe(el); }
                    else setTimeout(start, 300);
                },
                boot(el) {
                    const center = [48.858, 2.345];
                    const mobile = window.innerWidth < 1024;
                    const map = L.map(el, { zoomControl: false, attributionControl: false, dragging: false, scrollWheelZoom: false, doubleClickZoom: false, touchZoom: false, keyboard: false }).setView(center, 13);
                    C.tileLayer().addTo(map);
                    const pts = this.places.filter(p => p.lat && p.lng);
                    pts.forEach((p, i) => setTimeout(() => {
                        const m = L.marker([p.lat, p.lng], { icon: C.placeIcon(p.slug, { size: 30 }) }).addTo(map);
                        const el = m.getElement(); if (el) el.querySelector('.camino-pin')?.classList.add('pin-pop');
                    }, 600 + i * 220));
                    // Lent panoramique + carte de lieu qui tourne en boucle
                    let t = 0; setInterval(() => { t += 0.004; map.panTo([center[0] + Math.sin(t) * 0.012, center[1] + Math.cos(t * 0.8) * 0.02], { animate: true, duration: 1.5, easeLinearity: 0.2 }); }, 1600);
                    if (pts.length) { this.current = pts[0]; setInterval(() => { this.idx = (this.idx + 1) % pts.length; this.current = null; setTimeout(() => this.current = pts[this.idx], 80); }, 3800); }
                },
                tilt(e) { if (window.innerWidth < 1024) return; this.rot = -3 + ((e.clientX / window.innerWidth) - 0.5) * 6; },
            };
        }
        document.addEventListener('DOMContentLoaded', () => {
            // Apparition au défilement
            const io = new IntersectionObserver((entries) => entries.forEach(en => { if (en.isIntersecting) { en.target.classList.add('is-visible'); io.unobserve(en.target); } }), { threshold: 0.12 });
            document.querySelectorAll('.reveal').forEach(el => io.observe(el));
            // Compteurs
            const co = new IntersectionObserver((entries) => entries.forEach(en => {
                if (!en.isIntersecting) return; co.unobserve(en.target);
                const el = en.target, target = parseInt(el.dataset.count, 10) || 0, start = performance.now(), dur = 1400;
                const step = (now) => { const p = Math.min(1, (now - start) / dur), v = Math.round(target * (1 - Math.pow(1 - p, 3))); el.textContent = v.toLocaleString('fr-FR'); if (p < 1) requestAnimationFrame(step); };
                requestAnimationFrame(step);
            }), { threshold: 0.5 });
            document.querySelectorAll('[data-count]').forEach(el => co.observe(el));
            // Installation PWA : vrai bouton quand le navigateur le permet (Android/Chrome), astuce sur iPhone.
            let deferredInstall = null;
            window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); deferredInstall = e; document.querySelectorAll('[data-install]').forEach(b => b.classList.remove('hidden')); });
            document.querySelectorAll('[data-install]').forEach(b => b.addEventListener('click', async () => { if (!deferredInstall) return; deferredInstall.prompt(); await deferredInstall.userChoice; deferredInstall = null; b.classList.add('hidden'); }));
            if (/iPhone|iPad|iPod/.test(navigator.userAgent) && !window.navigator.standalone) document.querySelectorAll('[data-ios-tip]').forEach(t => t.classList.remove('hidden'));
        });
    </script>
    @endpush
</x-app-layout>
