import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { ThemeProvider } from '@mui/material/styles';
import CssBaseline from '@mui/material/CssBaseline';
import { theme } from './theme';

createInertiaApp({
    title: (title) => {
        if (!title) {
            return 'Admin | Jason Vertucio';
        }

        return `${title} | Admin | Jason Vertucio`;
    },
    resolve: (name) => {
        const pages = import.meta.glob('./pages/**/*.tsx', { eager: true });
        const page = pages[`./pages/${name}.tsx`];
        if (!page) {
            throw new Error(`Page not found: ${name}`);
        }
        return page;
    },
    setup({ el, App, props }) {
        createRoot(el).render(
            <ThemeProvider theme={theme}>
                <CssBaseline />
                <App {...props} />
            </ThemeProvider>
        );
    },
});
