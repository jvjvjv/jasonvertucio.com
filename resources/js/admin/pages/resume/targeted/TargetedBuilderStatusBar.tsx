import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Typography from "@mui/material/Typography";
import DownloadIcon from "@mui/icons-material/Download";
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
            {conversation.ai_system_name && (
                <Typography variant="caption" color="text.secondary">
                    AI: {conversation.ai_system_name}
                </Typography>
            )}
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
            {targetedResume && targetedResume.status !== "applied" && (
                <Button
                    size="small"
                    color="success"
                    variant="outlined"
                    onClick={onApplied}
                >
                    Applied
                </Button>
            )}
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
                                startIcon={<DownloadIcon />}
                                variant="outlined"
                                component="a"
                                href={`/admin/resume/targeted-resume/${targetedResume.id}/download/docx`}
                            >
                                DOCX
                            </Button>
                        )}
                        {targetedResume.pdf_path && (
                            <Button
                                size="small"
                                startIcon={<DownloadIcon />}
                                variant="outlined"
                                component="a"
                                href={`/admin/resume/targeted-resume/${targetedResume.id}/download/pdf`}
                            >
                                PDF
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
                                href={`/admin/cover-letters/${coverLetter.id}/download/docx`}
                            >
                                CL DOCX
                            </Button>
                        )}
                        {coverLetter.pdf_path && (
                            <Button
                                size="small"
                                variant="outlined"
                                component="a"
                                href={`/admin/cover-letters/${coverLetter.id}/download/pdf`}
                            >
                                CL PDF
                            </Button>
                        )}
                    </>
                )}
            </Box>
        </Box>
    );
}
