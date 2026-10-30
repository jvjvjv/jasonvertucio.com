import { createTheme, type Shadows } from "@mui/material/styles";

import { components } from "./components";
import { color, font, radius, spacingUnit, typeScale } from "./tokens";

const heading = {
    fontFamily: font.heading,
    fontWeight: 700,
    lineHeight: 1.2,
};

export const theme = createTheme({
    spacing: spacingUnit,
    // The project doesn't use box shadows, so no elevation casts one. Borders
    // separate surfaces instead.
    shadows: Array<string>(25).fill("none") as Shadows,
    shape: {
        borderRadius: radius.control,
    },
    palette: {
        primary: { main: color.blue },
        secondary: { main: color.orange },
        success: { main: color.success },
        warning: { main: color.warning },
        error: { main: color.error },
        info: { main: color.info },
        text: {
            primary: color.ink,
            secondary: color.inkMuted,
        },
        background: {
            default: color.paper,
            paper: color.surface,
        },
        divider: color.line,
    },
    typography: {
        fontFamily: font.body,
        h1: heading,
        h2: heading,
        h3: heading,
        h4: { ...heading, fontSize: typeScale.pageTitle },
        h5: { ...heading, fontSize: typeScale.title },
        h6: { fontSize: typeScale.section, fontWeight: 600 },
        subtitle1: { fontSize: "1rem", fontWeight: 600 },
        subtitle2: { fontSize: typeScale.small, fontWeight: 600 },
        body1: { fontSize: typeScale.body },
        body2: { fontSize: typeScale.small },
        caption: { fontSize: typeScale.caption },
        overline: {
            fontSize: typeScale.caption,
            fontWeight: 600,
            letterSpacing: "normal",
            textTransform: "none",
        },
    },
    components,
});
