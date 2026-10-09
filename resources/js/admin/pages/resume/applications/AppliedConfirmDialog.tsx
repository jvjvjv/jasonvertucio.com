import Alert from "@mui/material/Alert";
import Button from "@mui/material/Button";
import Dialog from "@mui/material/Dialog";
import DialogActions from "@mui/material/DialogActions";
import DialogContent from "@mui/material/DialogContent";
import DialogContentText from "@mui/material/DialogContentText";
import DialogTitle from "@mui/material/DialogTitle";
import MenuItem from "@mui/material/MenuItem";
import TextField from "@mui/material/TextField";
import { useState } from "react";

import type { ResumeVersionOption } from "@/types";

interface AppliedConfirmDialogProps {
    open: boolean;
    /**
     * The application's targeted resume is what was sent. The dialog then
     * states so and offers no choice of version.
     */
    hasTargetedResume: boolean;
    resumeVersions: ResumeVersionOption[];
    currentResumeVersionId: number | null;
    isSubmitting?: boolean;
    error?: string | null;
    /** Receives the chosen main resume version, or null when the targeted resume was used. */
    onConfirm: (resumeVersionId: number | null) => void;
    onCancel: () => void;
}

/**
 * Confirms which resume was sent before an application is recorded as
 * applied. Shared by the New Session page ("I Applied") and the Discussion
 * page ("Mark Applied").
 */
export default function AppliedConfirmDialog({
    open,
    hasTargetedResume,
    resumeVersions,
    currentResumeVersionId,
    isSubmitting = false,
    error = null,
    onConfirm,
    onCancel,
}: AppliedConfirmDialogProps) {
    const [resumeVersionId, setResumeVersionId] = useState<number | "">(
        () =>
            currentResumeVersionId ??
            resumeVersions.find((version) => version.is_current)?.id ??
            "",
    );

    const needsVersion = !hasTargetedResume;

    return (
        <Dialog
            open={open}
            onClose={isSubmitting ? undefined : onCancel}
            maxWidth="xs"
            fullWidth
        >
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    onConfirm(
                        needsVersion && resumeVersionId !== ""
                            ? resumeVersionId
                            : null,
                    );
                }}
            >
                <DialogTitle>Mark as applied</DialogTitle>
                <DialogContent>
                    {hasTargetedResume ? (
                        <DialogContentText>
                            The targeted resume built for this application will
                            be recorded as the resume you sent.
                        </DialogContentText>
                    ) : (
                        <>
                            <DialogContentText sx={{ mb: 2 }}>
                                Which version of your main resume did you send?
                            </DialogContentText>
                            <TextField
                                label="Resume version"
                                select
                                required
                                fullWidth
                                size="small"
                                value={resumeVersionId}
                                onChange={(e) => {
                                    setResumeVersionId(Number(e.target.value));
                                }}
                            >
                                {resumeVersions.map((version) => (
                                    <MenuItem
                                        key={version.id}
                                        value={version.id}
                                    >
                                        {version.version}
                                        {version.is_current ? " (current)" : ""}
                                    </MenuItem>
                                ))}
                            </TextField>
                        </>
                    )}
                    {error ? (
                        <Alert severity="error" sx={{ mt: 2 }}>
                            {error}
                        </Alert>
                    ) : null}
                </DialogContent>
                <DialogActions>
                    <Button
                        type="button"
                        onClick={onCancel}
                        disabled={isSubmitting}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        color="success"
                        variant="contained"
                        disabled={
                            isSubmitting ||
                            (needsVersion && resumeVersionId === "")
                        }
                    >
                        {isSubmitting ? "Saving..." : "Applied"}
                    </Button>
                </DialogActions>
            </form>
        </Dialog>
    );
}
