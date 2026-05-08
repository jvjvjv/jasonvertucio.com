import Box from "@mui/material/Box";
import Typography from "@mui/material/Typography";
import StatusChip from "../../../components/StatusChip";
import UsageChip from "../../../components/UsageChip";
import type { Conversation, CoverLetter, TargetedResume } from "../../../types";

interface TargetedBuilderStatusBarProps {
    conversation: Conversation;
    targetedResume: TargetedResume | null;
    coverLetter: CoverLetter | null;
}

export default function TargetedBuilderStatusBar({
    conversation,
    targetedResume,
    coverLetter,
}: TargetedBuilderStatusBarProps) {
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
            {targetedResume?.fit_score != null && (
                <Typography variant="caption" color="text.secondary">
                    Fit: {targetedResume.fit_score}%
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
