import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"IBM Plex Sans"', ...defaultTheme.fontFamily.sans],
                mono: ['"IBM Plex Mono"', ...defaultTheme.fontFamily.mono],
            },
            // Charte graphique SQLAudit
            colors: {
                page: '#F4F5F7',
                ink: '#161B22',
                muted: '#5A6270',
                line: '#DDE1E6',
                'line-soft': '#EEF0F3',
                field: '#C3C9D1',
                primary: '#1F4E79',
                sidebar: {
                    DEFAULT: '#13283F',
                    active: '#234A70',
                    link: '#D3DCE7',
                },
            },
        },
    },

    plugins: [forms],
};
