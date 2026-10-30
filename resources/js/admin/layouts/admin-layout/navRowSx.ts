import type { SxProps, Theme } from "@mui/material/styles";

import { border, color, font, typeScale } from "@/admin/theme/tokens";

interface NavRowOptions {
    level: "area" | "page";
    current?: boolean;
}

/**
 * One row of the navigation. The current page is drawn in the page's own
 * background, so it reads as a notch cut out of the blue column and joined to
 * the content beside it, with the orange bar marking it.
 */
export function navRowSx({
    level,
    current = false,
}: NavRowOptions): SxProps<Theme> {
    return {
        display: "block",
        width: "100%",
        boxSizing: "border-box",
        py: level === "area" ? 1 : 0.75,
        pr: 2,
        pl: level === "area" ? 2 : 3.5,
        borderLeft: `${String(border.currentWidth)}px solid`,
        borderLeftColor: current ? color.orange : "transparent",
        bgcolor: current ? color.paper : "transparent",
        color: current ? color.ink : color.onBlue,
        fontFamily: level === "area" ? font.heading : font.body,
        fontSize: level === "area" ? typeScale.area : typeScale.small,
        fontWeight: current ? 700 : level === "area" ? 500 : 400,
        lineHeight: 1.35,
        textAlign: "left",
        textDecoration: "none",
        "&:hover": {
            bgcolor: current ? color.paper : color.onBlueHover,
        },
        "&:focus-visible, &.Mui-focusVisible": {
            outline: current ? border.focus : border.focusOnBlue,
            outlineOffset: -3,
        },
    };
}
