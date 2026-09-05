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
            'url' => route('places.show', $s['place_id']), 'hours' => $s['hours'] ?? null,
        ])->values()->all(),
        'title' => $result['title'],
        'backUrl' => $backUrl,
        'simulate' => max(0, (int) request()->query('simulate', 0)),
    ];
@endphp
<x-app-layout :title="'Guidage · ' . $result['title']" :fullscreen="true" :bottom-nav="false">
    <div class="absolute inset-0" x-data="caminoNav(@js($nav))" @keydown.escape.window="if (!started) quit()">
        <div id="nav-map" class="absolute inset-0 z-0"></div>

        {{-- Bandeau instruction (haut) --}}
        <div class="absolute top-[4.6rem] inset-x-3 z-[600] pointer-events-none">
            <div :class="{ hidden: !started }" class="hidden nav-card rounded-3xl bg-ink text-white p-3.5 flex items-center gap-3 pointer-events-auto">
                <span class="h-14 w-14 rounded-2xl bg-white/10 flex items-center justify-center shrink-0"><span class="material-symbols-outlined" style="font-size:34px" x-text="icon"></span></span>
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] uppercase tracking-[0.14em] text-white/60 font-bold" x-text="distanceLabel"></p>
                    <p class="font-semibold leading-snug line-clamp-2" x-text="instruction || 'Suis le tracé'"></p>
                    <p x-show="street" class="text-xs text-white/60 truncate" x-text="street"></p>
                </div>
                <button type="button" @click="toggleMute()" class="h-10 w-10 rounded-full bg-white/10 flex items-center justify-center shrink-0" :aria-label="muted ? 'Activer la voix' : 'Couper la voix'"><span class="material-symbols-outlined" style="font-size:20px" x-text="muted ? 'volume_off' : 'volume_up'"></span></button>
            </div>
            <div x-show="offRoute && started" x-cloak class="mt-2 inline-flex items-center gap-2 rounded-full bg-amber-500 text-ink px-3 py-1.5 text-xs font-semibold pointer-events-auto"><span class="material-symbols-outlined" style="font-size:16px" :class="rerouting && 'animate-spin'" x-text="rerouting ? 'progress_activity' : 'alt_route'"></span><span x-text="rerouting ? 'Recalcul de l\'itinéraire…' : 'Tu t\'éloignes du tracé'"></span></div>
            <div x-show="gpsError" x-cloak class="mt-2 inline-flex items-center gap-2 rounded-full bg-coral text-white px-3 py-1.5 text-xs font-semibold pointer-events-auto"><span class="material-symbols-outlined" style="font-size:16px">location_off</span><span x-text="gpsError"></span></div>
        </div>

        {{-- Boutons carte --}}
        <div class="absolute right-3 bottom-[13.5rem] sm:bottom-52 z-[600] flex flex-col gap-2">
            <button type="button" x-show="started && !follow" x-cloak @click="recenter()" class="h-11 w-11 rounded-full bg-white shadow-card flex items-center justify-center text-ink" aria-label="Recentrer"><span class="material-symbols-outlined">my_location</span></button>
            <button type="button" @click="fitAll()" class="h-11 w-11 rounded-full bg-white shadow-card flex items-center justify-center text-ink" aria-label="Voir tout le parcours"><span class="material-symbols-outlined">zoom_out_map</span></button>
        </div>

        {{-- Feuille basse --}}
        <div class="absolute inset-x-0 bottom-0 z-[600] px-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
            {{-- Avant le départ --}}
            <div :class="{ hidden: started || done }" class="nav-card card p-4 sm:p-5">
                <div class="flex items-start gap-3">
                    <span class="h-11 w-11 rounded-2xl bg-coral-soft text-coral flex items-center justify-center shrink-0"><span class="material-symbols-outlined">navigation</span></span>
                    <div class="min-w-0 flex-1">
                        <p class="eyebrow">Guidage</p>
                        <p class="font-display text-xl leading-tight truncate">{{ $result['title'] }}</p>
                        <p class="text-xs text-ink-muted mt-1">{{ count($steps) }} étape{{ count($steps) > 1 ? 's' : '' }} · {{ number_format($result['total_distance_km'] ?? 0, 1, ',', ' ') }} km {{ ($result['mode'] ?? 'walk') === 'bike' ? 'à vélo' : 'à pied' }} · instructions vocales en français</p>
                    </div>
                </div>
                <div class="mt-3 flex gap-2">
                    <button type="button" @click="start()" class="btn btn-lg btn-primary flex-1"><span class="material-symbols-outlined">play_arrow</span>Démarrer le guidage</button>
                    <button type="button" @click="toggleMute()" class="btn btn-lg btn-soft !px-4" :aria-label="muted ? 'Activer la voix' : 'Couper la voix'"><span class="material-symbols-outlined" x-text="muted ? 'volume_off' : 'volume_up'"></span></button>
                </div>
                <p class="mt-2 text-[11px] text-ink-muted">CAMINO utilise ta position uniquement pendant le guidage, rien n'est enregistré. Garde l'écran allumé, on s'en occupe.</p>
                <a :href="backUrl" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-ink-muted hover:text-ink"><span class="material-symbols-outlined" style="font-size:14px">arrow_back</span>Retour</a>
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
                        <p class="text-xs text-ink-muted"><span x-text="formatDistance(remaining)"></span> · <span x-text="etaLabel"></span></p>
                    </div>
                    <button type="button" @click="confirmArrival()" class="btn btn-sm btn-ink shrink-0" title="Je suis arrivé"><span class="material-symbols-outlined" style="font-size:16px">check</span></button>
                </div>
                <div class="mt-2.5 h-1.5 rounded-full bg-paper overflow-hidden"><div class="h-full rounded-full bg-coral transition-all duration-500" :style="'width:' + progressPct + '%'"></div></div>
                <div class="mt-2.5 flex items-center justify-between text-[11px] text-ink-muted">
                    <span x-show="accuracy" x-text="'GPS ± ' + Math.round(accuracy) + ' m'"></span>
                    <span x-show="simulate" class="text-coral font-semibold">Simulation</span>
                    <button type="button" @click="quit()" class="font-semibold text-ink-muted hover:text-coral inline-flex items-center gap-1"><span class="material-symbols-outlined" style="font-size:14px">close</span>Quitter</button>
                </div>
            </div>

            {{-- Arrivée à une étape --}}
            <div :class="{ hidden: !arrived || done }" class="hidden nav-card card p-4 sm:p-5">
                <div class="flex items-center gap-3">
                    <span class="h-11 w-11 rounded-2xl bg-teal-soft text-teal flex items-center justify-center shrink-0"><span class="material-symbols-outlined">check_circle</span></span>
                    <div class="min-w-0 flex-1">
                        <p class="eyebrow">Tu es arrivé</p>
                        <p class="font-display text-xl leading-tight line-clamp-2" x-text="target ? target.title : ''"></p>
                        <p class="text-xs text-ink-muted mt-0.5" x-text="target && target.visit ? 'Visite prévue : ' + target.visit + ' min' + (target.hours && target.hours.status === 'open' ? ' · ouvert ' + target.hours.opens + '–' + target.hours.closes : '') : ''"></p>
                    </div>
                </div>
                <div class="mt-3 flex gap-2">
                    <button type="button" @click="continueRoute()" class="btn btn-md btn-primary flex-1"><span class="material-symbols-outlined" style="font-size:18px">arrow_forward</span><span x-text="nextLabel"></span></button>
                    <template x-if="target && target.url"><a :href="target.url" class="btn btn-md btn-soft"><span class="material-symbols-outlined" style="font-size:18px">info</span>Fiche</a></template>
                </div>
            </div>

            {{-- Fin --}}
            <div :class="{ hidden: !done }" class="hidden nav-card card p-4 sm:p-5 text-center">
                <span class="material-symbols-outlined text-coral" style="font-size:40px">celebration</span>
                <p class="font-display text-2xl mt-1">Parcours terminé !</p>
                <p class="text-sm text-ink-muted mt-1" x-text="walked > 0 ? 'Tu as parcouru ' + formatDistance(walked) + '. Bravo.' : 'Bravo.'"></p>
                <div class="mt-3 flex gap-2 justify-center">
                    <a :href="backUrl" class="btn btn-md btn-primary"><span class="material-symbols-outlined" style="font-size:18px">arrow_back</span>Retour au parcours</a>
                    <a href="{{ route('map.index') }}" class="btn btn-md btn-soft">Carte</a>
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
            const ICONS = { 1: 'straight', 2: 'straight', 3: 'straight', 4: 'sports_score', 5: 'sports_score', 6: 'sports_score', 7: 'straight', 8: 'straight', 9: 'turn_slight_right', 10: 'turn_right', 11: 'turn_sharp_right', 12: 'u_turn_right', 13: 'u_turn_left', 14: 'turn_sharp_left', 15: 'turn_left', 16: 'turn_slight_left', 17: 'straight', 18: 'turn_slight_right', 19: 'turn_slight_left', 20: 'turn_slight_right', 21: 'turn_slight_left', 22: 'straight', 23: 'turn_slight_right', 24: 'turn_slight_left', 25: 'merge', 26: 'roundabout_right', 27: 'roundabout_right', 37: 'merge', 38: 'merge' };
            let map = null, userMarker = null, accuracyCircle = null, routeLine = null, legLine = null, doneLine = null, watchId = null, wakeLock = null, simTimer = null, stepMarkers = [];
            let cum = [];
            const targets = data.steps.map(s => ({ ...s, kind: s.kind || 'visit' }));
            if (data.end) targets.push({ lat: data.end.lat, lng: data.end.lng, title: data.end.label || 'Arrivée', cover: null, kind: 'end', visit: 0, url: null, hours: null });

            return {
                started: false, done: false, arrived: false, muted: false, follow: true, simulate: data.simulate > 0, simSpeed: Math.max(1, data.simulate || 1),
                legIndex: 0, pos: null, heading: 0, accuracy: null, gpsError: null,
                leg: null, segIdx: 0, along: 0, instruction: '', street: '', icon: 'straight', distToManeuver: null, maneuverIdx: -1, spokenIdx: -1, spokenApproach: -1,
                remaining: 0, offRoute: false, offRouteCount: 0, rerouting: false, walked: 0, lastPos: null,
                backUrl: data.backUrl,

                get target() { return targets[this.legIndex] || null; },
                get progressLabel() { const n = targets.length; const t = this.target; return t && t.kind === 'end' ? 'Retour · arrivée' : 'Étape ' + (this.legIndex + 1) + ' / ' + (data.end ? n - 1 : n); },
                get progressPct() { const total = this.legTotal(); return total > 0 ? Math.max(0, Math.min(100, Math.round((total - this.remaining) / total * 100))) : 0; },
                get etaLabel() { const speed = data.mode === 'bike' ? 3.6 : 1.3; const min = Math.round(this.remaining / speed / 60); const eta = new Date(Date.now() + min * 60000); return (min <= 1 ? 'arrivée imminente' : 'environ ' + min + ' min') + ' · ' + eta.getHours() + 'h' + String(eta.getMinutes()).padStart(2, '0'); },
                get distanceLabel() { if (this.distToManeuver === null) return 'En route'; if (this.distToManeuver < 15) return 'Maintenant'; return 'Dans ' + this.formatDistance(this.distToManeuver); },
                get nextLabel() { const next = targets[this.legIndex + 1]; if (!next) return 'Terminer'; return next.kind === 'end' ? 'Retour au départ' : 'Vers ' + next.title; },

                init() {
                    map = L.map(document.getElementById('nav-map'), { zoomControl: false, attributionControl: true });
                    C.tileLayer().addTo(map);
                    const full = data.geometry && data.geometry.length > 1 ? data.geometry : [[data.start.lat, data.start.lng], ...targets.map(t => [t.lat, t.lng])];
                    routeLine = L.polyline(full, { color: '#12161C', weight: 5, opacity: 0.22 }).addTo(map);
                    L.marker([data.start.lat, data.start.lng], { icon: C.stepIcon(0, true) }).addTo(map);
                    targets.forEach((t, i) => { const m = L.marker([t.lat, t.lng], { icon: t.kind === 'end' ? C.stepIcon('<span class="material-symbols-outlined" style="font-size:16px">sports_score</span>') : (t.kind === 'lunch' ? C.placeIcon('restauration', { size: 30 }) : C.stepIcon(i + 1)) }).addTo(map); stepMarkers.push(m); });
                    map.on('dragstart', () => { this.follow = false; });
                    this.fitAll();
                    this.loadLeg(0, null);
                    if ('speechSynthesis' in window) window.speechSynthesis.getVoices();
                },
                fitAll() { map.fitBounds(routeLine.getBounds(), { padding: [40, 40] }); this.follow = false; },
                recenter() { this.follow = true; if (this.pos) map.setView(this.pos, Math.max(map.getZoom(), 17), { animate: true }); },
                toggleMute() { this.muted = !this.muted; if (this.muted && 'speechSynthesis' in window) window.speechSynthesis.cancel(); else this.speak('Guidage vocal activé.'); },
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
                        maneuvers = [t.kind === 'end' ? { type: 8, text: 'Retourne au point de départ', verbal: 'Retournez au point de départ.', street: '', begin: 0, end: 1 } : { type: 8, text: 'Dirige-toi vers ' + t.title, verbal: 'Dirigez-vous vers ' + t.title + '.', street: '', begin: 0, end: 1 }];
                    }
                    this.setLeg(shape, maneuvers);
                },
                setLeg(shape, maneuvers) {
                    this.leg = { shape, maneuvers: maneuvers.filter(m => m.type !== 4 && m.type !== 5 && m.type !== 6).concat(maneuvers.filter(m => m.type === 4 || m.type === 5 || m.type === 6).slice(0, 1)) };
                    cum = [0];
                    for (let i = 1; i < shape.length; i++) cum[i] = cum[i - 1] + dist(shape[i - 1], shape[i]);
                    if (legLine) legLine.remove();
                    if (doneLine) doneLine.remove();
                    legLine = L.polyline(shape, { color: data.mode === 'bike' ? '#0F8B8D' : '#FF5A3C', weight: 6, opacity: 0.95, lineJoin: 'round' }).addTo(map);
                    doneLine = L.polyline([], { color: '#9CA3AF', weight: 6, opacity: 0.9 }).addTo(map);
                    this.segIdx = 0; this.along = 0; this.remaining = this.legTotal(); this.maneuverIdx = -1; this.spokenIdx = -1; this.spokenApproach = -1; this.offRoute = false; this.offRouteCount = 0;
                    this.updateInstruction(0);
                },

                // ---------------------------------------------------------------- démarrage
                async start() {
                    this.started = true; this.follow = true;
                    this.speak('Guidage lancé. ' + (this.leg.maneuvers[0] ? this.leg.maneuvers[0].verbal : 'Suivez le tracé.'));
                    try { if ('wakeLock' in navigator) wakeLock = await navigator.wakeLock.request('screen'); } catch (e) {}
                    document.addEventListener('visibilitychange', async () => { if (document.visibilityState === 'visible' && 'wakeLock' in navigator && this.started) { try { wakeLock = await navigator.wakeLock.request('screen'); } catch (e) {} } });
                    if (this.simulate) { this.runSimulation(); return; }
                    if (!navigator.geolocation) { this.gpsError = 'Géolocalisation indisponible sur cet appareil.'; return; }
                    watchId = navigator.geolocation.watchPosition(
                        p => { this.gpsError = null; this.onPosition([p.coords.latitude, p.coords.longitude], p.coords.heading, p.coords.accuracy); },
                        e => { this.gpsError = e.code === 1 ? 'Autorise la localisation pour être guidé.' : 'Signal GPS faible, on réessaie…'; },
                        { enableHighAccuracy: true, maximumAge: 2000, timeout: 15000 }
                    );
                },
                quit() { this.stopTracking(); window.location.href = this.backUrl; },
                stopTracking() { if (watchId !== null) navigator.geolocation.clearWatch(watchId); if (simTimer) clearInterval(simTimer); if (wakeLock) { try { wakeLock.release(); } catch (e) {} } if ('speechSynthesis' in window) window.speechSynthesis.cancel(); },

                // ---------------------------------------------------------------- position
                onPosition(latlng, heading, accuracy) {
                    if (this.lastPos) { const d = dist(this.lastPos, latlng); if (d < 200) this.walked += d; if (d > 3 && (heading === null || heading === undefined || isNaN(heading))) heading = bearing(this.lastPos, latlng); }
                    this.lastPos = latlng; this.pos = latlng; this.accuracy = accuracy;
                    if (heading !== null && heading !== undefined && !isNaN(heading)) this.heading = heading;
                    this.drawUser();
                    if (this.follow) map.setView(latlng, Math.max(map.getZoom(), 17), { animate: true, duration: 0.5 });
                    if (this.arrived || this.done) return;
                    // Projection sur le tronçon courant
                    const shape = this.leg.shape;
                    let best = { d: Infinity, i: 0, t: 0, p: latlng };
                    const from = Math.max(0, this.segIdx - 3);
                    for (let i = from; i < shape.length - 1; i++) { const pr = project(latlng, shape[i], shape[i + 1]); if (pr.d < best.d - 0.5 || (pr.d < best.d + 0.5 && i >= this.segIdx)) best = { d: pr.d, i, t: pr.t, p: pr.p }; }
                    const target = this.target;
                    const dTarget = dist(latlng, [target.lat, target.lng]);
                    if (dTarget < 30 || (dTarget < 45 && best.i >= shape.length - 2)) { this.onArrival(); return; }
                    if (best.d > Math.max(45, (accuracy || 0) * 1.5)) {
                        this.offRouteCount++;
                        if (this.offRouteCount >= 3 && !this.rerouting) this.reroute();
                        this.offRoute = this.offRouteCount >= 2;
                        return;
                    }
                    this.offRouteCount = 0; this.offRoute = false;
                    this.segIdx = best.i;
                    this.along = cum[best.i] + (cum[best.i + 1] - cum[best.i]) * best.t;
                    this.remaining = Math.max(0, this.legTotal() - this.along);
                    doneLine.setLatLngs([...shape.slice(0, best.i + 1), best.p]);
                    this.updateInstruction(this.along);
                },
                drawUser() {
                    if (!userMarker) {
                        userMarker = L.marker(this.pos, { icon: L.divIcon({ className: 'camino-user', html: '<div class="camino-user-arrow"></div>', iconSize: [44, 44], iconAnchor: [22, 22] }), zIndexOffset: 1000 }).addTo(map);
                        accuracyCircle = L.circle(this.pos, { radius: this.accuracy || 10, color: '#1D4ED8', weight: 1, opacity: 0.4, fillOpacity: 0.08 }).addTo(map);
                    }
                    userMarker.setLatLng(this.pos);
                    const arrow = userMarker.getElement()?.querySelector('.camino-user-arrow');
                    if (arrow) arrow.style.transform = 'rotate(' + this.heading + 'deg)';
                    accuracyCircle.setLatLng(this.pos).setRadius(Math.min(80, this.accuracy || 10));
                },
                updateInstruction(along) {
                    const ms = this.leg.maneuvers;
                    if (!ms.length) { this.instruction = this.target && this.target.kind === 'end' ? 'Retourne au point de départ' : 'Dirige-toi vers ' + (this.target ? this.target.title : 'l’étape'); this.street = ''; this.icon = 'straight'; this.distToManeuver = this.remaining; return; }
                    // Prochaine manœuvre : la première dont le point de début est devant nous.
                    let idx = ms.findIndex(m => (cum[m.begin] ?? 0) > along + 8);
                    if (idx === -1) idx = ms.length - 1;
                    const m = ms[idx];
                    const d = Math.max(0, (cum[m.begin] ?? this.legTotal()) - along);
                    this.distToManeuver = idx === 0 && along < 5 ? null : d;
                    this.instruction = m.text; this.street = m.street || ''; this.icon = ICONS[m.type] || 'straight';
                    if (idx !== this.maneuverIdx) { this.maneuverIdx = idx; this.spokenApproach = -1; }
                    // Voix : annonce à ~80 m (150 m à vélo), puis au moment de tourner.
                    const approach = data.mode === 'bike' ? 150 : 80;
                    if (d <= approach && d > 25 && this.spokenApproach !== idx) { this.spokenApproach = idx; this.speak('Dans ' + Math.round(d / 10) * 10 + ' mètres, ' + this.lower(m.verbal)); }
                    else if (d <= 25 && this.spokenIdx !== idx) { this.spokenIdx = idx; this.speak(m.verbal); }
                },
                lower(s) { return s ? s.charAt(0).toLowerCase() + s.slice(1) : ''; },

                // ---------------------------------------------------------------- arrivée / suite
                onArrival() {
                    if (this.arrived) return;
                    this.arrived = true;
                    const t = this.target;
                    if (t.kind === 'end') { this.finish(); return; }
                    this.speak('Vous êtes arrivé à ' + t.title + '.' + (t.visit ? ' Visite prévue : ' + t.visit + ' minutes.' : ''));
                    if (navigator.vibrate) navigator.vibrate([120, 60, 120]);
                },
                confirmArrival() { this.onArrival(); },
                async continueRoute() {
                    const next = this.legIndex + 1;
                    this.arrived = false;
                    if (!targets[next]) { this.finish(); return; }
                    this.legIndex = next;
                    // Depuis la position réelle si on l'a, sinon le tronçon calculé à l'avance.
                    if (this.pos && !this.simulate) { await this.reroute(true); } else { this.loadLeg(next, this.pos); }
                    this.speak((targets[next].kind === 'end' ? 'Dernière étape : retour au point de départ. ' : 'Direction ' + targets[next].title + '. ') + (this.leg.maneuvers[0] ? this.leg.maneuvers[0].verbal : ''));
                    if (this.simulate) this.runSimulation();
                },
                finish() { this.done = true; this.arrived = false; this.stopTracking(); this.speak('Parcours terminé. Bravo !'); },
                async reroute(silent = false) {
                    if (!this.pos) { this.loadLeg(this.legIndex, null); return; }
                    this.rerouting = true;
                    try {
                        const r = await fetch('/api/v1/route', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ points: [{ lat: this.pos[0], lng: this.pos[1] }, { lat: this.target.lat, lng: this.target.lng }], mode: data.mode }) });
                        const j = await r.json();
                        const leg = j.legs && j.legs[0];
                        if (leg && leg.shape && leg.shape.length > 1) { this.setLeg(leg.shape, leg.maneuvers || []); if (!silent) this.speak('Itinéraire recalculé. ' + (this.leg.maneuvers[0] ? this.leg.maneuvers[0].verbal : '')); }
                        else this.loadLeg(this.legIndex, this.pos);
                    } catch (e) { this.loadLeg(this.legIndex, this.pos); }
                    this.rerouting = false; this.offRoute = false; this.offRouteCount = 0;
                },

                // ---------------------------------------------------------------- voix
                speak(text) {
                    if (this.muted || !('speechSynthesis' in window) || !text) return;
                    const u = new SpeechSynthesisUtterance(text.replace(/\s+/g, ' '));
                    u.lang = 'fr-FR'; u.rate = 1.0;
                    const voice = window.speechSynthesis.getVoices().find(v => v.lang && v.lang.toLowerCase().startsWith('fr'));
                    if (voice) u.voice = voice;
                    window.speechSynthesis.cancel();
                    window.speechSynthesis.speak(u);
                },

                // ---------------------------------------------------------------- simulation (test sans GPS)
                runSimulation() {
                    if (simTimer) clearInterval(simTimer);
                    const shape = this.leg.shape; let i = 0, t = 0; const speed = (data.mode === 'bike' ? 4.5 : 1.6) * this.simSpeed;
                    simTimer = setInterval(() => {
                        if (this.arrived || this.done) { clearInterval(simTimer); return; }
                        if (i >= shape.length - 1) { this.onPosition(shape[shape.length - 1], null, 8); clearInterval(simTimer); return; }
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
