import Box from "@mui/material/Box";
import Typography from "@mui/material/Typography";

import type { Conversation, StatusUpdate, TargetedResume } from "@/types";
import type { SxProps } from "@mui/material";

import StatusChip from "@/admin/components/StatusChip";
import UsageChip from "@/admin/components/UsageChip";
import { resolveTargetedResumeDisplayStatus } from "@/admin/utils/targetedResumeStatus";
import { formatCalendarDate } from "@/utils/date";
import mergeSx from "@/utils/mergeSx";

interface TargetedBuilderStatusBarProps {
    conversation: Conversation;
    targetedResume: TargetedResume | null;
    statusUpdates?: StatusUpdate[];
    sx?: SxProps;
}

export default function TargetedBuilderStatusBar({
    conversation,
    targetedResume,
    statusUpdates,
    sx,
}: TargetedBuilderStatusBarProps) {
    const fitScore: number | null = (targetedResume?.fit_score ??
        conversation.context?.fit_score) as number | null;

    const updates = statusUpdates ?? targetedResume?.status_updates ?? [];
    const latestUpdate =
        updates.length > 0 ? updates[updates.length - 1] : null;

    const displayStatus = resolveTargetedResumeDisplayStatus({
        conversationStatus: conversation.status,
        resumeStatus: targetedResume?.status,
        latestStatusOccurredAt: latestUpdate?.occurred_at,
    });

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
            <UsageChip usage={conversation.usage} />
            {fitScore && (
                <Typography variant="caption" color="text.secondary">
                    Fit: {fitScore}%
                </Typography>
            )}
            {latestUpdate && (
                <Typography variant="caption" color="text.secondary">
                    {latestUpdate.status.charAt(0).toUpperCase() +
                        latestUpdate.status.slice(1)}
                    : {formatCalendarDate(latestUpdate.occurred_at)}
                </Typography>
            )}
        </Box>
    );
}
