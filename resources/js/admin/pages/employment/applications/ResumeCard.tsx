import { Link as InertiaLink } from "@inertiajs/react";
import DeleteOutlineIcon from "@mui/icons-material/DeleteOutline";
import EditNoteIcon from "@mui/icons-material/EditNote";
import PictureAsPdfIcon from "@mui/icons-material/PictureAsPdf";
import StickyNote2Icon from "@mui/icons-material/StickyNote2";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import Typography from "@mui/material/Typography";

import type { TargetedResume } from "@/types";

interface ResumeCardProps {
    targetedResume: TargetedResume | null;
    /** Label of the main resume version recorded for the application. */
    resumeVersion: string | null;
    /**
     * The application has an `applied` entry, so its targeted resume is the
     * record of what was sent and can no longer be discarded.
     */
    hasApplied: boolean;
    onDiscard: () => void;
}

/**
 * The resume an application uses: its targeted resume, with downloads, Edit
 * and Discard, or — when it has none — the main resume version on record.
 */
export default function ResumeCard({
    targetedResume,
    resumeVersion,
    hasApplied,
    onDiscard,
}: ResumeCardProps) {
    if (targetedResume === null) {
        return (
            <Card variant="outlined">
                <CardContent sx={{ py: 1.5, "&:last-child": { pb: 1.5 } }}>
                    <Typography
                        variant="overline"
                        color="text.secondary"
                        sx={{ display: "block", lineHeight: 1.5 }}
                    >
                        Main resume
                    </Typography>
                    <Typography variant="subtitle2">
                        {resumeVersion !== null
                            ? `Version ${resumeVersion}`
                            : "No version recorded"}
                    </Typography>
                    <Typography variant="caption" color="text.secondary">
                        No targeted resume has been built for this application.
                    </Typography>
                </CardContent>
            </Card>
        );
    }

    const downloadBase = `/admin/resume/targeted-resume/${targetedResume.id}/download`;

    return (
        <Card variant="outlined">
            <CardContent sx={{ py: 1.5, "&:last-child": { pb: 1.5 } }}>
                <Typography
                    variant="overline"
                    color="text.secondary"
                    sx={{ display: "block", lineHeight: 1.5 }}
                >
                    Targeted resume
                </Typography>
                <Typography variant="subtitle2">
                    {targetedResume.title ?? "Untitled"}
                </Typography>
                <Typography
                    variant="caption"
                    color="text.secondary"
                    sx={{ display: "block" }}
                >
                    {targetedResume.resume_version !== null
                        ? `Tailored from version ${targetedResume.resume_version}`
                        : "Base version not recorded"}
                    {hasApplied
                        ? " · This is the resume sent with the application."
                        : ""}
                </Typography>
                <Box
                    sx={{ display: "flex", flexWrap: "wrap", gap: 1, mt: 1.5 }}
                >
                    <Button
                        component={InertiaLink}
                        href={`/admin/resume/targeted-resumes/${targetedResume.id}/edit`}
                        size="small"
                        variant="contained"
                        startIcon={<EditNoteIcon />}
                    >
                        Edit
                    </Button>
                    <Button
                        component="a"
                        href={`${downloadBase}/docx`}
                        size="small"
                        variant="outlined"
                        startIcon={<StickyNote2Icon />}
                    >
                        DOCX
                    </Button>
                    <Button
                        component="a"
                        href={`${downloadBase}/pdf`}
                        size="small"
                        variant="outlined"
                        startIcon={<PictureAsPdfIcon />}
                    >
                        PDF
                    </Button>
                    {hasApplied ? null : (
                        <Button
                            size="small"
                            color="error"
                            startIcon={<DeleteOutlineIcon />}
                            onClick={onDiscard}
                            sx={{ ml: "auto" }}
                        >
                            Discard
                        </Button>
                    )}
                </Box>
            </CardContent>
        </Card>
    );
}
