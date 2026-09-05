<x-app-layout>
    {{-- ================================================================ Hero --}}
    <section class="relative overflow-hidden">
        <div class="absolute inset-0 -z-10">
            <div class="absolute -top-40 -right-32 h-[520px] w-[520px] rounded-full bg-coral/15 blur-3xl"></div>
            <div class="absolute top-40 -left-40 h-[420px] w-[420px] rounded-full bg-teal/15 blur-3xl"></div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 sm:pt-16 pb-12 grid lg:grid-cols-[1.05fr_0.95fr] gap-10 lg:gap-16 items-center">
            <div class="animate-fade-up">
                <div class="flex flex-wrap items-center gap-3 mb-6">
                    <span class="eyebrow">GPS culturel intelligent · Île-de-France</span>
                    <x-weather-chip :forecast="$forecast" label="Paris" />
                </div>
                <h1 class="display text-[44px] sm:text-6xl lg:text-7xl">
                    Explore la ville<br>
                    <span class="italic text-coral">autrement.</span>
                </h1>
                <p class="mt-6 text-lg text-ink-soft max-w-xl">
                    {{ number_format($stats['places'], 0, ',', ' ') }} musées, monuments, parcs, scènes et bons plans sur une carte vivante.
                    Dis-nous ton temps, ton budget et tes envies : CAMINO trace le parcours, à pied ou à vélo, en tenant compte de la météo.
                </p>

                <form action="{{ route('map.index') }}" method="GET" class="mt-8 flex items-center gap-2 card p-2 pl-4 max-w-xl">
                    <span class="material-symbols-outlined text-ink-muted">search</span>
                    <input type="search" name="q" placeholder="Un lieu, un quartier, une envie…" class="flex-1 border-0 bg-transparent focus:ring-0 text-sm placeholder:text-ink-muted/70" autocomplete="off">
                    <button type="submit" class="btn btn-md btn-ink">Explorer</button>
                </form>

                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach([['musees', 'palette', 'Musées'], ['monuments', 'account_balance', 'Monuments'], ['parcs', 'park', 'Parcs & jardins'], ['free', 'loyalty', 'Gratuit'], ['evenements', 'celebration', 'Événements']] as [$f, $icon, $label])
                        <a href="{{ route('map.index', ['filtre' => $f]) }}" class="chip"><span class="material-symbols-outlined" style="font-size:16px">{{ $icon }}</span>{{ $label }}</a>
                    @endforeach
                </div>
            </div>

            {{-- Collage --}}
            <div class="relative animate-fade-up" style="animation-delay: 120ms">
                <div class="grid grid-cols-2 gap-3 sm:gap-4">
                    @foreach($featured->take(4) as $i => $place)
                        <a href="{{ route('places.show', $place) }}" class="group relative overflow-hidden rounded-3xl shadow-card {{ $i === 0 ? 'row-span-2 h-72 sm:h-96' : 'h-36 sm:h-44' }} {{ $i === 3 ? '-mt-6' : '' }}">
                            <x-cover :place="$place" :eager="true" class="h-full group-hover:scale-105 transition-transform duration-500" />
                            <div class="absolute inset-0 bg-gradient-to-t from-ink/80 via-ink/10 to-transparent"></div>
                            <div class="absolute bottom-3 left-3 right-3 text-white">
                                <p class="text-[10px] uppercase tracking-widest opacity-80">{{ $place->category->name ?? '' }}</p>
                                <p class="font-semibold text-sm leading-tight line-clamp-2">{{ $place->title }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
                <div class="absolute -bottom-5 -left-4 sm:-left-8 card px-4 py-3 flex items-center gap-3 animate-fade-up" style="animation-delay: 300ms">
                    <span class="h-10 w-10 rounded-2xl bg-teal-soft text-teal flex items-center justify-center"><span class="material-symbols-outlined filled">route</span></span>
                    <div class="text-sm leading-tight">
                        <p class="font-semibold">Parcours réels</p>
                        <p class="text-ink-muted text-xs">Trajets à pied ou à vélo calculés sur les rues d'OpenStreetMap</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ================================================================ Stats --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6">
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
                        <p class="text-xl font-semibold">{{ number_format($stats[$key], 0, ',', ' ') }}</p>
                        <p class="text-xs text-ink-muted">{{ $label }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ================================================================ Générateur express --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 mt-16 sm:mt-24">
        <div class="rounded-4xl bg-ink text-white p-6 sm:p-10 grid lg:grid-cols-[1fr_1.2fr] gap-8 items-center relative overflow-hidden">
            <div class="absolute -right-20 -top-20 h-72 w-72 rounded-full bg-coral/30 blur-3xl"></div>
            <div class="relative">
                <p class="eyebrow">L'innovation CAMINO</p>
                <h2 class="display text-3xl sm:text-4xl mt-2">Un parcours sur mesure en 10 secondes.</h2>
                <p class="mt-4 text-white/75">Temps disponible, budget, à pied ou à vélo, tes centres d'intérêt : l'algorithme sélectionne les lieux, optimise l'ordre et calcule les vrais temps de trajet. S'il pleut, il privilégie les lieux couverts.</p>
                <ul class="mt-5 space-y-2 text-sm text-white/80">
                    <li class="flex items-center gap-2"><span class="material-symbols-outlined text-sun" style="font-size:18px">check_circle</span>Horaires d'arrivée à chaque étape</li>
                    <li class="flex items-center gap-2"><span class="material-symbols-outlined text-sun" style="font-size:18px">check_circle</span>Tracé réel sur la carte, export vers Google Maps</li>
                    <li class="flex items-center gap-2"><span class="material-symbols-outlined text-sun" style="font-size:18px">check_circle</span>Recommandations qui apprennent de tes favoris</li>
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

    {{-- ================================================================ Sélection --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 mt-16 sm:mt-24">
        <x-section-heading eyebrow="À découvrir" title="Des lieux qui valent le détour" subtitle="Une sélection de lieux avec photo, renouvelée régulièrement." :href="route('map.index')" link-label="Ouvrir la carte" />
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach($featured as $place)
                <x-place-card :place="$place" />
            @endforeach
        </div>
    </section>

    {{-- ================================================================ Événements + alertes --}}
    @if($events->isNotEmpty() || $alerts->isNotEmpty())
        <section class="max-w-7xl mx-auto px-4 sm:px-6 mt-16 sm:mt-24 grid lg:grid-cols-[1.2fr_0.8fr] gap-6">
            <div>
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
            <div>
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
                            <p>Aucune alerte en ce moment.</p>
                            <p class="mt-1">Sur la carte, signale un événement gratuit, une forte affluence ou une fermeture : tout le monde le verra.</p>
                        </div>
                    @endforelse
                    <a href="{{ route('map.index') }}" class="btn btn-sm btn-soft w-full mt-1"><span class="material-symbols-outlined" style="font-size:16px">campaign</span>Signaler quelque chose</a>
                </div>
            </div>
        </section>
    @endif

    {{-- ================================================================ Comment ça marche --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 mt-16 sm:mt-24">
        <x-section-heading eyebrow="Simple" title="Comment ça marche" />
        <div class="grid md:grid-cols-3 gap-4">
            @foreach([
                ['explore', 'Explore la carte vivante', 'Filtre par type, budget ou distance. Les événements et alertes de la communauté apparaissent en temps réel, comme sur Waze.'],
                ['auto_awesome', 'Génère ton parcours', 'Indique ton temps, ton budget, ta mobilité et tes envies. CAMINO optimise l\'ordre et calcule les trajets réels, météo comprise.'],
                ['groups', 'Enrichis la carte', 'Partage une photo, laisse un avis, signale un concert gratuit ou une fermeture, propose un lieu que personne ne connaît.'],
            ] as $i => [$icon, $title, $text])
                <div class="card p-6">
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
    @guest
        <section class="max-w-7xl mx-auto px-4 sm:px-6 mt-16 sm:mt-24">
            <div class="rounded-4xl bg-coral text-white p-8 sm:p-12 text-center relative overflow-hidden">
                <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle at 1px 1px, #fff 1px, transparent 0); background-size: 22px 22px;"></div>
                <div class="relative max-w-2xl mx-auto">
                    <h2 class="display text-3xl sm:text-5xl">Ton profil culturel, qui apprend de toi.</h2>
                    <p class="mt-4 text-white/85">Favoris, avis, parcours réalisés : plus tu utilises CAMINO, plus les recommandations te ressemblent. Gratuit, sans pub.</p>
                    <div class="mt-6 flex flex-wrap justify-center gap-3">
                        <a href="{{ route('register') }}" class="btn btn-lg bg-white text-ink hover:-translate-y-0.5">Créer mon compte</a>
                        <a href="{{ route('map.index') }}" class="btn btn-lg bg-ink/20 text-white hover:bg-ink/30">Continuer sans compte</a>
                    </div>
                </div>
            </div>
        </section>
    @endguest
</x-app-layout>
