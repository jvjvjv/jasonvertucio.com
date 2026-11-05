import Box from "@mui/material/Box";
import { darken } from "@mui/material/styles";

import type { ChatStatus } from "./chatStatus";
import type { Theme } from "@mui/material/styles";
import type { ReactNode } from "react";

/**
 * Kept in the accessibility tree but takes no space. Deliberately not
 * `position: absolute`, so nothing in the composer is positioned out of flow.
 */
const VISUALLY_HIDDEN_SX = {
    display: "inline-block",
    width: "1px",
    height: "1px",
    margin: "-1px",
    overflow: "hidden",
    clipPath: "inset(50%)",
    whiteSpace: "nowrap",
} as const;

/**
 * `warning.dark` alone is about 3.8:1 on white, under the 4.5:1 that 13px
 * text needs, so warning words are darkened from the theme's own colour.
 */
const WORDS_COLOUR = {
    ready: "text.secondary",
    idle: "text.secondary",
    working: "text.secondary",
    warning: (theme: Theme) => darken(theme.palette.warning.dark, 0.2),
    error: "error.main",
} as const;

interface StatusCaptionProps {
    status: ChatStatus;
    /** Shown on the right, e.g. the send shortcut. Omit to hide. */
    hint?: ReactNode;
}

/**
 * The line under the composer that says in words what the rail shows: the
 * status on the left, an optional hint on the right. Warnings and errors wrap
 * across the full width; everything else stays on one line.
 */
export default function StatusCaption({ status, hint }: StatusCaptionProps) {
    const wraps = status.tone === "warning" || status.tone === "error";

    const words =
        status.kind === "ready" ? (
            <>
                <Box component="span" sx={VISUALLY_HIDDEN_SX}>
                    {status.text === "" ? "Ready" : "Ready on "}
                </Box>
                {status.text}
            </>
        ) : status.kind === "recording" ? (
            <>
                <Box
                    component="span"
                    aria-hidden="true"
                    sx={{
                        display: "inline-block",
                        width: 8,
                        height: 8,
                        borderRadius: "50%",
                        bgcolor: "error.main",
                        mr: 0.75,
                        verticalAlign: "middle",
                    }}
                />
                {status.text}
            </>
        ) : (
            status.text
        );

    return (
        <Box
            sx={{
                display: "flex",
                alignItems: "flex-start",
                gap: 2,
                mt: 0.75,
                minHeight: 18,
                fontSize: 13,
                lineHeight: "18px",
            }}
        >
            <Box
                role="status"
                aria-live="polite"
                sx={{
                    flex: "1 1 auto",
                    minWidth: 0,
                    color: WORDS_COLOUR[status.tone],
                    ...(wraps
                        ? { overflowWrap: "anywhere" }
                        : {
                              whiteSpace: "nowrap",
                              overflow: "hidden",
                              textOverflow: "ellipsis",
                          }),
                }}
            >
                {status.tone === "error" ? (
                    <Box component="span" role="alert">
                        {words}
                    </Box>
                ) : (
                    words
                )}
            </Box>
            {hint ? (
                <Box
                    sx={{
                        flex: "none",
                        color: "text.secondary",
                        whiteSpace: "nowrap",
                    }}
                >
                    {hint}
                </Box>
            ) : null}
        </Box>
    );
}
