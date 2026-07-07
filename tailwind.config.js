import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{vue,js}',
    ],

    safelist: [
        // Status badge pills (defined in scheduleStatus.js)
        'bg-gray-200', 'text-gray-800', 'border-gray-400', 'bg-gray-600',
        'bg-gray-500', 'text-white', 'bg-gray-100', 'text-gray-600', 'border-gray-300',
        'bg-blue-100', 'text-blue-800', 'border-blue-400', 'bg-blue-600', 'bg-blue-700',
        'bg-blue-200', 'border-blue-500', 'bg-blue-50',
        'bg-green-100', 'text-green-800', 'border-green-300', 'bg-green-600', 'bg-green-700',
        'bg-green-200', 'border-green-400', 'bg-green-50', 'border-green-500',
        'bg-emerald-100', 'text-emerald-900', 'border-emerald-500', 'bg-emerald-700', 'bg-emerald-800', 'text-emerald-800',
        'bg-red-100', 'text-red-800', 'border-red-300', 'bg-red-600', 'bg-red-700',
        'bg-red-200', 'border-red-400', 'bg-red-50',
        'bg-slate-200', 'text-slate-800', 'border-slate-400', 'bg-slate-600', 'text-slate-700',
        'text-gray-700', 'text-green-900',
    ],

    theme: {
        extend: {
            colors: {
                primary: {
                    DEFAULT: '#005740',
                    50: '#e7f5f0',
                    100: '#cde9df',
                    200: '#9bd3c1',
                    300: '#68bca1',
                    400: '#35a681',
                    500: '#0f8566',
                    600: '#006b4f',
                    700: '#005740',
                    800: '#004434',
                    900: '#003126',
                },
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
