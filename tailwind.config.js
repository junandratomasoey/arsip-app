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
                // Warna resmi Kementerian PU, diambil dari logo (lihat
                // resources/../public/images/logo-kemenpu.png). Skala angka
                // dibuat mengikuti pola skala warna Tailwind supaya kelas
                // seperti bg-pu-navy-600/text-pu-navy-700 bisa langsung
                // dipakai selayaknya warna bawaan (mis. menggantikan indigo).
                'pu-navy': {
                    DEFAULT: '#203368',
                    50: '#eef1f8',
                    100: '#dbe1f0',
                    200: '#b3bfdd',
                    300: '#8a9dc9',
                    400: '#4d5f9e',
                    500: '#203368',
                    600: '#1c2d5c',
                    700: '#182752',
                    800: '#141f42',
                    900: '#101a37',
                },
                'pu-gold': {
                    DEFAULT: '#FDB714',
                    50: '#fff8e6',
                    100: '#fdecc0',
                    200: '#fddd8a',
                    300: '#fdcc4a',
                    400: '#fdc02c',
                    500: '#fdb714',
                    600: '#e0a010',
                    700: '#c78d0d',
                    800: '#a3730a',
                    900: '#7a5608',
                },
            },
        },
    },

    plugins: [forms],
};
