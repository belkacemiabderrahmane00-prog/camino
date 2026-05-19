import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
                display: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: '#13ECEC',
                'background-light': '#f6f8f8',
                'background-dark': '#102222',
                'camino-accent': '#FACC15',
                'camino-background-dark': '#020617',
                'camino-surface': '#020617',
            },
            boxShadow: {
                'camino-soft': '0 18px 45px rgba(15,23,42,0.75)',
                'camino-chip': '0 8px 18px rgba(15,23,42,0.65)',
            },
            borderRadius: {
                DEFAULT: '1rem',
                lg: '2rem',
                xl: '3rem',
                '3xl': '1.5rem',
            },
        },
    },

    plugins: [forms],
};
