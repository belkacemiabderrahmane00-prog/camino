<x-app-layout title="Carte culturelle" :fullscreen="true">
    <x-slot name="actions">
        <x-weather-chip :forecast="$forecast" label="Paris" class="hidden lg:inline-flex !py-1 !pr-3" />
    </x-slot>

    <div id="map-page" class="absolute inset-0" x-data="caminoMap()" x-init="init()">
        {{-- Carte --}}
        <div id="camino-map" class="absolute inset-0 z-0"></div>

        {{-- Recherche + filtres (haut) --}}
        <div class="absolute top-[4.6rem] inset-x-0 z-[500] pointer-events-none">
            <div class="max-w-7xl mx-auto px-4 sm:px-6">
                <div class="md:max-w-2xl space-y-2 pointer-events-auto">
                    <div class="card flex items-center gap-2 pl-4 pr-2 py-1.5">
                        <span class="material-symbols-outlined text-ink-muted">search</span>
                        <input x-model.debounce.400ms="query" @input="load()" type="search" placeholder="Rechercher un lieu, une adresse…" class="flex-1 border-0 bg-transparent focus:ring-0 text-sm placeholder:text-ink-muted/70" autocomplete="off">
                        <span x-show="loading" class="material-symbols-outlined text-ink-muted animate-spin" style="font-size:18px">progress_activity</span>
                        <button @click="locate()" class="btn btn-icon btn-ghost" title="Autour de moi"><span class="material-symbols-outlined">my_location</span></button>
                    </div>
                    <div class="flex gap-2 overflow-x-auto hide-scrollbar pb-1">
                        <template x-for="f in filters" :key="f.key">
                            <button @click="setFilter(f.key)" class="chip shrink-0" :data-active="filter === f.key">
                                <span class="material-symbols-outlined" style="font-size:16px" x-text="f.icon"></span>
                                <span x-text="f.label"></span>
                            </button>
                        </template>
                        <select x-model="budget" @change="load()" class="chip shrink-0 !pr-8 appearance-none bg-white" style="background-image:none">
                            <option value="">Tout budget</option>
                            <option value="free">Gratuit</option>
                            <option value="1">Jusqu'à €</option>
                            <option value="2">Jusqu'à €€</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- Panneau latéral (desktop) --}}
        <aside class="hidden md:flex flex-col absolute top-[10.5rem] bottom-6 right-6 z-[500] w-[380px] card overflow-hidden">
            <div class="px-5 pt-4 pb-3 border-b border-ink/5 flex items-center justify-between gap-3">
                <div>
                    <p class="eyebrow">Dans cette zone</p>
                    <p class="text-sm font-semibold" x-text="countLabel()"></p>
                </div>
                <div class="flex gap-1">
                    <button @click="listMode = 'places'" class="btn btn-sm" :class="listMode === 'places' ? 'btn-ink' : 'btn-ghost'">Lieux</button>
                    <button @click="listMode = 'alerts'" class="btn btn-sm" :class="listMode === 'alerts' ? 'btn-ink' : 'btn-ghost'">Alertes <span x-show="alerts.length" class="ml-1 rounded-full bg-coral text-white text-[10px] px-1.5" x-text="alerts.length"></span></button>
                </div>
            </div>
            <div class="flex-1 overflow-y-auto p-3 space-y-2" x-ref="list">
                <template x-if="listMode === 'places'">
                    <div class="space-y-2">
                        <template x-for="p in places" :key="p.id">
                            <a :href="`{{ url('/lieux') }}/${p.id}`" @mouseenter="highlight(p.id)" @mouseleave="highlight(null)" class="flex gap-3 p-2 rounded-2xl hover:bg-paper transition group">
                                <div class="w-20 h-16 rounded-xl overflow-hidden shrink-0 placeholder-cover flex items-center justify-center" :style="`--c1:${style(p).color};--c2:#12161C`">
                                    <template x-if="p.media && p.media.cover"><img :src="p.media.cover" :alt="p.title" loading="lazy" class="w-full h-full object-cover"></template>
                                    <template x-if="!(p.media && p.media.cover)"><span class="material-symbols-outlined text-white/80" x-text="style(p).icon"></span></template>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-[10px] font-semibold" :style="`color:${style(p).color}`" x-text="p.category ? p.category.name : 'Lieu'"></p>
                                    <p class="text-sm font-semibold leading-snug line-clamp-2 group-hover:text-coral transition" x-text="p.title"></p>
                                    <p class="text-[11px] text-ink-muted line-clamp-1" x-text="p.address || ''"></p>
                                    <div class="flex items-center gap-2 mt-0.5 text-[10px]">
                                        <span x-show="p.is_free" class="badge badge-free !py-0.5">Gratuit</span>
                                        <span x-show="!p.is_free && p.price_level" class="text-ink-muted font-semibold" x-text="'€'.repeat(p.price_level || 0)"></span>
                                        <span x-show="p.rating" class="text-amber-600 inline-flex items-center gap-0.5"><span class="material-symbols-outlined filled" style="font-size:12px">star</span><span x-text="p.rating"></span></span>
                                        <span x-show="p.alerts" class="badge badge-alert !py-0.5"><span class="material-symbols-outlined" style="font-size:12px">campaign</span><span x-text="p.alerts"></span></span>
                                    </div>
                                </div>
                            </a>
                        </template>
                        <p x-show="!loading && !places.length" class="p-6 text-center text-sm text-ink-muted">Aucun lieu ici avec ces filtres. Déplace la carte ou change de filtre.</p>
                    </div>
                </template>
                <template x-if="listMode === 'alerts'">
                    <div class="space-y-2">
                        <template x-for="a in alerts" :key="a.id">
                            <button @click="focusAlert(a)" class="w-full text-left flex gap-3 p-3 rounded-2xl hover:bg-paper transition">
                                <span class="h-9 w-9 rounded-full flex items-center justify-center shrink-0" :style="`background:${a.color}22;color:${a.color}`"><span class="material-symbols-outlined" style="font-size:18px" x-text="a.icon"></span></span>
                                <div class="min-w-0 text-sm">
                                    <p class="font-semibold leading-snug" x-text="a.title"></p>
                                    <p class="text-xs text-ink-muted"><span x-text="a.label"></span><template x-if="a.place"><span> · <span x-text="a.place.title"></span></span></template> · expire dans <span x-text="a.expires_in"></span></p>
                                </div>
                            </button>
                        </template>
                        <div x-show="!alerts.length" class="p-6 text-center text-sm text-ink-muted">
                            <p>Aucune alerte dans cette zone.</p>
                            <button @click="$dispatch('open-alert', center())" class="btn btn-sm btn-primary mt-3"><span class="material-symbols-outlined" style="font-size:16px">campaign</span>Signaler ici</button>
                        </div>
                    </div>
                </template>
            </div>
        </aside>

        {{-- Boutons flottants --}}
        <div class="absolute right-4 md:right-[404px] bottom-24 md:bottom-6 z-[500] flex flex-col gap-2">
            <button @click="$dispatch('open-alert', center())" class="btn btn-md btn-primary shadow-float" title="Signaler un événement, une affluence, une fermeture">
                <span class="material-symbols-outlined">campaign</span><span class="hidden sm:inline">Signaler</span>
            </button>
            <button @click="zoomIn()" class="btn btn-icon btn-soft hidden md:inline-flex"><span class="material-symbols-outlined">add</span></button>
            <button @click="zoomOut()" class="btn btn-icon btn-soft hidden md:inline-flex"><span class="material-symbols-outlined">remove</span></button>
        </div>

        {{-- Feuille mobile --}}
        <div class="md:hidden absolute inset-x-0 bottom-0 z-[500] pb-20" x-show="sheet" x-cloak x-transition:enter="animate-sheet-up">
            <div class="mx-3 card max-h-[50vh] flex flex-col">
                <button @click="sheet = false" class="w-full flex justify-center pt-2 pb-1"><span class="h-1.5 w-12 rounded-full bg-ink/15"></span></button>
                <div class="px-4 pb-2 flex items-center justify-between">
                    <p class="text-sm font-semibold" x-text="countLabel()"></p>
                    <button @click="sheet = false" class="text-ink-muted"><span class="material-symbols-outlined">close</span></button>
                </div>
                <div class="overflow-y-auto px-3 pb-3 space-y-2">
                    <template x-for="p in places" :key="'m' + p.id">
                        <a :href="`{{ url('/lieux') }}/${p.id}`" class="flex gap-3 p-2 rounded-2xl hover:bg-paper">
                            <div class="w-16 h-14 rounded-xl overflow-hidden shrink-0 placeholder-cover flex items-center justify-center" :style="`--c1:${style(p).color};--c2:#12161C`">
                                <template x-if="p.media && p.media.cover"><img :src="p.media.cover" alt="" loading="lazy" class="w-full h-full object-cover"></template>
                                <template x-if="!(p.media && p.media.cover)"><span class="material-symbols-outlined text-white/80" x-text="style(p).icon"></span></template>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold leading-snug line-clamp-2" x-text="p.title"></p>
                                <p class="text-[11px] text-ink-muted line-clamp-1" x-text="(p.category ? p.category.name + ' · ' : '') + (p.address || '')"></p>
                            </div>
                        </a>
                    </template>
                </div>
            </div>
        </div>
        <button x-show="!sheet" @click="sheet = true" class="md:hidden absolute left-4 bottom-24 z-[500] btn btn-md btn-ink shadow-float">
            <span class="material-symbols-outlined">list</span><span x-text="countLabel()"></span>
        </button>

        <x-alert-modal :types="$alertTypes" />
    </div>

    @push('scripts')
    <script>
        function caminoMap() {
            const C = window.Camino;
            return {
                map: null, markers: {}, alertMarkers: [], places: [], alerts: [], loading: false,
                query: @js(request('q', '')), filter: @js(request('filtre', 'all')), budget: '', listMode: 'places', sheet: false,
                apiPois: @js(url('/api/v1/pois')), apiAlerts: @js(url('/api/v1/alerts')),
                filters: [
                    { key: 'all', label: 'Tous', icon: 'apps' },
                    { key: 'musees', label: 'Musées', icon: 'palette', slug: 'musee' },
                    { key: 'monuments', label: 'Monuments', icon: 'account_balance', slug: 'monument' },
                    { key: 'parcs', label: 'Parcs', icon: 'park', slug: 'parc-jardin' },
                    { key: 'culturels', label: 'Scènes & galeries', icon: 'theater_comedy', slug: 'lieu-culturel' },
                    { key: 'evenements', label: 'Événements', icon: 'celebration', slug: 'evenement-culturel' },
                    { key: 'restauration', label: 'Restauration', icon: 'restaurant', slug: 'restauration' },
                    { key: 'itineraires', label: 'Balades', icon: 'route', slug: 'itineraire' },
                    { key: 'free', label: 'Gratuit', icon: 'loyalty' },
                ],
                init() {
                    if (!window.L) return;
                    const params = new URLSearchParams(window.location.search);
                    const lat = parseFloat(params.get('lat')) || 48.8566, lng = parseFloat(params.get('lng')) || 2.3522, z = parseInt(params.get('z')) || 13;
                    this.map = L.map('camino-map', { zoomControl: false, attributionControl: true }).setView([lat, lng], z);
                    C.tileLayer().addTo(this.map);
                    this.map.on('moveend', C.debounce(() => this.load(), 250));
                    this.load();
                    if (params.get('locate')) this.locate();
                },
                style(p) { return C.categoryStyle(p.category ? p.category.slug : null); },
                center() { const c = this.map.getCenter(); return { lat: c.lat, lng: c.lng }; },
                zoomIn() { this.map.zoomIn(); }, zoomOut() { this.map.zoomOut(); },
                setFilter(key) { this.filter = key; this.load(); },
                countLabel() {
                    const n = this.places.length;
                    if (this.loading && !n) return 'Chargement…';
                    if (!n) return 'Aucun lieu ici';
                    return n >= 120 ? '120+ lieux (zoome pour affiner)' : `${n} lieu${n > 1 ? 'x' : ''}`;
                },
                async locate() {
                    try { const p = await C.locate(); this.map.setView([p.lat, p.lng], 15); L.circleMarker([p.lat, p.lng], { radius: 8, color: '#fff', weight: 3, fillColor: '#0F8B8D', fillOpacity: 1 }).addTo(this.map); }
                    catch (e) { alert('Impossible de récupérer ta position.'); }
                },
                async load() {
                    if (!this.map) return;
                    const b = this.map.getBounds();
                    const bbox = `${b.getSouth()},${b.getWest()},${b.getNorth()},${b.getEast()}`;
                    const params = new URLSearchParams({ bbox, limit: '120' });
                    const f = this.filters.find(x => x.key === this.filter);
                    if (f && f.slug) params.set('category_slugs', f.slug);
                    if (this.filter === 'evenements') params.set('events', '1');
                    if (this.filter === 'free' || this.budget === 'free') params.set('free', '1'); else if (this.budget) params.set('price_max', this.budget);
                    if (this.query) params.set('q', this.query);
                    this.loading = true;
                    try {
                        const [rp, ra] = await Promise.all([fetch(`${this.apiPois}?${params}`), fetch(`${this.apiAlerts}?bbox=${bbox}`)]);
                        const jp = await rp.json(); const ja = await ra.json();
                        this.places = jp.data || []; this.alerts = ja.data || [];
                        this.render();
                    } catch (e) { console.error(e); } finally { this.loading = false; }
                },
                render() {
                    Object.values(this.markers).forEach(m => m.remove()); this.markers = {};
                    this.alertMarkers.forEach(m => m.remove()); this.alertMarkers = [];
                    this.places.forEach(p => {
                        if (!p.lat || !p.lng) return;
                        const st = this.style(p);
                        const m = L.marker([p.lat, p.lng], { icon: C.placeIcon(p.category ? p.category.slug : null) }).addTo(this.map);
                        const cover = p.media && p.media.cover ? `<img src="${p.media.cover}" alt="" class="w-full h-28 object-cover rounded-t-[18px]">` : `<div class="w-full h-20 placeholder-cover rounded-t-[18px] flex items-center justify-center" style="--c1:${st.color};--c2:#12161C"><span class="material-symbols-outlined text-white/80" style="font-size:32px">${st.icon}</span></div>`;
                        m.bindPopup(`${cover}<div class="p-3"><p class="text-[10px] font-semibold" style="color:${st.color}">${C.escapeHtml(p.category ? p.category.name : 'Lieu')}</p><p class="font-semibold text-sm leading-snug">${C.escapeHtml(p.title)}</p><p class="text-[11px] text-ink-muted mt-0.5">${C.escapeHtml(p.address || '')}</p><div class="mt-2 flex items-center justify-between"><span class="text-[11px] ${p.is_free ? 'text-emerald-700 font-semibold' : 'text-ink-muted'}">${p.is_free ? 'Gratuit' : (p.price_level ? '€'.repeat(p.price_level) : '')}</span><a href="{{ url('/lieux') }}/${p.id}" class="btn btn-sm btn-ink">Voir le lieu</a></div></div>`, { maxWidth: 280 });
                        this.markers[p.id] = m;
                    });
                    this.alerts.forEach(a => {
                        const m = L.marker([a.lat, a.lng], { icon: C.alertIcon(a.color, a.icon), zIndexOffset: 500 }).addTo(this.map);
                        m.bindPopup(`<div class="p-3"><p class="text-[10px] font-semibold" style="color:${a.color}">${C.escapeHtml(a.label)}</p><p class="font-semibold text-sm leading-snug">${C.escapeHtml(a.title)}</p>${a.message ? `<p class="text-[11px] text-ink-muted mt-1">${C.escapeHtml(a.message)}</p>` : ''}<p class="text-[10px] text-ink-muted mt-2">${a.place ? C.escapeHtml(a.place.title) + ' · ' : ''}expire dans ${C.escapeHtml(a.expires_in)}</p></div>`);
                        this.alertMarkers.push(m);
                    });
                },
                highlight(id) {
                    Object.entries(this.markers).forEach(([k, m]) => { const el = m.getElement(); if (el) el.style.zIndex = String(k == id ? 1000 : 0); const pin = el && el.querySelector('.camino-pin'); if (pin) pin.style.transform = k == id ? 'scale(1.25)' : ''; });
                },
                focusAlert(a) { this.map.setView([a.lat, a.lng], Math.max(this.map.getZoom(), 15)); const m = this.alertMarkers.find(m => m.getLatLng().lat === a.lat && m.getLatLng().lng === a.lng); if (m) m.openPopup(); },
            };
        }
    </script>
    @endpush
</x-app-layout>
