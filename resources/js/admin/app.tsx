import { createInertiaApp } from "@inertiajs/react";
import CssBaseline from "@mui/material/CssBaseline";
import { ThemeProvider } from "@mui/material/styles";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createRoot } from "react-dom/client";

import { theme } from "./theme";

import { followNonInertiaResponses } from "@/utils/nonInertiaNavigation";

followNonInertiaResponses();

void createInertiaApp({
    title: (title) => {
        if (!title) {
            return "Admin | Jason Vertucio";
        }

        return `${title} | Admin | Jason Vertucio`;
    },
    // Pages are loaded on demand, one chunk each, rather than shipping every
    // admin screen — the charts, the markdown editor, the date pickers — to
    // whichever page happens to be opened first.
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob("./pages/**/*.tsx"),
        ),
    setup({ el, App, props }) {
        createRoot(el).render(
            <ThemeProvider theme={theme}>
                <CssBaseline />
                <App {...props} />
            </ThemeProvider>,
        );
    },
});
