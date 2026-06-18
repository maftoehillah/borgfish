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
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                surface: '#F7F9FB',
                border: '#E6EAF0',
                text: '#0F1724',
                muted: '#6B7280',
                accent: {
                    100: '#EEF6F6',
                    200: '#D6EEEC',
                    500: '#48A9A6',
                    700: '#2C7F7F',
                },
            },
        },
    },

    plugins: [forms],
};
