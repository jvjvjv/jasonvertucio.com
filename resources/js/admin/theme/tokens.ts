/**
 * Every visual decision the admin makes, in one place. The theme is built from
 * these values, and pages reach them through the theme's names rather than as
 * literals. The brand colours and typefaces mirror `resources/css/theme.css`:
 * change them together, or the admin stops looking like the public site.
 */
export const color = {
    blue: "#1b587c",
    blueDark: "#154763",
    blueTint: "#1b587c1a",
    orange: "#b35e06",
    ink: "#25292c",
    inkMuted: "#5b6369",
    paper: "#f9fafb",
    surface: "#ffffff",
    line: "#e5e7eb",
    lineStrong: "#c4cad0",
    onBlue: "#ffffff",
    onBlueMuted: "#d5e3ec",
    onBlueHover: "#ffffff1f",
    success: "#2e7d32",
    warning: "#ed6c02",
    error: "#d32f2f",
    info: "#0288d1",
} as const;

export const font = {
    heading: '"Josefin Sans", "Impact", "Arial", sans-serif',
    body: '"Montserrat", "Arial", sans-serif',
} as const;

/** In rem, on a ratio of about 1.25 up from the body size. */
export const typeScale = {
    pageTitle: "1.875rem",
    title: "1.5rem",
    section: "1.125rem",
    area: "1.0625rem",
    body: "0.9375rem",
    small: "0.8125rem",
    caption: "0.75rem",
} as const;

/** MUI's spacing unit in pixels. */
export const spacingUnit = 8;

/** In pixels. Controls are tighter than the surfaces that hold them. */
export const radius = {
    control: 6,
    surface: 8,
} as const;

export const border = {
    hairline: `1px solid ${color.line}`,
    focus: `2px solid ${color.blue}`,
    focusOnBlue: `2px solid ${color.onBlue}`,
    /** Marks the current page in the navigation. */
    currentWidth: 4,
} as const;

/** In pixels. The sidebar matches the public homepage's. */
export const shell = {
    sidebarMd: 208,
    sidebarLg: 256,
    drawer: 280,
} as const;
