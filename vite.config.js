import { defineConfig } from 'vite';
import fs from 'fs';
import path from 'path';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import { viteStaticCopy } from 'vite-plugin-static-copy';

/**
 * Chunking.
 *
 * Rollup's default puts everything a lazy page shares with no other page into
 * that page's chunk, so the markdown editor's whole dependency tree (~1 MB,
 * more than half of it Prism grammars) landed in one file. The rules below
 * give the heavy libraries chunks of their own, aiming for files under
 * ~128 kB.
 *
 * Two properties of `manualChunks` shape every rule here:
 *
 * - A manual chunk swallows each static dependency of its modules that no rule
 *   has claimed. So a rule's packages must not depend on an unclaimed package,
 *   or that package's code moves into the chunk and every other user of it
 *   starts loading the chunk. `vendorChunks` is ordered and ends in a
 *   catch-all for that reason; only `rollupSplit` is deliberately unclaimed.
 * - The chunk graph must stay acyclic. A cycle between chunks can run a module
 *   before an import it needs at load time is initialised.
 */

/**
 * Left to Rollup's own splitting. MUI is imported a component at a time, and
 * Rollup already shares it out per page far better than one 260 kB chunk
 * would. Nothing claimed below may depend on these.
 */
const rollupSplit = ['@mui/', '@emotion/', '@popperjs/', 'react-transition-group', 'stylis', 'hoist-non-react-statics', 'dayjs'];

/** Chunk name -> the packages in it. A trailing `/` or `-` makes it a prefix. First match wins. */
const vendorChunks = {
    'vendor-react': ['react', 'react-dom', 'scheduler'],
    'vendor-shared': ['clsx', 'prop-types', 'react-is', '@babel/runtime', 'use-sync-external-store', 'object-assign'],
    'vendor-inertia': ['@inertiajs/', 'laravel-precognition'],
    'vendor-http': ['axios', 'qs', 'lodash-es'],
    'vendor-recharts': ['recharts'],
    'vendor-chart-state': ['@reduxjs/toolkit', 'redux', 'redux-thunk', 'reselect', 'immer', 'react-redux'],
    'vendor-chart-math': ['d3-', 'victory-vendor', 'internmap', 'decimal.js-light', 'eventemitter3', 'es-toolkit', 'tiny-invariant'],
    'vendor-html-parser': ['parse5'],
    'vendor-entities': ['entities'],
    'vendor-md-editor': ['@uiw/'],
    'vendor-micromark': ['micromark', 'micromark-'],
    'vendor-unist': ['unist-', 'unified', 'vfile', 'vfile-'],
    'vendor-hast': [
        'hast-',
        'hastscript',
        'property-information',
        'mdast-util-to-hast',
        'css-selector-parser',
        'nth-check',
        'bcp-47-match',
        'direction',
    ],
    'vendor-mdast': ['mdast-', 'remark-', 'react-markdown'],
    'vendor-rehype': ['rehype', 'rehype-'],
    'vendor-sanitize': ['dompurify', 'marked'],
    'vendor-virtuoso': ['react-virtuoso'],
    'vendor-dnd': ['@dnd-kit/'],
};

function matches(name, patterns) {
    return patterns.some((pattern) => (/[/-]$/.test(pattern) ? name.startsWith(pattern) : name === pattern));
}

/**
 * Prism grammars that other grammars import (clike, markup, javascript...).
 * They live with Prism's core, so every other grammar chunk depends only on
 * that one and the grammar chunks cannot form a cycle among themselves.
 */
const grammarDir = path.resolve(__dirname, 'node_modules/refractor/lang');
const sharedGrammars = new Set(
    fs.existsSync(grammarDir)
        ? fs
              .readdirSync(grammarDir)
              .filter((file) => file.endsWith('.js'))
              .flatMap((file) =>
                  [...fs.readFileSync(path.join(grammarDir, file), 'utf8').matchAll(/from '\.\/([^']+)\.js'/g)].map((match) => match[1]),
              )
        : [],
);

/**
 * The remaining grammars, dealt alphabetically into chunks of roughly equal
 * source size. Sized from the files on disk so a refractor upgrade that adds
 * grammars adds chunks instead of growing one past the target.
 */
const GRAMMAR_CHUNK_SOURCE_BYTES = 140_000;
const grammarChunks = new Map();

if (fs.existsSync(grammarDir)) {
    let chunk = 1;
    let bytes = 0;

    for (const file of fs.readdirSync(grammarDir).sort()) {
        const grammar = file.endsWith('.js') ? file.slice(0, -3) : null;

        if (!grammar || sharedGrammars.has(grammar)) {
            continue;
        }

        const size = fs.statSync(path.join(grammarDir, file)).size;

        if (bytes > 0 && bytes + size > GRAMMAR_CHUNK_SOURCE_BYTES) {
            chunk += 1;
            bytes = 0;
        }

        bytes += size;
        grammarChunks.set(grammar, `vendor-prism-${chunk}`);
    }
}

function prismChunk(file) {
    const grammar = file.match(/^lang\/(.+)\.js$/)?.[1];

    if (!grammar) {
        return file === 'lib/all.js' || file === 'lib/common.js' ? 'vendor-prism' : 'vendor-prism-core';
    }

    return grammarChunks.get(grammar) ?? 'vendor-prism-core';
}

function manualChunks(id) {
    if (!id.includes('node_modules/')) {
        return undefined;
    }

    const segments = id.split('node_modules/').pop().split('/');
    const scoped = segments[0].startsWith('@');
    const name = scoped ? `${segments[0]}/${segments[1]}` : segments[0];

    if (matches(name, rollupSplit)) {
        return undefined;
    }

    if (name === 'refractor') {
        return prismChunk(segments.slice(1).join('/'));
    }

    return Object.keys(vendorChunks).find((chunk) => matches(name, vendorChunks[chunk])) ?? 'vendor-misc';
}

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
    watch: {
      ignored: ['**/vendor/**', '**/storage/**', '**/.git/**'],
    },
  },
    plugins: [
        laravel({
            input: [
                'resources/js/app.js',
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
                { src: 'resources/img/*', dest: '../img' },
                { src: 'resources/wp-includes/*', dest: '../wp-includes' },
                { src: 'resources/wp-admin/*', dest: '../wp-admin' },
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
        rollupOptions: {
            output: {
                manualChunks,
            },
        },
    },
});
