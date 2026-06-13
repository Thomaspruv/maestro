import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Livewire/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['system-ui', '-apple-system', 'Segoe UI', 'Roboto', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                'bg-base': '#0f0f13',
                'bg-surface': '#18181f',
                'bg-elevated': '#23232e',
                'bg-overlay': '#2a2a35',
                primary: {
                    DEFAULT: '#7c3aed',
                    light: '#a78bfa',
                    muted: '#2d1f5e',
                },
                success: {
                    DEFAULT: '#4ade80',
                    muted: '#1a2f1a',
                },
                warning: {
                    DEFAULT: '#facc15',
                    muted: '#3f3010',
                },
                danger: {
                    DEFAULT: '#f87171',
                    muted: '#3f1f1f',
                },
                'text-primary': '#e0e0e0',
                'text-secondary': '#999999',
                'text-muted': '#666666',
                'text-faint': '#444444',
            },
        },
    },

    plugins: [forms],
};
