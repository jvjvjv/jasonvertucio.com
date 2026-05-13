import Box from "@mui/material/Box";
import Typography from "@mui/material/Typography";

import type { Conversation, TargetedResume } from "@/types";

import StatusChip from "@/admin/components/StatusChip";
import UsageChip from "@/admin/components/UsageChip";

interface TargetedBuilderStatusBarProps {
    conversation: Conversation;
    targetedResume: TargetedResume | null;
}

export default function TargetedBuilderStatusBar({
    conversation,
    targetedResume,
}: TargetedBuilderStatusBarProps) {
    const fitScore: number | null = (targetedResume?.fit_score ??
        conversation.context?.fit_score) as number | null;
    console.log({ conversation, fitScore, targetedResume });
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
            <StatusChip
                status={
                    targetedResume?.status === "finalized"
                        ? "finalized"
                        : conversation.status
                }
            />
            <UsageChip usage={conversation.usage} />
            {fitScore && (
                <Typography variant="caption" color="text.secondary">
                    Fit: {fitScore}%
                </Typography>
            )}
            {targetedResume?.applied_at && (
                <Typography variant="caption" color="text.secondary">
                    Applied: {targetedResume.applied_at}
                </Typography>
            )}
        </Box>
    );
}
