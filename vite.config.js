import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { viteStaticCopy } from 'vite-plugin-static-copy';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/app.js',
                'resources/js/resume.js',
                'resources/js/currently-watching.js',
                'resources/js/font-loader.js',
                'resources/js/home.js',
                'resources/js/canvas-ui/app.js',
                'resources/css/app.css',
                'resources/css/blog.css',
                'resources/css/canvas-ui.css',
            ],
            refresh: true,
        }),
        vue(),
        viteStaticCopy({
            targets: [
                { src: 'resources/config/config.json', dest: '' },
                { src: 'resources/img/*', dest: 'img' },
                { src: 'resources/wp-includes/*', dest: 'wp-includes' },
                { src: 'resources/wp-admin/*', dest: 'wp-admin' },
            ],
        }),
    ],
    build: {
        sourcemap: true,
    },
});
