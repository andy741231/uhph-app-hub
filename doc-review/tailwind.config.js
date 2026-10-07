import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Source Sans 3"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                'uh-red': 'rgb(200, 16, 46)',
                'uh-white': 'rgb(255, 255, 255)',
                'uh-black': 'rgb(0, 0, 0)',
                'uh-slate': 'rgb(84, 88, 90)',
                'uh-brick': 'rgb(150, 12, 34)',
                'uh-chocolate': 'rgb(100, 8, 23)',
                'uh-cream': 'rgb(255, 249, 217)',
                'uh-gray': 'rgb(136, 139, 141)',
                'uh-gold': 'rgb(246, 190, 0)',
                'uh-mustard': 'rgb(216, 155, 0)',
                'uh-ocher': 'rgb(185, 120, 0)',
                'uh-teal': 'rgb(0, 179, 136)',
                'uh-green': 'rgb(0, 134, 108)',
                'uh-forest': 'rgb(0, 89, 80)',
                primary: {
                    DEFAULT: '#4F46E5',
                    dark: '#4338CA',
                    light: '#6366F1',
                },
                danger: {
                    DEFAULT: '#EF4444',
                    dark: '#DC2626',
                },
                success: {
                    DEFAULT: '#10B981',
                    dark: '#059669',
                },
                warning: {
                    DEFAULT: '#F59E0B',
                    dark: '#D97706',
                },
            },
        },
    },

    plugins: [forms],
};
