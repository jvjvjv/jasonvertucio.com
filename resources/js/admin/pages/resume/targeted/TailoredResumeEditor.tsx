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
    targetedResume: Pick<TargetedResume, "id" | "tailored_content">;
}

export default function TailoredResumeEditor({
    targetedResume,
}: TailoredResumeEditorProps) {
    const [markdown, setMarkdown] = useState(
        targetedResume.tailored_content ?? "",
    );
    /** The text as last saved, so the confirmation clears on the next edit. */
    const [savedMarkdown, setSavedMarkdown] = useState(markdown);

    const { isSaving, saveError, saveSuccess, saveMarkdown } =
        useUpdateTailoredMarkdown({
            targetedResumeId: targetedResume.id,
        });

    const handleSave = async () => {
        const submitted = markdown;
        if (await saveMarkdown(submitted)) {
            setSavedMarkdown(submitted);
        }
    };

    const showSaved = saveSuccess && markdown === savedMarkdown;

    return (
        <Box sx={{ p: { xs: 1.5, md: 3 } }}>
            <Typography variant="body2" sx={{ mb: 2, color: "text.secondary" }}>
                Edit the finalized resume markdown directly. Saving regenerates
                the DOCX and PDF, and lets the AI persona know the resume was
                changed outside of chat.
            </Typography>

            {saveError ? (
                <Alert severity="error" sx={{ mb: 2 }}>
                    {saveError}
                </Alert>
            ) : null}

            {showSaved ? (
                <Alert severity="success" sx={{ mb: 2 }}>
                    Saved. The DOCX and PDF downloads now reflect this version.
                </Alert>
            ) : null}

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
                    void handleSave();
                }}
            >
                {isSaving ? "Saving..." : "Save & Regenerate"}
            </Button>
        </Box>
    );
}
