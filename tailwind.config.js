import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                display: ['"Cormorant Garamond"', 'Georgia', 'serif'],
                devanagari: ['"Noto Serif Devanagari"', 'serif'],
            },
            colors: {
                saffron: '#F97316',
                turmeric: '#F59E0B',
                gulal: { DEFAULT: '#EC4899', deep: '#BE185D' },
                yamuna: { DEFAULT: '#1E3A8A', deep: '#172554' },
                maroon: { DEFAULT: '#6B1029', deep: '#3A0A18', dusk: '#240613' },
                gold: { DEFAULT: '#C9A227', soft: '#E4CD8C' },
                peacock: { DEFAULT: '#0E5C61', deep: '#083C40' },
                cream: { DEFAULT: '#FFF8F0', warm: '#FCEFDD' },
            },
            keyframes: {
                floatY: { '0%, 100%': { transform: 'translateY(0)' }, '50%': { transform: 'translateY(-14px)' } },
                pulseGlow: {
                    '0%, 100%': { boxShadow: '0 0 0 0 rgba(236,72,153,0.55)' },
                    '50%': { boxShadow: '0 0 0 10px rgba(236,72,153,0)' },
                },
                marquee: { from: { transform: 'translateX(0)' }, to: { transform: 'translateX(-50%)' } },
                fadeInUp: { from: { opacity: 0, transform: 'translateY(28px)' }, to: { opacity: 1, transform: 'translateY(0)' } },
            },
            animation: {
                float: 'floatY 5s ease-in-out infinite',
                'pulse-glow': 'pulseGlow 2.4s ease-in-out infinite',
                marquee: 'marquee 26s linear infinite',
                'fade-in-up': 'fadeInUp 0.7s cubic-bezier(0.22,1,0.36,1) both',
            },
            backgroundImage: {
                festive: 'linear-gradient(120deg, #F97316 0%, #EC4899 55%, #F59E0B 100%)',
            },
        },
    },

    plugins: [forms],
};
