import { createInertiaApp } from "@inertiajs/react";
import CssBaseline from "@mui/material/CssBaseline";
import { ThemeProvider } from "@mui/material/styles";
import { createRoot } from "react-dom/client";

import { theme } from "../admin/theme";

import { followNonInertiaResponses } from "@/utils/nonInertiaNavigation";

followNonInertiaResponses();

void createInertiaApp({
    title: (title) => (title ? `${title} | Jason Vertucio` : "Jason Vertucio"),
    resolve: (name) => {
        const pages = import.meta.glob("./pages/**/*.tsx", { eager: true });

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
