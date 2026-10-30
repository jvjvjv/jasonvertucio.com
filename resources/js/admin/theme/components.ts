import { border, color, radius, typeScale } from "./tokens";

import type { Components, Theme } from "@mui/material/styles";

const focusRing = {
    outline: border.focus,
    outlineOffset: 2,
};

/**
 * MUI's defaults, restyled, so that every admin page picks up the same look
 * without being edited. Borders do the work of shadows here: the project
 * doesn't use box shadows or gradients.
 */
export const components: Components<Theme> = {
    MuiCssBaseline: {
        styleOverrides: {
            "@media (prefers-reduced-motion: reduce)": {
                "*, *::before, *::after": {
                    animationDuration: "0.01ms !important",
                    animationIterationCount: "1 !important",
                    transitionDuration: "0.01ms !important",
                    scrollBehavior: "auto !important",
                },
            },
        },
    },
    MuiButtonBase: {
        defaultProps: {
            disableRipple: true,
        },
        styleOverrides: {
            root: {
                "&.Mui-focusVisible": focusRing,
            },
        },
    },
    MuiButton: {
        defaultProps: {
            disableElevation: true,
        },
        styleOverrides: {
            root: {
                textTransform: "none",
                borderRadius: radius.control,
                fontWeight: 600,
            },
        },
    },
    MuiLink: {
        styleOverrides: {
            root: {
                color: color.blue,
                "&:focus-visible": focusRing,
            },
        },
    },
    MuiPaper: {
        styleOverrides: {
            root: {
                boxShadow: "none",
            },
        },
    },
    MuiCard: {
        styleOverrides: {
            root: {
                border: border.hairline,
                borderRadius: radius.surface,
            },
        },
    },
    MuiInputBase: {
        styleOverrides: {
            inputMultiline: {
                resize: "vertical",
            },
        },
    },
    MuiOutlinedInput: {
        styleOverrides: {
            root: {
                borderRadius: radius.control,
                backgroundColor: color.surface,
            },
            notchedOutline: {
                borderColor: color.lineStrong,
            },
        },
    },
    MuiTableCell: {
        styleOverrides: {
            root: {
                borderBottom: border.hairline,
            },
            head: {
                color: color.inkMuted,
                fontSize: typeScale.small,
                fontWeight: 600,
                lineHeight: 1.3,
            },
            sizeSmall: {
                padding: "6px 12px",
            },
        },
    },
    MuiTableRow: {
        styleOverrides: {
            root: {
                "&:last-child td": {
                    borderBottom: 0,
                },
            },
        },
    },
    MuiChip: {
        styleOverrides: {
            root: {
                fontWeight: 500,
            },
        },
    },
};
