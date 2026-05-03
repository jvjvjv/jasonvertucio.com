import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Typography from "@mui/material/Typography";
import StickyNote2Icon from "@mui/icons-material/StickyNote2";
import PictureAsPdfIcon from "@mui/icons-material/PictureAsPdf";
import StatusChip from "../../../components/StatusChip";
import UsageChip from "../../../components/UsageChip";
import type { Conversation, CoverLetter, TargetedResume } from "../../../types";

interface TargetedBuilderStatusBarProps {
    conversation: Conversation;
    targetedResume: TargetedResume | null;
    coverLetter: CoverLetter | null;
    onApplied: () => void;
    onPass: () => void;
}

export default function TargetedBuilderStatusBar({
    conversation,
    targetedResume,
    coverLetter,
    onApplied,
    onPass,
}: TargetedBuilderStatusBarProps) {
    const isApplied = targetedResume?.status === "applied";

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
            <Box sx={{ flexGrow: 1 }} />
            <Button
                size="small"
                color="success"
                variant="outlined"
                onClick={onApplied}
                disabled={!targetedResume || isApplied}
            >
                {isApplied ? "Applied" : "Mark Applied"}
            </Button>
            {conversation.status === "active" && (
                <Button
                    size="small"
                    color="warning"
                    variant="outlined"
                    onClick={onPass}
                >
                    Pass
                </Button>
            )}
            <Box sx={{ display: "flex", gap: 1, flexWrap: "wrap" }}>
                {targetedResume && (
                    <>
                        {targetedResume.docx_path && (
                            <Button
                                size="small"
                                variant="outlined"
                                component="a"
                                color="success"
                                href={`/admin/resume/targeted-resume/${targetedResume.id}/StickyNote2/docx`}
                            >
                                <StickyNote2Icon />
                            </Button>
                        )}
                        {targetedResume.pdf_path && (
                            <Button
                                size="small"
                                variant="outlined"
                                component="a"
                                color="success"
                                href={`/admin/resume/targeted-resume/${targetedResume.id}/StickyNote2/pdf`}
                            >
                                <PictureAsPdfIcon />
                            </Button>
                        )}
                    </>
                )}
                {coverLetter && (
                    <>
                        {coverLetter.docx_path && (
                            <Button
                                size="small"
                                variant="outlined"
                                component="a"
                                color="secondary"
                                href={`/admin/cover-letters/${coverLetter.id}/StickyNote2/docx`}
                            >
                                <StickyNote2Icon />
                            </Button>
                        )}
                        {coverLetter.pdf_path && (
                            <Button
                                size="small"
                                variant="outlined"
                                component="a"
                                color="secondary"
                                href={`/admin/cover-letters/${coverLetter.id}/StickyNote2/pdf`}
                            >
                                <PictureAsPdfIcon />
                            </Button>
                        )}
                    </>
                )}
            </Box>
        </Box>
    );
}
