import maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';

/**
 * Carte de navigation (guidage) : MapLibre GL + tuiles vectorielles OpenFreeMap.
 * Vue « conduite » : la carte tourne avec le cap, inclinée, position en bas de l'écran ;
 * vue « aperçu » : tout le tronçon ou tout le parcours, nord en haut.
 * Les coordonnées reçues sont [lat, lng] (comme le reste de l'app), converties ici en [lng, lat].
 */
const STYLES = {
    light: 'https://tiles.openfreemap.org/styles/positron',
    dark: 'https://tiles.openfreemap.org/styles/dark',
};
const toLngLat = (p) => [p[1], p[0]];
const line = (coords) => ({ type: 'Feature', properties: {}, geometry: { type: 'LineString', coordinates: coords.map(toLngLat) } });
const empty = () => ({ type: 'FeatureCollection', features: [] });

export class NavMap {
    constructor(el, options = {}) {
        this.dark = !!options.dark;
        this.mode = 'follow';
        this.ready = false;
        this.pending = [];
        this.userMarker = null;
        this.markers = [];
        this.bottomPadding = options.bottomPadding || 260;
        this.map = new maplibregl.Map({
            container: el,
            style: STYLES[this.dark ? 'dark' : 'light'],
            center: options.center ? toLngLat(options.center) : [2.3522, 48.8566],
            zoom: options.zoom || 13,
            pitch: 0,
            bearing: 0,
            attributionControl: { compact: true },
            dragRotate: true,
            touchPitch: true,
            fadeDuration: 0,
        });
        // style.load plutôt que load : les couches n'attendent pas la fin du chargement de toutes les tuiles.
        this.map.on('style.load', () => { if (!this.ready) this.onLoad(); });
        this.map.on('error', (e) => console.warn('NavMap', e && e.error ? e.error.message : e));
        // Seules les interactions de l'utilisateur (doigt, souris) quittent le mode suivi : les mouvements programmés n'ont pas d'originalEvent.
        const userMove = (e) => { if (e && e.originalEvent && options.onDrag) options.onDrag(); };
        ['dragstart', 'rotatestart', 'zoomstart', 'pitchstart'].forEach((ev) => this.map.on(ev, userMove));
    }

    onLoad() {
        const m = this.map;
        m.addSource('route', { type: 'geojson', data: empty() });
        m.addSource('leg', { type: 'geojson', data: empty() });
        m.addSource('done', { type: 'geojson', data: empty() });
        m.addSource('turn', { type: 'geojson', data: empty() });
        // Parcours complet en fond, discret.
        m.addLayer({ id: 'route-line', type: 'line', source: 'route', layout: { 'line-join': 'round', 'line-cap': 'round' }, paint: { 'line-color': this.dark ? '#9AA3B2' : '#12161C', 'line-width': 4, 'line-opacity': 0.22 } });
        // Tronçon courant : bordure blanche + trait coloré (lisible sur toute carte), pointillé en transports.
        m.addLayer({ id: 'leg-casing', type: 'line', source: 'leg', layout: { 'line-join': 'round', 'line-cap': 'round' }, paint: { 'line-color': this.dark ? '#0B0E12' : '#FFFFFF', 'line-width': ['interpolate', ['linear'], ['zoom'], 12, 8, 17, 14, 19, 18], 'line-opacity': 0.9 } });
        const width = ['interpolate', ['linear'], ['zoom'], 12, 4, 17, 8, 19, 11];
        m.addLayer({ id: 'leg-line', type: 'line', source: 'leg', filter: ['!=', ['get', 'transit'], true], layout: { 'line-join': 'round', 'line-cap': 'round' }, paint: { 'line-color': ['coalesce', ['get', 'color'], '#FF5A3C'], 'line-width': width } });
        // Transports : pointillés (line-dasharray n'accepte pas d'expression par entité, d'où une couche dédiée).
        m.addLayer({ id: 'leg-line-transit', type: 'line', source: 'leg', filter: ['==', ['get', 'transit'], true], layout: { 'line-join': 'round', 'line-cap': 'butt' }, paint: { 'line-color': ['coalesce', ['get', 'color'], '#1D4ED8'], 'line-width': width, 'line-dasharray': [1.6, 1.2] } });
        // Portion déjà parcourue.
        m.addLayer({ id: 'done-line', type: 'line', source: 'done', layout: { 'line-join': 'round', 'line-cap': 'round' }, paint: { 'line-color': this.dark ? '#5B6472' : '#9CA3AF', 'line-width': ['interpolate', ['linear'], ['zoom'], 12, 4, 17, 8, 19, 11], 'line-opacity': 0.9 } });
        // Point de la prochaine manœuvre.
        m.addLayer({ id: 'turn-point', type: 'circle', source: 'turn', paint: { 'circle-radius': 7, 'circle-color': '#FFFFFF', 'circle-stroke-color': '#12161C', 'circle-stroke-width': 3 } });
        this.ready = true;
        this.pending.forEach((fn) => fn());
        this.pending = [];
    }

