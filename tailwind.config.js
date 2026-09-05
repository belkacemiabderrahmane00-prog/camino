import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Sora', ...defaultTheme.fontFamily.sans],
                display: ['Fraunces', 'Georgia', 'serif'],
            },
            colors: {
                ink: {
                    DEFAULT: '#12161C',
                    soft: '#3B4552',
                    muted: '#6B7684',
                },
                paper: {
                    DEFAULT: '#F6F3EC',
                    deep: '#EEE9DF',
                },
                coral: {
                    DEFAULT: '#FF5A3C',
                    dark: '#E3432A',
                    soft: '#FFE9E3',
                },
                teal: {
                    DEFAULT: '#0F8B8D',
                    dark: '#0B6F71',
                    soft: '#DDF3F2',
                },
                sun: {
                    DEFAULT: '#FFC857',
                    soft: '#FFF3D6',
                },
                cat: {
                    musee: '#7C3AED',
                    monument: '#B45309',
                    parc: '#15803D',
                    culturel: '#0369A1',
                    resto: '#DB2777',
                    event: '#F59E0B',
                    street: '#E11D48',
                    itineraire: '#0F766E',
                },
            },
            borderRadius: {
                '2xl': '1.25rem',
                '3xl': '1.75rem',
                '4xl': '2.5rem',
            },
            boxShadow: {
                card: '0 1px 2px rgba(18,22,28,0.04), 0 8px 24px -12px rgba(18,22,28,0.18)',
                float: '0 12px 40px -12px rgba(18,22,28,0.35)',
                ring: '0 0 0 4px rgba(255,90,60,0.18)',
            },
            keyframes: {
                'fade-up': {
                    '0%': { opacity: 0, transform: 'translateY(12px)' },
                    '100%': { opacity: 1, transform: 'translateY(0)' },
                },
                'pulse-ring': {
                    '0%': { transform: 'scale(0.8)', opacity: 0.9 },
                    '100%': { transform: 'scale(2.2)', opacity: 0 },
                },
                'sheet-up': {
                    '0%': { transform: 'translateY(100%)' },
                    '100%': { transform: 'translateY(0)' },
                },
            },
            animation: {
                'fade-up': 'fade-up 420ms cubic-bezier(0.22, 0.61, 0.36, 1) both',
                'pulse-ring': 'pulse-ring 1.8s cubic-bezier(0.2, 0.6, 0.4, 1) infinite',
                'sheet-up': 'sheet-up 320ms cubic-bezier(0.22, 0.61, 0.36, 1) both',
            },
        },
    },

    plugins: [forms],
};
