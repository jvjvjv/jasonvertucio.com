import { defineConfig } from 'vite';
import path from 'path';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import { viteStaticCopy } from 'vite-plugin-static-copy';

export default defineConfig({
  server: {
    host: 'localhost',
    port: 5173,
    strictPort: true,
    hmr: {
      host: 'localhost',
      protocol: 'ws',
      clientPort: 5173,
    },
  },
    plugins: [
        laravel({
            input: [
                'resources/js/app.js',
                'resources/js/resume.js',
                'resources/js/currently-watching.js',
                'resources/js/font-loader.js',
                'resources/js/home.js',
                'resources/js/admin/app.tsx',
                'resources/js/chat/app.tsx',
                'resources/css/app.css',
                'resources/css/blog.css',
                'resources/css/resume.css',
                'resources/css/cover-letter.css',
            ],
            refresh: true,
        }),
        react(),
        viteStaticCopy({
            targets: [
                { src: 'resources/config/config.json', dest: '' },
                { src: 'resources/img/*', dest: 'img' },
                { src: 'resources/wp-includes/*', dest: 'wp-includes' },
                { src: 'resources/wp-admin/*', dest: 'wp-admin' },
                { src: 'node_modules/@fortawesome/fontawesome-free/webfonts/*', dest: 'webfonts' },
            ],
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
        },
    },
    build: {
        sourcemap: process.env.NODE_ENV !== 'production',
    },
});
