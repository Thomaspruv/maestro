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
                sans: ['Inter', 'ui-sans-serif', 'system-ui', ...defaultTheme.fontFamily.sans],
                mono: ['JetBrains Mono', 'ui-monospace', ...defaultTheme.fontFamily.mono],
            },
            borderRadius: {
                DEFAULT: '8px',
                lg: '12px',
                xl: '16px',
            },
            borderColor: {
                DEFAULT: 'var(--maestro-border)',
                md: 'var(--maestro-border-md)',
            },
            colors: {
                maestro: {
                    bg: 'var(--maestro-bg)',
                    surface: 'var(--maestro-surface)',
                    'surface-2': 'var(--maestro-surface-2)',
                    accent: 'var(--maestro-accent)',
                    'accent-hover': 'var(--maestro-accent-hover)',
                    'accent-light': 'var(--maestro-accent-light)',
                    'accent-muted': 'var(--maestro-accent-muted)',
                    text: 'var(--maestro-text-primary)',
                    muted: 'var(--maestro-text-secondary)',
                    subtle: 'var(--maestro-text-muted)',
                },
                'bg-base': 'var(--maestro-bg)',
                'bg-surface': 'var(--maestro-surface)',
                'bg-elevated': 'var(--maestro-surface-2)',
                'bg-overlay': 'var(--maestro-border-md)',
                primary: {
                    DEFAULT: 'rgb(var(--maestro-accent-rgb) / <alpha-value>)',
                    light: 'var(--maestro-accent-light)',
                    muted: 'rgb(var(--maestro-accent-muted-rgb) / <alpha-value>)',
                },
                success: {
                    DEFAULT: 'rgb(var(--maestro-success-rgb) / <alpha-value>)',
                    muted: 'var(--maestro-success-bg)',
                },
                warning: {
                    DEFAULT: 'rgb(var(--maestro-warning-rgb) / <alpha-value>)',
                    muted: 'var(--maestro-warning-bg)',
                },
                danger: {
                    DEFAULT: 'rgb(var(--maestro-danger-rgb) / <alpha-value>)',
                    muted: 'var(--maestro-danger-bg)',
                },
                'text-primary': 'var(--maestro-text-primary)',
                'text-secondary': 'var(--maestro-text-secondary)',
                'text-muted': 'var(--maestro-text-muted)',
                'text-faint': '#B8A898',
            },
        },
    },

    plugins: [forms],
};
