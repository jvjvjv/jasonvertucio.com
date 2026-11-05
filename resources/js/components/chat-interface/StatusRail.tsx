import Box from "@mui/material/Box";
import { keyframes } from "@mui/material/styles";

import type { ChatStatus } from "./chatStatus";

/** The segment is a quarter of the rail, so 400% carries it fully off the right edge. */
const travel = keyframes`
    from {
        transform: translateX(-100%);
    }
    to {
        transform: translateX(400%);
    }
`;

/** The rail's own colour; a working rail is the track its segment travels along. */
const RAIL_COLOUR = {
    ready: "primary.main",
    idle: "divider",
    working: "divider",
    warning: "warning.main",
    error: "error.main",
} as const;

interface StatusRailProps {
    status: ChatStatus;
}

/**
 * The 3px line across the composer's top edge. Its colour gives the state at
 * a glance; a travelling segment says work is under way. Decorative: the
 * caption line carries the same state in words.
 */
export default function StatusRail({ status }: StatusRailProps) {
    const isWorking = status.tone === "working";

    return (
        <Box
            aria-hidden="true"
            data-testid="chat-status-rail"
            sx={{
                flex: "none",
                height: 3,
                overflow: "hidden",
                bgcolor: RAIL_COLOUR[status.tone],
                transition: "background-color 200ms ease",
            }}
        >
            {isWorking ? (
                <Box
                    sx={{
                        width: "25%",
                        height: "100%",
                        bgcolor: "secondary.main",
                        // Parked in the middle. Only a moving status that the
                        // viewer hasn't asked to keep still sets it travelling.
                        transform: "translateX(150%)",
                        ...(status.moving
                            ? {
                                  "@media (prefers-reduced-motion: no-preference)":
                                      {
                                          animation: `${travel} 1.6s linear infinite`,
                                      },
                              }
                            : {}),
                    }}
                />
            ) : null}
        </Box>
    );
}
