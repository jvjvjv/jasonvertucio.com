import { useForm } from "@inertiajs/react";
import Alert from "@mui/material/Alert";
import Button from "@mui/material/Button";
import Dialog from "@mui/material/Dialog";
import DialogActions from "@mui/material/DialogActions";
import DialogContent from "@mui/material/DialogContent";
import DialogTitle from "@mui/material/DialogTitle";
import TextField from "@mui/material/TextField";
import { useEffect } from "react";

import type { Permission } from "./types";
import type { SyntheticEvent } from "react";

interface PermissionFormDialogProps {
    open: boolean;
    /** The permission being edited, or null when creating a new one. */
    permission: Permission | null;
    onClose: () => void;
}

export default function PermissionFormDialog({
    open,
    permission,
    onClose,
}: PermissionFormDialogProps) {
    const isEdit = permission !== null;

    const form = useForm({
        name: "",
        title: "",
        description: "",
    });

    // Re-seed the fields whenever the dialog is opened for a different permission.
    useEffect(() => {
        if (!open) {
            return;
        }

        form.setData({
            name: permission?.name ?? "",
            title: permission?.title ?? "",
            description: permission?.description ?? "",
        });
        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, permission?.id]);

    const handleSubmit = (e: SyntheticEvent<HTMLFormElement>) => {
        e.preventDefault();

        const onSuccess = () => {
            onClose();
        };

        if (isEdit) {
            form.put(`/admin/permissions/${String(permission.id)}`, {
                onSuccess,
            });
        } else {
            form.post("/admin/permissions", { onSuccess });
        }
    };

    return (
        <Dialog open={open} onClose={onClose} fullWidth maxWidth="sm">
            <form onSubmit={handleSubmit}>
                <DialogTitle>
                    {isEdit ? "Edit Permission" : "New Permission"}
                </DialogTitle>
                <DialogContent>
                    {isEdit && (
                        <Alert severity="info" sx={{ mb: 2 }}>
                            A permission&rsquo;s name is used directly in{" "}
                            <code>can:</code> checks and cannot be changed after
                            it is created.
                        </Alert>
                    )}

                    <TextField
                        label="Name"
                        required
                        size="small"
                        fullWidth
                        autoFocus={!isEdit}
                        value={isEdit ? permission.name : form.data.name}
                        onChange={(e) => {
                            form.setData("name", e.target.value);
                        }}
                        error={!!form.errors.name}
                        helperText={
                            form.errors.name ??
                            "Lowercase kebab-case, for example edit-resume."
                        }
                        placeholder="edit-resume"
                        disabled={isEdit}
                        slotProps={{
                            input: { sx: { fontFamily: "monospace" } },
                        }}
                        sx={{ mt: 1, mb: 2 }}
                    />

                    <TextField
                        label="Title"
                        size="small"
                        fullWidth
                        value={form.data.title}
                        onChange={(e) => {
                            form.setData("title", e.target.value);
                        }}
                        error={!!form.errors.title}
                        helperText={
                            form.errors.title ?? "Optional display name."
                        }
                        placeholder="Edit Resume"
                        sx={{ mb: 2 }}
                    />

                    <TextField
                        label="Description"
                        size="small"
                        fullWidth
                        multiline
                        rows={3}
                        value={form.data.description}
                        onChange={(e) => {
                            form.setData("description", e.target.value);
                        }}
                        error={!!form.errors.description}
                        helperText={form.errors.description}
                        placeholder="What this permission grants."
                    />
                </DialogContent>
                <DialogActions>
                    <Button onClick={onClose} color="inherit">
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        variant="contained"
                        disabled={form.processing}
                    >
                        {isEdit ? "Save Changes" : "Create Permission"}
                    </Button>
                </DialogActions>
            </form>
        </Dialog>
    );
}
