import { createInertiaApp } from "@inertiajs/react";
import CssBaseline from "@mui/material/CssBaseline";
import { ThemeProvider } from "@mui/material/styles";
import { createRoot } from "react-dom/client";

import { theme } from "./theme";

import type { ComponentType } from "react";

void createInertiaApp({
    title: (title) => {
        if (!title) {
            return "Admin | Jason Vertucio";
        }

        return `${title} | Admin | Jason Vertucio`;
    },
    resolve: (name) => {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call
        const pages = import.meta.glob("./pages/**/*.tsx", { eager: true }) as {
            [key: string]: { default: ComponentType } | undefined;
        };
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
            </ThemeProvider>,
        );
    },
});
