import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/sass/app.scss',
                'resources/js/app.js',
                'resources/assets/css/demo.css',
                'resources/assets/js/config.js',
                'resources/assets/js/main.js',
                'resources/assets/vendor/fonts/boxicons.scss',
                'resources/assets/vendor/js/bootstrap.js',
                'resources/assets/vendor/js/helpers.js',
                'resources/assets/vendor/js/menu.js',
                'resources/assets/vendor/libs/jquery/jquery.js',
                'resources/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js',
                'resources/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.scss',
                'resources/assets/vendor/libs/popper/popper.js',
                'resources/assets/vendor/scss/core.scss',
                'resources/assets/vendor/scss/pages/page-auth.scss',
                'resources/assets/vendor/scss/theme-default.scss',
            ],
            refresh: true,
        }),
    ],
});
