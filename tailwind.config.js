import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    // Match viewer pages that toggle `class="dark"` on <html> (see layouts/user-portal.blade.php).
    darkMode: 'class',

    // Avoid a second Tailwind preflight after dcplibrary/ui’s dcpl.css (which already includes Preflight).
    corePlugins: {
        preflight: false,
    },

    // Scan only the package's own files — never the host app.
    // This ensures the compiled CSS is self-contained and portable.
    content: [
        './resources/views/**/*.blade.php',
        './src/**/*.php',
        'vendor/dcplibrary/ui/resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            colors: {
                'dcpl-blue':   '#0075A3',
                'dcpl-orange': '#F47920',
                'dcpl-green':  '#8BC53F',
                'dcpl-purple': '#8B5CF6',
                'dcpl-gold':   '#F4B942',
                'dcpl-text':   '#4d6375',
            },
            fontFamily: {
                outfit: ['Outfit', 'sans-serif'],
            },
        },
    },

    plugins: [
        forms,
    ],
};
