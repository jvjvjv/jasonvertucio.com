import { useForm } from "@inertiajs/react";
import Alert from "@mui/material/Alert";
import Button from "@mui/material/Button";
import Dialog from "@mui/material/Dialog";
import DialogActions from "@mui/material/DialogActions";
import DialogContent from "@mui/material/DialogContent";
import DialogTitle from "@mui/material/DialogTitle";
import TextField from "@mui/material/TextField";
import { useEffect } from "react";

import type { Role } from "./types";
import type { SyntheticEvent } from "react";

interface RoleFormDialogProps {
    open: boolean;
    /** The role being edited, or null when creating a new one. */
    role: Role | null;
    onClose: () => void;
}

export default function RoleFormDialog({
    open,
    role,
    onClose,
}: RoleFormDialogProps) {
    const isEdit = role !== null;

    const form = useForm({
        name: "",
        title: "",
        description: "",
    });

    // Re-seed the fields whenever the dialog is opened for a different role.
    useEffect(() => {
        if (!open) {
            return;
        }

        form.setData({
            name: role?.name ?? "",
            title: role?.title ?? "",
            description: role?.description ?? "",
        });
        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, role?.id]);

    const handleSubmit = (e: SyntheticEvent<HTMLFormElement>) => {
        e.preventDefault();

        const onSuccess = () => {
            onClose();
        };

        if (isEdit) {
            form.put(`/admin/roles/${String(role.id)}`, { onSuccess });
        } else {
            form.post("/admin/roles", { onSuccess });
        }
    };

    return (
        <Dialog open={open} onClose={onClose} fullWidth maxWidth="sm">
            <form onSubmit={handleSubmit}>
                <DialogTitle>{isEdit ? "Edit Role" : "New Role"}</DialogTitle>
                <DialogContent>
                    {isEdit && (
                        <Alert severity="info" sx={{ mb: 2 }}>
                            A role&rsquo;s name is referenced by code and cannot
                            be changed after it is created.
                        </Alert>
                    )}

                    <TextField
                        label="Name"
                        required
                        size="small"
                        fullWidth
                        autoFocus={!isEdit}
                        value={isEdit ? role.name : form.data.name}
                        onChange={(e) => {
                            form.setData("name", e.target.value);
                        }}
                        error={!!form.errors.name}
                        helperText={
                            form.errors.name ??
                            "Letters, numbers, spaces, hyphens, and underscores."
                        }
                        placeholder="content-editor"
                        disabled={isEdit}
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
                        placeholder="Content Editor"
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
                        placeholder="What this role is for."
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
                        {isEdit ? "Save Changes" : "Create Role"}
                    </Button>
                </DialogActions>
            </form>
        </Dialog>
    );
}
