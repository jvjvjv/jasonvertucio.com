/**
 * Keyboard focus ring for the composer's action margin. The surface's
 * `:focus-within` outline stays lit while a button has focus, so each button
 * needs its own ring to say which control has it.
 */
export const MARGIN_FOCUS_SX = {
    "&.Mui-focusVisible": {
        outline: "2px solid",
        outlineColor: "primary.main",
        outlineOffset: "2px",
    },
} as const;