    whenReady(fn) { this.ready ? fn() : this.pending.push(fn); }

    setRoute(coords) { this.whenReady(() => this.map.getSource('route').setData(coords.length > 1 ? line(coords) : empty())); }
    setLeg(coords, { transit = false, color = null } = {}) {
        this.whenReady(() => {
            const f = coords.length > 1 ? line(coords) : null;
            if (f) f.properties = { transit, color: color || (transit ? '#1D4ED8' : '#FF5A3C') };
            this.map.getSource('leg').setData(f || empty());
            this.map.getSource('done').setData(empty());
        });
    }
    setDone(coords) { this.whenReady(() => this.map.getSource('done').setData(coords.length > 1 ? line(coords) : empty())); }
    setTurn(point) { this.whenReady(() => this.map.getSource('turn').setData(point ? { type: 'Feature', properties: {}, geometry: { type: 'Point', coordinates: toLngLat(point) } } : empty())); }

    /** Marqueur DOM (étape, départ, arrivée) : html = contenu de la puce, comme avec Leaflet. */
    addMarker(latlng, html, { size = 30, anchor = 'center' } = {}) {
        const el = document.createElement('div');
        el.className = 'camino-marker';
        el.style.width = size + 'px';
        el.style.height = size + 'px';
        el.innerHTML = html;
        const marker = new maplibregl.Marker({ element: el, anchor }).setLngLat(toLngLat(latlng)).addTo(this.map);
        this.markers.push(marker);
        return marker;
    }

    /** Position de l'utilisateur : flèche orientée dans le repère de la carte (reste juste quand la carte tourne). */
    setUser(latlng, heading = 0, accuracy = null) {
        if (!this.userMarker) {
            const el = document.createElement('div');
            el.className = 'nav-puck';
            el.innerHTML = '<div class="nav-puck-halo"></div><div class="nav-puck-arrow"></div>';
            this.userMarker = new maplibregl.Marker({ element: el, rotationAlignment: 'map', pitchAlignment: 'map' }).setLngLat(toLngLat(latlng)).addTo(this.map);
        }
        this.userMarker.setLngLat(toLngLat(latlng)).setRotation(heading || 0);
        const halo = this.userMarker.getElement().querySelector('.nav-puck-halo');
        if (halo) { const px = accuracy ? Math.min(90, Math.max(28, accuracy * 1.2)) : 34; halo.style.width = px + 'px'; halo.style.height = px + 'px'; }
    }

    /** Vue conduite : caméra derrière l'utilisateur, cap en haut, inclinée. */
    follow(latlng, heading = 0, { zoom = 17.2, pitch = 55, duration = 600 } = {}) {
        this.mode = 'follow';
        this.map.easeTo({ center: toLngLat(latlng), bearing: heading || 0, pitch, zoom, duration, padding: { top: 120, bottom: this.bottomPadding, left: 0, right: 0 }, essential: true });
    }

    /** Vue aperçu : tout ce qui est passé en paramètre, nord en haut, à plat. */
    fit(coordsList, { padding = 40, duration = 700 } = {}) {
        const pts = coordsList.filter(Boolean);
        if (pts.length === 0) return;
        this.mode = 'overview';
        if (pts.length === 1) { this.map.easeTo({ center: toLngLat(pts[0]), zoom: 16, bearing: 0, pitch: 0, duration }); return; }
        const b = new maplibregl.LngLatBounds(toLngLat(pts[0]), toLngLat(pts[0]));
        pts.forEach((p) => b.extend(toLngLat(p)));
        this.map.fitBounds(b, { padding: { top: 96, bottom: this.bottomPadding + 8, left: padding, right: padding }, bearing: 0, pitch: 0, duration, maxZoom: 17.5 });
    }

    northUp() { this.map.easeTo({ bearing: 0, duration: 400 }); }
    getBearing() { return this.map.getBearing(); }
    setBottomPadding(px) { this.bottomPadding = px; }
    resize() { this.map.resize(); }
}

export default NavMap;
