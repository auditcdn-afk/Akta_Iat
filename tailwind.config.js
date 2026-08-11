import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',

        // Sebagian besar tabel, kartu, dan panel aplikasi ini digambar dari
        // JavaScript (audit-editor.js dkk) lewat template string, bukan dari
        // Blade. Tanpa baris ini Tailwind tidak pernah melihat kelas-kelas itu
        // sehingga tidak ikut dibuat, dan elemennya tampil tanpa gaya — hanya
        // kebetulan terlihat benar selama kelas yang sama juga dipakai di suatu
        // Blade. Kelas yang tidak muncul di Blade mana pun (mis. bg-amber-500/15)
        // diam-diam hilang.
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
