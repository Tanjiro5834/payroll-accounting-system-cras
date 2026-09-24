// assets/js/theme.js
// Centralized Tailwind theme for Coronacion Timekeeping.
// Loaded immediately after the Tailwind CDN script in every view.

tailwind.config = {
    theme: {
        extend: {
            fontFamily: {
                display: ['Archivo', 'system-ui', 'sans-serif'],
                sans: ['Public Sans', 'system-ui', 'sans-serif'],
                mono: ['IBM Plex Mono', 'ui-monospace', 'monospace'],
            },
            colors: {
                ink: {
                    DEFAULT: '#0B1726',
                    50: '#F5F7FA',
                },
                coolant: {
                    DEFAULT: '#0E7490',
                    hover: '#155E75',
                    tint: '#ECFEFF',
                },
                frost: {
                    DEFAULT: '#22D3EE',
                },
                positive: '#059669',
                negative: '#DC2626',
                warning: '#D97706',
                line: '#E2E8F0',
            },
            transitionTimingFunction: {
                smooth: 'cubic-bezier(0.22, 1, 0.36, 1)',
            },
            keyframes: {
                'fade-up': {
                    '0%': { opacity: '0', transform: 'translateY(8px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                'fade-scale': {
                    '0%': { opacity: '0', transform: 'scale(0.96)' },
                    '100%': { opacity: '1', transform: 'scale(1)' },
                },
                shimmer: {
                    '0%': { transform: 'translateX(-100%)' },
                    '100%': { transform: 'translateX(100%)' },
                },
                'toast-in': {
                    '0%': { opacity: '0', transform: 'translateY(12px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
            },
            animation: {
                'fade-up': 'fade-up 400ms cubic-bezier(0.22, 1, 0.36, 1) both',
                'fade-scale': 'fade-scale 220ms cubic-bezier(0.22, 1, 0.36, 1) both',
                shimmer: 'shimmer 1.6s linear infinite',
                'toast-in': 'toast-in 260ms cubic-bezier(0.22, 1, 0.36, 1) both',
            },
        },
    },
};