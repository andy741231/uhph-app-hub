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
                sans: ['"Source Sans 3"', ...defaultTheme.fontFamily.sans],
                mono: ['"Fira Code"', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                uh: {
                    red: '#C8102E',           // Primary UH Red (PMS 186 C)
                    'red-dark': '#960C22',     // Brick (PMS 704 C)
                    'red-hover': '#A60D26',
                    brick: '#960C22',          // Brick (PMS 704 C)
                    chocolate: '#640817',      // Chocolate (PMS 490 C)
                    slate: '#54585A',          // Secondary Slate (PMS 425 C)
                    'slate-dark': '#3E4244',
                    gray: '#888B8D',           // Gray (PMS Cool Gray 8 C)
                    gold: '#F6BE00',           // Gold (PMS 7408 C)
                    mustard: '#D89B00',        // Mustard (PMS 124 C)
                    ocher: '#B97800',          // Ocher (PMS 1245 C)
                    teal: '#00B388',           // Teal (PMS 339 C)
                    green: '#00866C',          // Green (PMS 328 C)
                    forest: '#005950',         // Forest (PMS 3305 C)
                    cream: '#FFF9D9',          // Cream (PMS 7499 C)
                    navy: '#C8102E',           // Mapped to UH Red for headers
                    blue: '#C8102E',           // Mapped to UH Red
                    bg: '#F8FAFC',
                    fg: '#111827',
                    muted: '#F4F5F7',
                    border: '#E2E5E9',
                },
            },
        },
    },

    plugins: [forms],
};
