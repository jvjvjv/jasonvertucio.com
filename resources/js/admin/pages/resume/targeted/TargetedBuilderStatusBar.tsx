import Box from "@mui/material/Box";
import Typography from "@mui/material/Typography";

import type { Conversation, StatusUpdate, TargetedResume } from "@/types";

import StatusChip from "@/admin/components/StatusChip";
import UsageChip from "@/admin/components/UsageChip";

interface TargetedBuilderStatusBarProps {
    conversation: Conversation;
    targetedResume: TargetedResume | null;
    statusUpdates?: StatusUpdate[];
}

export default function TargetedBuilderStatusBar({
    conversation,
    targetedResume,
    statusUpdates,
}: TargetedBuilderStatusBarProps) {
    const fitScore: number | null = (targetedResume?.fit_score ??
        conversation.context?.fit_score) as number | null;

    const updates = statusUpdates ?? targetedResume?.status_updates ?? [];
    const latestUpdate =
        updates.length > 0 ? updates[updates.length - 1] : null;

    const displayStatus =
        targetedResume?.status && !["draft"].includes(targetedResume.status)
            ? targetedResume.status
            : conversation.status;

    return (
        <Box
            sx={{
                display: "flex",
                gap: 2,
                mb: 2,
                alignItems: "center",
                flexWrap: "wrap",
            }}
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
                    :{" "}
                    {new Date(latestUpdate.occurred_at).toLocaleDateString(
                        undefined,
                        { year: "numeric", month: "short", day: "numeric" },
                    )}
                </Typography>
            )}
        </Box>
    );
}
