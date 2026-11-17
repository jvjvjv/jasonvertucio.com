import Box from "@mui/material/Box";
import Typography from "@mui/material/Typography";

import FitScore from "./FitScore";

import type { CompanyGhosting, ConversationUsage, StatusUpdate } from "@/types";
import type { SxProps } from "@mui/material";
import type { ReactNode } from "react";

import StatusChip from "@/admin/components/StatusChip";
import UsageChip from "@/admin/components/UsageChip";
import { formatCalendarDate } from "@/utils/date";
import mergeSx from "@/utils/mergeSx";

interface StatusBarProps {
    /** The application's stored status. */
    status: string;
    statusUpdates: StatusUpdate[];
    fitScore: number | null;
    ghosting: CompanyGhosting;
    /** AI usage of the application's session; omit when it has none. */
    usage?: ConversationUsage | null;
    /** Shown at the end of the bar, pushed to the right. */
    action?: ReactNode;
    sx?: SxProps;
}

export default function StatusBar({
    status,
    statusUpdates,
    fitScore,
    ghosting,
    usage,
    action,
    sx,
}: StatusBarProps) {
    const latestUpdate =
        statusUpdates.length > 0
            ? statusUpdates[statusUpdates.length - 1]
            : null;

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
            <StatusChip status={status} />
            {usage ? <UsageChip usage={usage} /> : null}
            <FitScore fitScore={fitScore} ghosting={ghosting} layout="inline" />
            {latestUpdate ? (
                <Typography variant="caption" color="text.secondary">
                    {latestUpdate.status.charAt(0).toUpperCase() +
                        latestUpdate.status.slice(1)}
                    : {formatCalendarDate(latestUpdate.occurred_at)}
                </Typography>
            ) : null}
            {action ? (
                <Box sx={{ ml: "auto", minWidth: 0 }}>{action}</Box>
            ) : null}
        </Box>
    );
}
