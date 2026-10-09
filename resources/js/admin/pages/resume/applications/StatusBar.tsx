import Box from "@mui/material/Box";
import Typography from "@mui/material/Typography";

import useGhostedAfterDays from "./useGhostedAfterDays";

import type { ConversationUsage, StatusUpdate } from "@/types";
import type { SxProps } from "@mui/material";

import StatusChip from "@/admin/components/StatusChip";
import UsageChip from "@/admin/components/UsageChip";
import { resolveApplicationDisplayStatus } from "@/admin/utils/applicationStatus";
import { formatCalendarDate } from "@/utils/date";
import mergeSx from "@/utils/mergeSx";

interface StatusBarProps {
    /** The application's stored status; the ghosted display status is derived here. */
    status: string;
    statusUpdates: StatusUpdate[];
    fitScore: number | null;
    /** AI usage of the application's session; omit when it has none. */
    usage?: ConversationUsage | null;
    sx?: SxProps;
}

export default function StatusBar({
    status,
    statusUpdates,
    fitScore,
    usage,
    sx,
}: StatusBarProps) {
    const latestUpdate =
        statusUpdates.length > 0
            ? statusUpdates[statusUpdates.length - 1]
            : null;

    const ghostedAfterDays = useGhostedAfterDays();
    const displayStatus = resolveApplicationDisplayStatus(
        status,
        latestUpdate?.occurred_at,
        ghostedAfterDays,
    );

    return (
        <Box
            sx={mergeSx(
                {
                    display: "flex",
                    gap: 2,
                    mb: 2,
                    alignItems: "center",
                    flexWrap: "wrap",
                },
                sx,
            )}
        >
            <StatusChip status={displayStatus} />
            {usage ? <UsageChip usage={usage} /> : null}
            {fitScore !== null ? (
                <Typography variant="caption" color="text.secondary">
                    Fit: {fitScore}%
                </Typography>
            ) : null}
            {latestUpdate ? (
                <Typography variant="caption" color="text.secondary">
                    {latestUpdate.status.charAt(0).toUpperCase() +
                        latestUpdate.status.slice(1)}
                    : {formatCalendarDate(latestUpdate.occurred_at)}
                </Typography>
            ) : null}
        </Box>
    );
}
