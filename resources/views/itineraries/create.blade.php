@php
    /** @var \Illuminate\Database\Eloquent\Collection|\App\Models\Category[] $categories */
    $itinerary = session('itinerary');
@endphp

<x-app-layout>
    <div class="max-w-5xl mx-auto px-4 py-6 sm:py-8 lg:py-10">
        <div class="mb-6 flex items-center justify-between gap-3">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Explore</p>
                <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">
                    Parcours & itinéraires
                </h1>
                <p class="mt-2 text-xs sm:text-sm text-slate-600 dark:text-slate-300">
                    Crée un parcours culturel sur mesure selon ton temps, ton budget et tes envies.
                </p>
            </div>
            <div class="hidden sm:flex items-center gap-2">
                @auth
                    <a href="{{ route('itineraries.index') }}" class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 dark:border-slate-700 px-3 py-1.5 text-xs text-slate-700 dark:text-slate-200 hover:border-primary hover:text-primary transition-colors">
                        <span class="material-symbols-outlined text-[16px]">history</span>
                        Mes parcours
                    </a>
                @endauth
            </div>
        </div>

        @if(session('status'))
            <div class="mb-4 rounded-2xl border border-primary/40 bg-primary/10 px-4 py-2 text-xs text-slate-800 dark:text-slate-100">{{ session('status') }}</div>
        @endif

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)]">
            <!-- Wizard form -->
            <x-ui.card glass class="space-y-5 bg-white border border-slate-300 shadow-lg shadow-slate-900/10 dark:bg-slate-900/90 dark:border-slate-800 dark:shadow-black/40 transition-colors duration-150">
                <div class="flex items-center gap-3 text-xs">
                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-primary/15 text-primary font-semibold shadow-sm shadow-primary/30">1</span>
                    <div>
                        <p class="font-semibold text-slate-950 dark:text-slate-100">Paramètres de ton parcours</p>
                        <p class="text-[11px] text-slate-700 dark:text-slate-400">Temps, budget, gratuité, centres d’intérêt.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('itineraries.store') }}" class="space-y-5 mt-2">
                    @csrf

                    <div class="grid gap-4 sm:grid-cols-2 text-xs">
                        <x-ui.input
                            label="Temps disponible (minutes)"
                            name="duration_minutes"
                            type="number"
                            min="30"
                            max="600"
                            value="{{ old('duration_minutes', 180) }}"
                            required
                        />
                        <x-ui.input
                            label="Budget approximatif (€)"
                            name="budget_eur"
                            type="number"
                            step="1"
                            min="0"
                            value="{{ old('budget_eur', 40) }}"
                            required
                        />
                    </div>

                    <div class="flex items-center justify-between gap-4 text-xs">
                        <div class="flex items-center gap-2">
                            <input
                                id="free_only"
                                type="checkbox"
                                name="free_only"
                                value="1"
                                @checked(old('free_only'))
                                class="rounded border-slate-300 text-primary focus:ring-primary bg-white dark:border-slate-600 dark:bg-slate-900"
                            >
                            <label for="free_only" class="text-slate-900 dark:text-slate-300">
                                Prioriser les lieux gratuits
                            </label>
                        </div>
                        <p class="hidden sm:block text-[11px] text-slate-700 dark:text-slate-500">
                            CAMINO veille à rester dans les contraintes choisies.
                        </p>
                    </div>

                    <div class="space-y-2 text-xs">
                        <p class="text-slate-950 dark:text-slate-200 font-semibold">Centres d’intérêt</p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($categories as $category)
                                @php
                                    $checked = in_array($category->id, old('category_ids', []));
                                @endphp
                                <label class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 border border-slate-200 bg-slate-50 text-[11px] text-slate-700 cursor-pointer hover:border-primary/70 hover:text-primary transition-colors duration-150 dark:border-slate-700/80 dark:bg-slate-900/80 dark:text-slate-200">
                                    <input
                                        type="checkbox"
                                        name="category_ids[]"
                                        value="{{ $category->id }}"
                                        class="h-3 w-3 rounded border-slate-300 text-primary focus:ring-primary bg-white dark:border-slate-500 dark:bg-slate-900"
                                        @checked($checked)
                                    >
                                    <span>{{ $category->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('category_ids.*')
                            <p class="mt-1 text-[11px] text-rose-500 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="pt-1 flex items-center justify-between">
                        <div class="flex items-center gap-2 text-[11px] text-slate-500 dark:text-slate-500">
                            <span class="material-symbols-outlined text-[16px] text-primary">lightbulb</span>
                            <span>Astuce : commence avec 2–3 catégories max.</span>
                        </div>
                        <x-ui.button variant="accent" size="md" class="rounded-full text-xs border border-slate-900/5 transition-transform duration-150 hover:-translate-y-0.5">
                            <span class="material-symbols-outlined text-[18px]">route</span>
                            Calculer un parcours
                        </x-ui.button>
                    </div>
                </form>
            </x-ui.card>

            @if($itineraryPlaces->isNotEmpty())
                <x-ui.card glass class="space-y-4 bg-white border border-slate-300 shadow-lg shadow-slate-900/10 dark:bg-slate-950/95 dark:border-slate-800 dark:shadow-black/40 transition-colors duration-150">
                    <div class="flex items-center justify-between">
                        <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-slate-500 dark:text-slate-400">Tes lieux</p>
                        <form method="POST" action="{{ route('itineraries.clear-places') }}" class="inline">
                            @csrf
                            <button type="submit" class="text-[11px] text-slate-500 dark:text-slate-400 hover:text-rose-500">Vider</button>
                        </form>
                    </div>
                    <div class="space-y-2 max-h-32 overflow-y-auto hide-scrollbar">
                        @foreach($itineraryPlaces as $p)
                            <div class="flex items-center justify-between gap-2 rounded-xl bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 px-3 py-2 text-xs">
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-slate-900 dark:text-slate-100 truncate">{{ $p->title }}</p>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">{{ $p->address ?? 'Adresse à venir' }}</p>
                                </div>
                                <div class="flex items-center gap-1 shrink-0">
                                    @if($p->getGoogleMapsUrl())
                                        <a href="{{ $p->getGoogleMapsUrl() }}" target="_blank" rel="noopener" class="p-1.5 rounded-lg text-slate-500 hover:bg-primary/20 hover:text-primary" title="Google Maps"><span class="material-symbols-outlined text-[16px]">map</span></a>
                                    @endif
                                    <form method="POST" action="{{ route('itineraries.remove-place', $p) }}" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1.5 rounded-lg text-slate-500 hover:bg-rose-500/20 hover:text-rose-400" title="Retirer"><span class="material-symbols-outlined text-[16px]">close</span></button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @php
                        $addrs = $itineraryPlaces->map(fn ($p) => $p->address ?? $p->title)->filter(fn ($a) => $a && $a !== 'Adresse à venir')->values()->all();
                    @endphp
                    @if(count($addrs) >= 1)
                        <div class="flex flex-wrap gap-2 pt-2 border-t border-slate-200 dark:border-slate-800">
                            @if(count($addrs) >= 2)
                                <a href="https://www.google.com/maps/dir/?api=1&origin={{ urlencode($addrs[0]) }}&destination={{ urlencode($addrs[count($addrs)-1]) }}{{ count($addrs) > 2 ? '&waypoints=' . implode('|', array_map('urlencode', array_slice($addrs, 1, -1))) : '' }}&travelmode=walking" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-full bg-primary/20 text-primary px-3 py-1.5 text-[11px] font-semibold hover:bg-primary/30">
                                    <span class="material-symbols-outlined text-[14px]">map</span> Google Maps
                                </a>
                            @else
                                <a href="https://www.google.com/maps/dir/?api=1&destination={{ urlencode($addrs[0]) }}&travelmode=walking" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-full bg-primary/20 text-primary px-3 py-1.5 text-[11px] font-semibold hover:bg-primary/30">
                                    <span class="material-symbols-outlined text-[14px]">map</span> Google Maps
                                </a>
                            @endif
                            <a href="https://waze.com/ul?q={{ urlencode($addrs[0]) }}&navigate=yes" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-full bg-primary/20 text-primary px-3 py-1.5 text-[11px] font-semibold hover:bg-primary/30">
                                <span class="material-symbols-outlined text-[14px]">directions_car</span> Waze
                            </a>
                        </div>
                    @endif
                </x-ui.card>
            @endif

            <!-- Result timeline -->
            <x-ui.card glass class="space-y-4 bg-white border border-slate-300 relative overflow-hidden shadow-lg shadow-slate-900/10 dark:bg-slate-950/95 dark:border-slate-800 dark:shadow-black/40 transition-colors duration-150">
                <div class="flex items-center justify-between text-xs">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-slate-500">Résultat</p>
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Parcours généré</p>
                    </div>
                    <div class="flex gap-3 text-[11px] text-slate-300">
                        @if($itinerary)
                            @php $result = $itinerary->result_json ?? []; @endphp
                            <div class="flex flex-col items-end">
                                <span class="text-slate-500 dark:text-slate-500">Durée</span>
                                <span class="font-semibold">{{ $result['estimated_total_minutes'] ?? '–' }} min</span>
                            </div>
                            @if(isset($result['total_distance_km']))
                                <div class="flex flex-col items-end">
                                    <span class="text-slate-500 dark:text-slate-500">Marche</span>
                                    <span class="font-semibold">{{ number_format($result['total_distance_km'], 1, ',', ' ') }} km</span>
                                </div>
                            @endif
                            <div class="flex flex-col items-end">
                                <span class="text-slate-500 dark:text-slate-500">Budget</span>
                                <span class="font-semibold">
                                    {{ number_format($result['estimated_total_budget'] ?? 0, 2, ',', ' ') }} €
                                </span>
                            </div>
                        @endif
                    </div>
                </div>

                @if($itinerary)
                    @php
                        $result = $itinerary->result_json ?? [];
                        $steps = $result['steps'] ?? [];
                    @endphp

                    @if(!empty($result['warnings']))
                        <div class="rounded-2xl border border-amber-400/60 bg-amber-500/10 px-3 py-2 text-[11px] text-amber-100">
                            @foreach($result['warnings'] as $warning)
                                <p>{{ $warning }}</p>
                            @endforeach
                        </div>
                    @endif

                    @if(!empty($steps))
                        <div id="itinerary-map" class="h-56 sm:h-64 rounded-2xl overflow-hidden border border-slate-200 dark:border-slate-800 bg-slate-100 dark:bg-slate-900"></div>
                    @endif

                    <div class="relative mt-2 pb-4">
                        <div class="absolute left-4 top-0 bottom-2 w-[3px] bg-gradient-to-b from-primary via-primary/40 to-primary/10 rounded-full"></div>
                        <div class="space-y-6 pl-8">
                            @forelse($steps as $index => $step)
                                <div class="relative">
                                    <div class="absolute -left-5 top-1 h-6 w-6 rounded-full bg-primary shadow-lg shadow-primary/40 flex items-center justify-center text-[11px] font-bold text-slate-900">
                                        {{ $step['order'] ?? $index + 1 }}
                                    </div>
                                    <div class="rounded-2xl bg-slate-50 border border-slate-200 px-3.5 py-2.5 dark:bg-slate-900/80 dark:border-slate-800 transition-colors duration-150">
                                        <div class="flex items-center justify-between gap-2">
                                            <p class="text-xs font-semibold text-slate-900 dark:text-slate-50">
                                                @if(!empty($step['place_id']))
                                                    <a href="{{ route('places.show', $step['place_id']) }}" class="hover:text-primary transition-colors">{{ $step['title'] ?? 'Étape' }}</a>
                                                @else
                                                    {{ $step['title'] ?? 'Étape' }}
                                                @endif
                                                @if(!empty($step['category']))
                                                    <span class="ml-1 text-[10px] font-normal text-slate-500 dark:text-slate-400">· {{ $step['category'] }}</span>
                                                @endif
                                            </p>
                                            <p class="text-[10px] text-slate-500 dark:text-slate-400 flex items-center gap-1">
                                                <span class="material-symbols-outlined text-[14px]">timer</span>
                                                {{ ($step['visit_minutes'] ?? 0) + ($step['travel_minutes'] ?? 0) }} min
                                            </p>
                                        </div>
                                        <p class="mt-1 text-[11px] text-slate-600 dark:text-slate-400 line-clamp-2">
                                            {{ $step['address'] ?? 'Adresse à venir' }}
                                        </p>
                                    </div>
                                    @if($index < count($steps) - 1)
                                        <div class="mt-1 flex gap-2 text-[10px] text-slate-500 dark:text-slate-500">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-100 border border-slate-200 dark:bg-slate-900/80 dark:border-slate-800">
                                                <span class="material-symbols-outlined text-[14px]">directions_walk</span>
                                                {{ $step['travel_minutes'] ?? 0 }} min de marche
                                            </span>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-100 border border-slate-200 dark:bg-slate-900/80 dark:border-slate-800">
                                                <span class="material-symbols-outlined text-[14px]">payments</span>
                                                {{ number_format($step['cost_eur'] ?? 0, 2, ',', ' ') }} €
                                            </span>
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    Aucun lieu n’a pu être sélectionné avec ces paramètres.
                                </p>
                            @endforelse
                        </div>
                    </div>
                    @if(!empty($steps))
                        @php
                            $addresses = collect($steps)->pluck('address')->filter(fn ($a) => $a && $a !== 'Adresse à venir')->values()->all();
                            $gmParcoursUrl = count($addresses) >= 2
                                ? 'https://www.google.com/maps/dir/?api=1&origin=' . urlencode($addresses[0]) . '&destination=' . urlencode($addresses[count($addresses) - 1]) . (count($addresses) > 2 ? '&waypoints=' . implode('|', array_map('urlencode', array_slice($addresses, 1, -1))) : '') . '&travelmode=walking'
                                : (count($addresses) === 1 ? 'https://www.google.com/maps/dir/?api=1&destination=' . urlencode($addresses[0]) . '&travelmode=walking' : null);
                            $wazeParcoursUrl = !empty($addresses) ? 'https://waze.com/ul?q=' . urlencode($addresses[0]) . '&navigate=yes' : null;
                        @endphp
                        <div class="mt-4 flex flex-wrap gap-2 justify-end">
                            <a href="{{ route('map.index') }}" class="inline-flex items-center gap-2 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700 px-4 py-2.5 text-xs font-semibold transition-transform duration-150 hover:-translate-y-0.5">
                                <span class="material-symbols-outlined text-[18px]">explore</span>
                                Voir sur la carte
                            </a>
                            @if($gmParcoursUrl)
                                <a href="{{ $gmParcoursUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-full bg-primary text-slate-900 px-5 py-2.5 text-xs font-semibold shadow-lg shadow-primary/30 transition-transform duration-150 hover:-translate-y-0.5">
                                    <span class="material-symbols-outlined text-[18px]">map</span>
                                    Google Maps
                                </a>
                            @endif
                            @if($wazeParcoursUrl)
                                <a href="{{ $wazeParcoursUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-full bg-primary/80 text-slate-900 px-5 py-2.5 text-xs font-semibold shadow-lg shadow-primary/20 transition-transform duration-150 hover:-translate-y-0.5">
                                    <span class="material-symbols-outlined text-[18px]">directions_car</span>
                                    Waze
                                </a>
                            @endif
                            @if(!$gmParcoursUrl && !$wazeParcoursUrl)
                                <a href="{{ route('map.index') }}" class="inline-flex items-center gap-2 rounded-full bg-primary text-slate-900 px-5 py-2.5 text-xs font-semibold shadow-lg shadow-primary/30 transition-transform duration-150 hover:-translate-y-0.5">
                                    <span class="material-symbols-outlined text-[18px]">play_circle</span>
                                    Démarrer ce parcours
                                </a>
                            @endif
                        </div>
                    @endif
                @else
                    <div class="mt-4 space-y-3">
                        <x-ui.skeleton class="h-4 w-1/2"></x-ui.skeleton>
                        <x-ui.skeleton class="h-16 w-full"></x-ui.skeleton>
                        <x-ui.skeleton class="h-16 w-full"></x-ui.skeleton>
                    </div>
                    <p class="mt-3 text-[11px] text-slate-500 dark:text-slate-400">
                        Renseigne les paramètres à gauche pour générer un premier parcours.
                    </p>
                @endif
            </x-ui.card>
        </div>
    </div>
    @if(!empty($itinerary) && !empty(($itinerary->result_json ?? [])['steps']))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (!window.L) return;
                const el = document.getElementById('itinerary-map');
                if (!el) return;
                const steps = @json(($itinerary->result_json ?? [])['steps'] ?? []);
                const points = steps.filter(s => s.lat && s.lng).map(s => [s.lat, s.lng]);
                if (!points.length) return;

                const map = L.map(el, { zoomControl: false, scrollWheelZoom: false });
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors',
                }).addTo(map);
                L.control.zoom({ position: 'bottomright' }).addTo(map);

                const line = L.polyline(points, { color: '#13ecec', weight: 4, opacity: 0.9, dashArray: '2 8', lineCap: 'round' }).addTo(map);
                steps.forEach((s, i) => {
                    if (!s.lat || !s.lng) return;
                    const icon = L.divIcon({
                        html: `<div style="width:28px;height:28px;border-radius:50%;background:#13ecec;border:2px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;font:700 12px/1 system-ui;color:#0f172a;">${s.order || i + 1}</div>`,
                        className: 'camino-marker-wrapper',
                        iconSize: [28, 28],
                        iconAnchor: [14, 14],
                    });
                    L.marker([s.lat, s.lng], { icon })
                        .addTo(map)
                        .bindPopup(`<div class="text-[11px] font-sans"><p class="font-semibold">${s.order || i + 1}. ${s.title}</p><p class="text-slate-500">${s.address || ''}</p><p class="text-slate-500">${s.visit_minutes || 0} min sur place${s.travel_minutes ? ' · ' + s.travel_minutes + ' min de marche' : ''}</p></div>`);
                });
                map.fitBounds(line.getBounds(), { padding: [24, 24] });
            });
        </script>
    @endif
</x-app-layout>