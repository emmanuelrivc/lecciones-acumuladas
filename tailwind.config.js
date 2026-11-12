import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

// Paleta tomada de los wireframes: verde azulado como color principal
const brand = {
    50: '#EAF5F3',
    100: '#E6F4F1',
    200: '#C4E4DF',
    500: '#14937F',
    600: '#0F766E',
    700: '#0B5A54',
    800: '#0B4A45',
    900: '#0B3B37',
};

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
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand,
                // Los componentes de Breeze usan "indigo": lo mapeamos al verde de la escuela
                indigo: { ...brand, 400: '#14937F' },
            },
            boxShadow: {
                card: '0 1px 2px rgba(16,24,40,.04), 0 8px 24px rgba(16,24,40,.05)',
            },
        },
    },

    plugins: [forms],
};
