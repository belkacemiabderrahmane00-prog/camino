import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

// Simple thème clair / sombre géré côté client
const CaminoTheme = {
    storageKey: 'camino.theme',

    getPreferredTheme() {
        if (typeof window === 'undefined') return 'dark';
        const stored = window.localStorage.getItem(this.storageKey);
        if (stored === 'light' || stored === 'dark') {
            return stored;
        }
        return window.matchMedia &&
            window.matchMedia('(prefers-color-scheme: dark)').matches
            ? 'dark'
            : 'light';
    },

    applyTheme(theme) {
        const root = document.documentElement;
        if (theme === 'dark') {
            root.classList.add('dark');
        } else {
            root.classList.remove('dark');
        }
    },

    init() {
        const theme = this.getPreferredTheme();
        this.applyTheme(theme);
    },

    toggle() {
        const isDark = document.documentElement.classList.contains('dark');
        const next = isDark ? 'light' : 'dark';
        window.localStorage.setItem(this.storageKey, next);
        this.applyTheme(next);
    },
};

window.CaminoTheme = CaminoTheme;

document.addEventListener('DOMContentLoaded', () => {
    CaminoTheme.init();
});

