import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Typography from "@mui/material/Typography";
import MDEditor from "@uiw/react-md-editor";
import { useState } from "react";

import useUpdateTailoredMarkdown from "./useUpdateTailoredMarkdown";

import type { TargetedResume } from "@/types";

import "@uiw/react-md-editor/markdown-editor.css";

interface TailoredResumeEditorProps {
    targetedResume: TargetedResume;
}

export default function TailoredResumeEditor({
    targetedResume,
}: TailoredResumeEditorProps) {
    const [markdown, setMarkdown] = useState(
        targetedResume.tailored_content ?? "",
    );

    const { isSaving, saveError, saveMarkdown } = useUpdateTailoredMarkdown({
        targetedResumeId: targetedResume.id,
    });

    return (
        <Box sx={{ p: { xs: 1.5, md: 3 } }}>
            <Typography variant="body2" sx={{ mb: 2, color: "text.secondary" }}>
                Edit the finalized resume markdown directly. Saving regenerates
                the DOCX and PDF, and lets the chat agent know the resume was
                changed outside of chat.
            </Typography>

            {saveError && (
                <Alert severity="error" sx={{ mb: 2 }}>
                    {saveError}
                </Alert>
            )}

            <Box data-color-mode="light" sx={{ mb: 2 }}>
                <MDEditor
                    value={markdown}
                    onChange={(value) => {
                        setMarkdown(value ?? "");
                    }}
                    height={480}
                    preview="live"
                />
            </Box>

            <Button
                variant="contained"
                disabled={isSaving}
                onClick={() => {
                    void saveMarkdown(markdown);
                }}
            >
                {isSaving ? "Saving..." : "Save & Regenerate"}
            </Button>
        </Box>
    );
}
