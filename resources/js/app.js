import './bootstrap';

import Alpine from 'alpinejs';
import L from 'leaflet';

// Leaflet est embarqué dans le bundle (pas de dépendance à un CDN externe au chargement).
window.L = L;
window.Alpine = Alpine;

/**
 * Icônes et couleurs par catégorie (alignées avec tailwind.config.js et le brief client).
 */
export const CATEGORY_STYLE = {
    musee: { icon: 'palette', color: '#7C3AED', label: 'Musée' },
    monument: { icon: 'account_balance', color: '#B45309', label: 'Monument' },
    'parc-jardin': { icon: 'park', color: '#15803D', label: 'Parc / Jardin' },
    'lieu-culturel': { icon: 'theater_comedy', color: '#0369A1', label: 'Lieu culturel' },
    restauration: { icon: 'restaurant', color: '#DB2777', label: 'Restauration' },
    'evenement-culturel': { icon: 'celebration', color: '#F59E0B', label: 'Événement' },
    'street-art': { icon: 'brush', color: '#E11D48', label: 'Street art' },
    itineraire: { icon: 'route', color: '#0F766E', label: 'Itinéraire' },
    'librairies-bibliotheques': { icon: 'menu_book', color: '#1D4ED8', label: 'Librairie / bibliothèque' },
    'ateliers-artisans': { icon: 'handyman', color: '#9A3412', label: 'Atelier / artisan' },
    default: { icon: 'place', color: '#0F8B8D', label: 'Lieu' },
};

export function categoryStyle(slug) {
    return CATEGORY_STYLE[slug] || CATEGORY_STYLE.default;
}

/**
 * Marqueur Leaflet stylé pour un lieu.
 */
export function placeIcon(slug, options = {}) {
    if (!window.L) return null;
    const style = categoryStyle(slug);
    const size = options.size || 36;
    return window.L.divIcon({
        className: 'camino-marker',
        html: `<div class="camino-pin" style="background:${style.color};width:${size}px;height:${size}px"><span class="material-symbols-outlined">${style.icon}</span></div>`,
        iconSize: [size, size],
        iconAnchor: [size / 2, size / 2],
        popupAnchor: [0, -size / 2],
    });
}

export function alertIcon(color, icon) {
    if (!window.L) return null;
    return window.L.divIcon({
        className: 'camino-marker',
        html: `<div class="camino-pin camino-pin-alert" style="background:${color};color:${color}"><span class="material-symbols-outlined" style="font-size:16px">${icon}</span></div>`,
        iconSize: [30, 30],
        iconAnchor: [15, 15],
        popupAnchor: [0, -15],
    });
}

export function stepIcon(number, isStart = false) {
    if (!window.L) return null;
    return window.L.divIcon({
        className: 'camino-marker',
        html: `<div class="camino-pin ${isStart ? 'camino-pin-start' : 'camino-pin-step'}" style="width:30px;height:30px">${isStart ? '<span class="material-symbols-outlined" style="font-size:16px">flag</span>' : number}</div>`,
        iconSize: [30, 30],
        iconAnchor: [15, 15],
        popupAnchor: [0, -15],
    });
}

/**
 * Fond de carte : OpenStreetMap France (style clair, sans clé), attribution OSM.
 */
export function tileLayer() {
    return window.L.tileLayer('https://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png', {
        maxZoom: 20,
        subdomains: 'abc',
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> · <a href="https://www.openstreetmap.fr/">OSM France</a>',
    });
}

export function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

export function debounce(fn, delay = 350) {
    let t;
    return (...args) => {
        clearTimeout(t);
        t = setTimeout(() => fn(...args), delay);
    };
}

/**
 * Géolocalisation avec promesse.
 */
export function locate(options = {}) {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) return reject(new Error('unsupported'));
        navigator.geolocation.getCurrentPosition(
            (pos) => resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude }),
            reject,
            { enableHighAccuracy: true, timeout: 10000, ...options }
        );
    });
}

/**
 * Puce d'étape en HTML (pour les marqueurs MapLibre du guidage).
 */
export function stepPinHtml(number, isStart = false) {
    return `<div class="camino-pin ${isStart ? 'camino-pin-start' : 'camino-pin-step'}" style="width:30px;height:30px">${isStart ? '<span class="material-symbols-outlined" style="font-size:16px">flag</span>' : number}</div>`;
}
export function placePinHtml(slug, size = 30) {
    const style = categoryStyle(slug);
    return `<div class="camino-pin" style="background:${style.color};width:${size}px;height:${size}px"><span class="material-symbols-outlined">${style.icon}</span></div>`;
}

/** Carte de navigation (MapLibre), chargée uniquement sur la page de guidage. */
export function loadNavMap() {
    return import('./nav-map.js').then((m) => m.NavMap);
}

window.Camino = { CATEGORY_STYLE, categoryStyle, placeIcon, alertIcon, stepIcon, stepPinHtml, placePinHtml, tileLayer, escapeHtml, debounce, locate, loadNavMap };

Alpine.start();
