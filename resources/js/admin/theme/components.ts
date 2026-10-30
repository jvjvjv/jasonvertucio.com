import { border, color, radius, typeScale } from "./tokens";

import type { Components, Theme } from "@mui/material/styles";
import type {} from "@mui/x-date-pickers/themeAugmentation";

const focusRing = {
    outline: border.focus,
    outlineOffset: 2,
};

/** For rows in a list, whose ring would otherwise be clipped by the list. */
const insetFocusRing = {
    "&.Mui-focusVisible": { outlineOffset: -2 },
};

const floatingSurface = {
    border: border.hairline,
    borderRadius: radius.surface,
};

/**
 * MUI's defaults, restyled, so that every admin page picks up the same look
 * without being edited.
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
    MuiCard: {
        styleOverrides: {
            root: floatingSurface,
        },
    },
    MuiPopover: {
        styleOverrides: {
            paper: floatingSurface,
        },
    },
    MuiAutocomplete: {
        styleOverrides: {
            paper: floatingSurface,
        },
    },
    MuiPickerPopper: {
        styleOverrides: {
            paper: floatingSurface,
        },
    },
    MuiDialog: {
        styleOverrides: {
            paper: floatingSurface,
        },
    },
    MuiDialogActions: {
        styleOverrides: {
            root: ({ theme }) => ({
                padding: theme.spacing(1, 3, 2),
            }),
        },
    },
    MuiMenuItem: {
        styleOverrides: {
            root: insetFocusRing,
        },
    },
    MuiListItemButton: {
        styleOverrides: {
            root: insetFocusRing,
        },
    },
    MuiTabs: {
        styleOverrides: {
            indicator: {
                backgroundColor: color.orange,
            },
        },
    },
    MuiTab: {
        styleOverrides: {
            root: {
                ...insetFocusRing,
                textTransform: "none",
                fontWeight: 600,
                "&.Mui-selected": {
                    color: color.ink,
                },
            },
        },
    },
    MuiFab: {
        styleOverrides: {
            root: {
                textTransform: "none",
                fontWeight: 600,
            },
        },
    },
    MuiToggleButton: {
        styleOverrides: {
            root: {
                textTransform: "none",
            },
        },
    },
    MuiAccordion: {
        styleOverrides: {
            root: {
                border: border.hairline,
                "&::before": {
                    display: "none",
                },
            },
        },
    },
    MuiAlert: {
        styleOverrides: {
            root: {
                borderRadius: radius.control,
            },
        },
    },
    MuiTooltip: {
        styleOverrides: {
            tooltip: {
                backgroundColor: color.ink,
                fontSize: typeScale.caption,
            },
            arrow: {
                color: color.ink,
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
