import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import flowbitePlugin from 'flowbite/plugin';
import typographyPlugin from 'flowbite-typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './node_modules/flowbite/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Earthy premium palette
                brand: {
                    50: '#f0f7f4',
                    100: '#dcebe4',
                    200: '#bdd7ca',
                    300: '#8fb8a5',
                    400: '#5d9378',
                    500: '#3d7659', // Primary forest green
                    600: '#2d5d47',
                    700: '#254b3a',
                    800: '#203d30',
                    900: '#1c3329',
                    950: '#0e1c16',
                },
                accent: {
                    50: '#fef9f0',
                    100: '#fdf2d9',
                    200: '#fae2b3',
                    300: '#f6ca82',
                    400: '#f1a94f', // Warm amber
                    500: '#ed8f2a',
                    600: '#de7118',
                    700: '#b85616',
                    800: '#934519',
                    900: '#773a17',
                    950: '#411c0a',
                },
                stone: {
                    50: '#fafaf9',
                    100: '#f5f5f4',
                    200: '#e7e5e4',
                    300: '#d6d3d1',
                    400: '#a8a29e',
                    500: '#78716c',
                    600: '#57534e',
                    700: '#44403c',
                    800: '#292524',
                    900: '#1c1917',
                    950: '#0c0a09',
                },
            },
        },
    },

    plugins: [
        forms,
        flowbitePlugin,
        typographyPlugin,
    ],
};
