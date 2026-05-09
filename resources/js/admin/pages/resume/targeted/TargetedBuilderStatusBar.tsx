import Box from "@mui/material/Box";
import Typography from "@mui/material/Typography";
import StatusChip from "../../../components/StatusChip";
import UsageChip from "../../../components/UsageChip";
import type { Conversation, TargetedResume } from "../../../types";

interface TargetedBuilderStatusBarProps {
    conversation: Conversation;
    targetedResume: TargetedResume | null;
}

export default function TargetedBuilderStatusBar({
    conversation,
    targetedResume,
}: TargetedBuilderStatusBarProps) {
    const fitScore =
        targetedResume?.fit_score ?? conversation.targeted_resume?.fit_score;
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
            <UsageChip usage={conversation.usage} />
        </Box>
    );
}
