@php
    /** @var \Illuminate\Database\Eloquent\Collection|\App\Models\Place[] $places */
    /** @var \Illuminate\Database\Eloquent\Collection|\App\Models\Event[] $events */
@endphp

<x-app-layout>
    <div
        x-data="{ drawerOpen: false }"
        class="relative h-[calc(100vh-4rem)] w-full overflow-hidden bg-slate-950 text-slate-100 dark:bg-camino-background-dark"
        @keydown.escape.window="drawerOpen = false"
    >
        <!-- Leaflet map background -->
        <div id="camino-map" class="absolute inset-0 z-0"></div>

        <!-- Gradient overlay for depth -->
        <div class="pointer-events-none absolute inset-0 z-0 bg-gradient-to-b from-black/40 via-transparent to-black/60"></div>

        <!-- Top search + chips -->
        <div class="relative z-10 px-4 pt-4 space-y-3 max-w-3xl mx-auto">
            <div class="flex items-center gap-3">
                <div class="flex-1 flex items-center bg-white/90 border border-slate-200 rounded-2xl px-4 py-3 shadow-lg shadow-slate-900/10 backdrop-blur-xl dark:bg-slate-900/80 dark:border-slate-700/80 dark:shadow-black/40 transition-colors duration-150">
                    <span class="material-symbols-outlined mr-2 text-[20px] text-slate-500 dark:text-slate-400">search</span>
                    <input
                        id="map-search"
                        type="text"
                        placeholder="Rechercher un lieu, un quartier..."
                        class="bg-transparent border-0 focus:ring-0 text-sm text-slate-900 placeholder:text-slate-400 dark:text-slate-100 dark:placeholder:text-slate-500 w-full"
                        autocomplete="off"
                    />
                    <span class="material-symbols-outlined text-primary text-[20px]">tune</span>
                </div>
                <div class="hidden sm:flex items-center justify-center rounded-2xl bg-white/90 border border-slate-200 p-2 shadow-lg shadow-slate-900/10 dark:bg-slate-900/80 dark:border-slate-700/80 dark:shadow-black/40 transition-colors duration-150">
                    <span class="material-symbols-outlined text-primary text-[22px]">person</span>
                </div>
            </div>

            <div class="flex gap-2 overflow-x-auto hide-scrollbar pb-1 text-xs" id="map-filters">
                <x-ui.chip :active="true" data-filter="all">Tous</x-ui.chip>
                <x-ui.chip icon="museum" data-filter="musees">Musées</x-ui.chip>
                <x-ui.chip icon="castle" data-filter="monuments">Monuments</x-ui.chip>
                <x-ui.chip icon="park" data-filter="parcs">Parcs / jardins</x-ui.chip>
                <x-ui.chip icon="apartment" data-filter="lieux-culturels">Lieux culturels</x-ui.chip>
                <x-ui.chip icon="restaurant" data-filter="restauration">Restauration</x-ui.chip>
                <x-ui.chip icon="brush" data-filter="street-art">Street Art</x-ui.chip>
                <x-ui.chip icon="theaters" data-filter="spectacles">Spectacles</x-ui.chip>
                <x-ui.chip icon="timeline" data-filter="itineraires">Itinéraires</x-ui.chip>
                <x-ui.chip icon="loyalty" data-filter="free">Gratuit</x-ui.chip>
            </div>
        </div>

        <!-- Floating map controls : petite icône « À proximité » + layers + near_me -->
        <div class="absolute right-0 bottom-24 sm:bottom-24 z-50 flex flex-col gap-3 pr-2 sm:pr-3">
            <button
                type="button"
                @click="drawerOpen = !drawerOpen"
                class="flex size-11 items-center justify-center rounded-full bg-slate-900/90 border border-slate-700 text-slate-100 shadow-xl backdrop-blur-sm hover:border-primary hover:text-primary transition"
                :class="drawerOpen && 'bg-primary/90 text-slate-900 border-primary'"
                title="À proximité"
            >
                <span class="material-symbols-outlined text-[22px] font-normal" style="font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;">list</span>
            </button>
            <button class="flex size-11 items-center justify-center rounded-full bg-slate-900/90 border border-slate-700 text-slate-100 shadow-xl backdrop-blur-sm hover:border-primary hover:text-primary transition">
                <span class="material-symbols-outlined text-[22px] font-normal" style="font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;">layers</span>
            </button>
            <button class="flex size-11 items-center justify-center rounded-full bg-slate-900/90 border border-slate-700 text-slate-100 shadow-xl backdrop-blur-sm hover:border-primary hover:text-primary transition" data-action="geolocate">
                <span class="material-symbols-outlined text-[22px] font-normal" style="font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;">near_me</span>
            </button>
        </div>

        <!-- Desktop sidebar (filters) -->
        <aside class="hidden md:flex md:flex-col absolute left-4 top-28 bottom-24 z-20 w-80">
            <x-ui.card glass padding="md" class="h-full flex flex-col bg-white/95 border border-slate-200/80 dark:bg-slate-900/85 dark:border-slate-800/80 transition-colors duration-150">
                <div class="mb-3">
                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-500">Filtres</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Affinez par type de lieu, budget, ouverture.</p>
                </div>

                <div class="space-y-4 text-xs flex-1 overflow-y-auto hide-scrollbar pr-1">
                    <div>
                        <p class="font-medium text-slate-800 dark:text-slate-200 mb-2">Budget</p>
                        <select class="w-full rounded-2xl border-slate-200 bg-white/90 text-[11px] text-slate-900 dark:border-slate-700 dark:bg-slate-900/80 dark:text-slate-100">
                            <option>Tout</option>
                            <option>Gratuit</option>
                            <option>€</option>
                            <option>€€</option>
                            <option>€€€</option>
                        </select>
                    </div>

                    <div>
                        <p class="font-medium text-slate-800 dark:text-slate-200 mb-1">Ouvert maintenant</p>
                        <label class="inline-flex items-center gap-2 text-xs text-slate-600 dark:text-slate-300">
                            <input type="checkbox" class="rounded border-slate-300 text-primary focus:ring-primary bg-white dark:border-slate-600 dark:bg-slate-900" />
                            Oui
                        </label>
                    </div>

                    <div>
                        <p class="font-medium text-slate-800 dark:text-slate-200 mb-2">Autour de moi</p>
                        <x-ui.button variant="outline" size="sm" class="w-full justify-center border-slate-300 text-slate-700 hover:border-primary hover:text-primary dark:border-slate-700 dark:text-slate-200 transition-colors duration-150">
                            <span class="material-symbols-outlined text-[16px]">my_location</span>
                            Utiliser ma position
                        </x-ui.button>
                    </div>

                    <div class="pt-2 border-t border-slate-200 dark:border-slate-800">
                        <p class="font-medium text-slate-800 dark:text-slate-200 mb-2">Événements en direct</p>
                        <div class="space-y-2 max-h-40 overflow-y-auto hide-scrollbar pr-1">
                            @forelse($events as $event)
                                <div class="rounded-2xl bg-slate-100 border border-slate-200 px-3 py-2 dark:bg-slate-900/80 dark:border-slate-800 transition-colors duration-150">
                                    <p class="text-[11px] font-medium text-slate-800 dark:text-slate-50 truncate">
                                        {{ $event->title }}
                                    </p>
                                    <p class="text-[10px] text-slate-500 dark:text-slate-400">
                                        {{ optional($event->start_at)->format('d/m H:i') }}
                                    </p>
                                </div>
                            @empty
                                <p class="text-[11px] text-slate-500 dark:text-slate-400">Aucun événement à venir.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </x-ui.card>
        </aside>

        <!-- Bottom sheet façon Stitch : visible uniquement au clic sur l'icône list -->
        <div
            x-show="drawerOpen"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="translate-y-full opacity-0"
            x-transition:enter-end="translate-y-0 opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="translate-y-0 opacity-100"
            x-transition:leave-end="translate-y-full opacity-0"
            class="fixed inset-x-0 bottom-24 sm:bottom-0 z-50 flex flex-col items-center"
        >
            <div class="relative w-full max-w-md bg-white/95 dark:bg-slate-950/95 backdrop-blur-xl border border-slate-200/80 dark:border-slate-700/80 rounded-t-3xl shadow-[0_-10px_40px_rgba(0,0,0,0.25)] dark:shadow-[0_-16px_50px_rgba(0,0,0,0.7)] pb-6 max-h-[70vh] flex flex-col text-slate-900 dark:text-slate-50">
                <div class="w-full flex justify-center py-3">
                    <div class="w-12 h-1.5 bg-slate-200 dark:bg-slate-600 rounded-full"></div>
                </div>
                <button
                    type="button"
                    @click="drawerOpen = false"
                    class="absolute -top-4 right-4 flex h-9 w-9 items-center justify-center rounded-full bg-slate-900/90 text-slate-100 shadow-xl border border-slate-700 hover:bg-slate-800 hover:text-primary hover:border-primary transition-colors"
                    title="Fermer"
                >
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>

                <div class="px-5 flex-1 overflow-y-auto pt-1">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-slate-50">À proximité</h2>
                        <button type="button" class="text-primary text-sm font-semibold hover:text-cyan-400 transition">Voir tout</button>
                    </div>
                    <div id="nearby-loading" class="hidden items-center gap-2 text-xs text-slate-400 dark:text-slate-500 mb-2">
                        <span class="material-symbols-outlined animate-spin text-[16px]">progress_activity</span>
                        <span>Chargement des lieux…</span>
                    </div>
                    <div class="space-y-3" id="nearby-list">
                        @forelse($places->take(10) as $place)
                            <a href="{{ route('places.show', $place) }}" class="flex gap-3 p-3 rounded-2xl hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                                <div class="relative shrink-0 overflow-hidden bg-gradient-to-br from-slate-200 to-slate-100 dark:from-slate-700 dark:to-slate-800 flex items-center justify-center" style="width:56px;height:56px;border-radius:9999px;">
                                    <span class="material-symbols-outlined text-3xl text-slate-400 dark:text-slate-500">museum</span>
                                </div>
                                <div class="flex flex-col justify-center flex-1 min-w-0">
                                    <div class="flex justify-between items-start gap-2">
                                        <h3 class="font-bold text-base leading-tight text-slate-900 dark:text-slate-50 line-clamp-2">{{ $place->title }}</h3>
                                        @auth
                                            <span class="material-symbols-outlined text-slate-300 dark:text-slate-500 group-hover:text-primary shrink-0" style="font-variation-settings: 'FILL' 0">favorite</span>
                                        @endauth
                                    </div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 flex items-center gap-2">
                                        <span>{{ $place->category->name ?? 'Lieu culturel' }} • {{ Str::limit($place->address ?? 'Adresse à venir', 25) }}</span>
                                        @if($place->is_free)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 text-[10px] font-semibold">GRATUIT</span>
                                        @else
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full bg-primary/15 text-primary text-[10px] font-semibold">INDOOR</span>
                                        @endif
                                    </p>
                                    <div class="flex items-center gap-3 mt-2">
                                        <span class="flex items-center text-primary text-xs font-bold">
                                            <span class="material-symbols-outlined text-[14px] mr-0.5">near_me</span>
                                            —
                                        </span>
                                        @if($place->reviews_avg_rating ?? null)
                                            <span class="flex items-center text-amber-500 text-xs font-bold">
                                                <span class="material-symbols-outlined text-[14px] mr-0.5 text-amber-500">star</span>
                                                {{ number_format($place->reviews_avg_rating, 1) }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </a>
                        @empty
                            <div class="py-8 text-center text-slate-500 dark:text-slate-400 text-sm">
                                <span class="material-symbols-outlined text-4xl mb-2 block">place</span>
                                Aucun lieu sur la carte pour l'instant.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (!window.L) return;

            const mapElement = document.getElementById('camino-map');
            if (!mapElement) return;

            const paris = [48.8566, 2.3522];
            const map = L.map(mapElement, {
                zoomControl: false,
            }).setView(paris, 13);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(map);

            L.control.zoom({ position: 'bottomright' }).addTo(map);

            const events = @json($events);
            const apiUrl = @js(url('/api/v1/pois'));
            let placeMarkers = [];
            let markerById = {};
            const loadingEl = document.getElementById('nearby-loading');
            const searchInput = document.getElementById('map-search');
            const filterChips = document.querySelectorAll('#map-filters [data-filter]');
            let activeFilter = 'all';
            let searchQuery = '';

            const categoryToIcon = {
                'Musée': 'museum',
                'Galerie': 'palette',
                'Concert': 'music_note',
                'Spectacle': 'theaters',
                'Street Art': 'brush',
                'Monument': 'castle', // 'landmark' s'affiche en texte "MARK"
                'Parc / Jardin': 'park',
                'Lieu culturel': 'apartment',
                'Restauration': 'restaurant',
                'Itinéraire': 'route',
                'Événement culturel': 'event',
                'Exposition': 'image',
                'Théâtre': 'theaters',
            };

            function getPlaceIcon(place) {
                const name = (place.category && place.category.name)
                    ? place.category.name
                    : (place.category_name || '');
                const symbol = categoryToIcon[name] || 'place';
                const html = `<div style="width:36px;height:36px;border-radius:50%;background:#13ecec;border:2px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,0.3);display:flex;align-items:center;justify-content:center;">
                    <span class="material-symbols-outlined" style="font-size:20px;color:#0f172a;font-family:'Material Symbols Outlined',sans-serif;font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;">${symbol}</span>
                </div>`;
                return L.divIcon({
                    html,
                    className: 'camino-marker-wrapper border-0 bg-transparent',
                    iconSize: [36, 36],
                    iconAnchor: [18, 18],
                });
            }

            function clearPlaceMarkers() {
                placeMarkers.forEach(marker => map.removeLayer(marker));
                placeMarkers = [];
                markerById = {};
            }

            function renderNearbyList(list) {
                const container = document.getElementById('nearby-list');
                if (!container) return;

                if (!list.length) {
                    container.innerHTML = `
                        <div class="py-8 text-center text-slate-500 dark:text-slate-400 text-sm">
                            <span class="material-symbols-outlined text-4xl mb-2 block">place</span>
                            Aucun lieu sur la carte pour l'instant.
                        </div>
                    `;
                    return;
                }

                container.innerHTML = list.map(place => {
                    const isFree = !!place.is_free;
                    const badge = isFree
                        ? '<span class="inline-flex items-center px-1.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 text-[10px] font-semibold">GRATUIT</span>'
                        : '<span class="inline-flex items-center px-1.5 py-0.5 rounded-full bg-primary/15 text-primary text-[10px] font-semibold">INDOOR</span>';
                    const ratingHtml = place.rating
                        ? `<span class="flex items-center text-amber-500 text-xs font-bold">
                                <span class="material-symbols-outlined text-[14px] mr-0.5 text-amber-500">star</span>
                                ${Number(place.rating).toFixed(1)}
                           </span>`
                        : '';
                    const categoryName = place.category && place.category.name
                        ? place.category.name
                        : (place.category_name || 'Lieu culturel');
                    const iconSymbol = categoryToIcon[categoryName] || 'place';
                    const coverUrl = place.media && place.media.cover ? place.media.cover : '';
                    const address = place.address || 'Adresse à venir';

                    return `
                        <a href="{{ url('/lieux') }}/${place.id}" data-place-id="${place.id}" class="flex gap-3 p-3 rounded-2xl hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                            <div class="relative shrink-0 overflow-hidden bg-gradient-to-br from-slate-200 to-slate-100 dark:from-slate-700 dark:to-slate-800 flex items-center justify-center" style="width:56px;height:56px;border-radius:9999px;">
                                ${
                                    coverUrl
                                        ? `<img src="${coverUrl}" alt="${place.title}" loading="lazy" style="width:56px;height:56px;object-fit:cover;" />`
                                        : `<span class="material-symbols-outlined text-3xl text-slate-400 dark:text-slate-500" style="font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;">${iconSymbol}</span>`
                                }
                            </div>
                            <div class="flex flex-col justify-center flex-1 min-w-0">
                                <div class="flex justify-between items-start gap-2">
                                    <h3 class="font-bold text-base leading-tight text-slate-900 dark:text-slate-50 line-clamp-2">${place.title}</h3>
                                    <span class="material-symbols-outlined text-slate-300 dark:text-slate-500 group-hover:text-primary shrink-0" style="font-variation-settings: 'FILL' 0">favorite</span>
                                </div>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 flex items-center gap-2">
                                    <span>${categoryName} • ${address}</span>
                                    ${badge}
                                </p>
                                <div class="flex items-center gap-3 mt-2">
                                    <span class="flex items-center text-primary text-xs font-bold">
                                        <span class="material-symbols-outlined text-[14px] mr-0.5">near_me</span>
                                        —
                                    </span>
                                    ${ratingHtml}
                                </div>
                            </div>
                        </a>
                    `;
                }).join('');

                // Survol des éléments de la liste → ouverture du popup sur la carte
                const items = container.querySelectorAll('[data-place-id]');
                items.forEach(link => {
                    const id = link.getAttribute('data-place-id');
                    const marker = markerById[id];
                    if (!marker) return;

                    link.addEventListener('mouseenter', () => {
                        marker.openPopup();
                    });
                    link.addEventListener('mouseleave', () => {
                        marker.closePopup();
                    });
                    link.addEventListener('click', (event) => {
                        // laissez le lien s'ouvrir, mais centrez aussi la carte
                        const latLng = marker.getLatLng();
                        if (latLng) {
                            map.flyTo(latLng, Math.max(map.getZoom(), 14), { duration: 0.35 });
                        }
                    });
                });
            }

            function renderPlaces(list) {
                clearPlaceMarkers();

                list.forEach(place => {
                    if (!place.lat || !place.lng) return;
                    const marker = L.marker([place.lat, place.lng], {
                        icon: getPlaceIcon(place),
                    }).addTo(map);

                    placeMarkers.push(marker);
                    markerById[String(place.id)] = marker;

                    const priceBadge = place.is_free
                        ? '<span class="inline-flex items-center px-1.5 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-medium">Gratuit</span>'
                        : '<span class="inline-flex items-center px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-700 text-[10px] font-medium">' +
                            (place.price_level ? '€'.repeat(place.price_level) : '€€') +
                          '</span>';

                    const coverUrl = place.media && place.media.cover ? place.media.cover : '';
                    const popupHtml = `
                        <div class="text-[11px] font-sans max-w-[220px]">
                            ${
                                coverUrl
                                    ? `<div class="mb-1 overflow-hidden rounded-lg border border-slate-200">
                                            <img src="${coverUrl}" alt="${place.title}" loading="lazy" class="w-full h-20 object-cover" />
                                       </div>`
                                    : ''
                            }
                            <div class="flex items-center justify-between gap-2 mb-1">
                                <p class="font-semibold text-slate-900 line-clamp-2">${place.title}</p>
                                ${priceBadge}
                            </div>
                            <p class="text-slate-500 mb-1 line-clamp-2">${place.address || ''}</p>
                            <a href="{{ url('/lieux') }}/${place.id}" class="inline-flex items-center gap-1 text-[11px] text-cyan-600 hover:text-cyan-700">
                                Voir le lieu
                                <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.5 12h15m0 0L13.5 6.75M19.5 12l-6 5.25" />
                                </svg>
                            </a>
                        </div>
                    `;

                    marker.bindPopup(popupHtml);
                });

                renderNearbyList(list);
            }

            function debounce(fn, delay) {
                let timeout;
                return function (...args) {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => fn.apply(this, args), delay);
                };
            }

            async function loadPlacesForCurrentView() {
                const bounds = map.getBounds();
                const params = new URLSearchParams({
                    bbox: `${bounds.getSouth()},${bounds.getWest()},${bounds.getNorth()},${bounds.getEast()}`,
                    limit: '80',
                });

                if (searchQuery.trim().length >= 2) {
                    params.set('q', searchQuery.trim());
                }

                // Filtres haut : Musées / Monuments / Street Art / Gratuit
                const categoryByFilter = {
                    'musees': 'musee',
                    'monuments': 'monument',
                    'parcs': 'parc-jardin',
                    'lieux-culturels': 'lieu-culturel',
                    'restauration': 'restauration',
                    'street-art': 'street-art',
                    'spectacles': 'evenement-culturel',
                    'itineraires': 'itineraire',
                };
                if (categoryByFilter[activeFilter]) {
                    params.set('category_slugs', categoryByFilter[activeFilter]);
                }
                if (activeFilter === 'free') {
                    params.set('free', '1');
                }

                try {
                    if (loadingEl) loadingEl.classList.remove('hidden');
                    const response = await fetch(`${apiUrl}?${params.toString()}`);
                    if (!response.ok) return;
                    const json = await response.json();
                    const list = Array.isArray(json.data) ? json.data : [];
                    renderPlaces(list);
                } catch (e) {
                    console.error('Erreur lors du chargement des POI', e);
                } finally {
                    if (loadingEl) loadingEl.classList.add('hidden');
                }
            }

            // Événements (markers simples, non filtrés par bbox pour l'instant)
            events.forEach(event => {
                if (!event.lat || !event.lng) return;
                const marker = L.circleMarker([event.lat, event.lng], {
                    radius: 6,
                    color: '#a855f7',
                    weight: 1.2,
                    fillColor: '#d946ef',
                    fillOpacity: 0.9,
                }).addTo(map);

                const popupHtml = `
                    <div class="text-[11px] font-sans">
                        <p class="font-semibold text-slate-900 mb-1">${event.title}</p>
                        <p class="text-slate-500 mb-1">${event.start_at || ''}</p>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full bg-fuchsia-100 text-fuchsia-700 text-[10px] font-medium">
                            Événement
                        </span>
                    </div>
                `;

                marker.bindPopup(popupHtml);
            });

            // Bouton "autour de moi" (géolocalisation navigateur)
            const geoButton = document.querySelector('[data-action="geolocate"]');
            if (geoButton && 'geolocation' in navigator) {
                geoButton.addEventListener('click', () => {
                    navigator.geolocation.getCurrentPosition(
                        (pos) => {
                            const coords = [pos.coords.latitude, pos.coords.longitude];
                            map.setView(coords, 15);
                        },
                        (err) => {
                            console.warn('Géolocalisation impossible', err);
                            alert('Impossible de récupérer votre position.');
                        },
                        { enableHighAccuracy: true, timeout: 10000 }
                    );
                });
            }

            // Filtres chips (Tous / Musées / Monuments / Street Art / Gratuit)
            if (filterChips.length) {
                filterChips.forEach(chip => {
                    chip.addEventListener('click', () => {
                        const value = chip.getAttribute('data-filter') || 'all';
                        activeFilter = value;

                        filterChips.forEach(c => {
                            const isActive = c === chip;
                            if (isActive) {
                                c.setAttribute('data-active', 'true');
                            } else {
                                c.removeAttribute('data-active');
                            }

                            // Micro-état visuel : couleur différente pour le filtre actif
                            c.classList.toggle('bg-primary/90', isActive);
                            c.classList.toggle('text-slate-900', isActive);
                        });

                        loadPlacesForCurrentView();
                    });
                });
            }

            // Barre de recherche (titre + adresse)
            if (searchInput) {
                searchInput.addEventListener('input', debounce((event) => {
                    searchQuery = event.target.value || '';
                    loadPlacesForCurrentView();
                }, 400));
            }

            // Chargement initial + sur mouvement de carte
            const debouncedLoad = debounce(loadPlacesForCurrentView, 400);
            map.whenReady(loadPlacesForCurrentView);
            map.on('moveend', debouncedLoad);
        });
    </script>
</x-app-layout>

