import { useForm } from "@inertiajs/react";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Checkbox from "@mui/material/Checkbox";
import Dialog from "@mui/material/Dialog";
import DialogActions from "@mui/material/DialogActions";
import DialogContent from "@mui/material/DialogContent";
import DialogTitle from "@mui/material/DialogTitle";
import FormControlLabel from "@mui/material/FormControlLabel";
import Typography from "@mui/material/Typography";
import { useEffect } from "react";

import type { Permission, Role } from "./types";
import type { SyntheticEvent } from "react";

interface RolePermissionsDialogProps {
    open: boolean;
    role: Role | null;
    permissions: Permission[];
    onClose: () => void;
}

export default function RolePermissionsDialog({
    open,
    role,
    permissions,
    onClose,
}: RolePermissionsDialogProps) {
    const form = useForm<{ permissions: string[] }>({ permissions: [] });

    // Re-seed the checklist whenever the dialog is opened for a different role.
    useEffect(() => {
        if (!open || role === null) {
            return;
        }

        form.setData("permissions", role.permissions);
        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, role?.id]);

    if (role === null) {
        return null;
    }

    const toggle = (name: string) => {
        form.setData(
            "permissions",
            form.data.permissions.includes(name)
                ? form.data.permissions.filter((p) => p !== name)
                : [...form.data.permissions, name],
        );
    };

    const handleSubmit = (e: SyntheticEvent<HTMLFormElement>) => {
        e.preventDefault();
        form.put(`/admin/roles/${String(role.id)}/permissions`, {
            onSuccess: () => {
                onClose();
            },
        });
    };

    const selectedCount = form.data.permissions.length;

    return (
        <Dialog open={open} onClose={onClose} fullWidth maxWidth="sm">
            <form onSubmit={handleSubmit}>
                <DialogTitle>
                    Permissions for &ldquo;{role.title ?? role.name}&rdquo;
                </DialogTitle>
                <DialogContent>
                    <Box
                        sx={{
                            display: "flex",
                            alignItems: "center",
                            justifyContent: "space-between",
                            mb: 1,
                        }}
                    >
                        <Typography variant="body2" color="text.secondary">
                            {selectedCount} of {permissions.length} selected
                        </Typography>
                        <Box sx={{ display: "flex", gap: 1 }}>
                            <Button
                                size="small"
                                onClick={() => {
                                    form.setData(
                                        "permissions",
                                        permissions.map((p) => p.name),
                                    );
                                }}
                            >
                                Select all
                            </Button>
                            <Button
                                size="small"
                                color="inherit"
                                onClick={() => {
                                    form.setData("permissions", []);
                                }}
                            >
                                Clear
                            </Button>
                        </Box>
                    </Box>

                    <Box
                        sx={{
                            display: "grid",
                            gridTemplateColumns: { xs: "1fr", sm: "1fr 1fr" },
                        }}
                    >
                        {permissions.map((permission) => (
                            <FormControlLabel
                                key={permission.id}
                                control={
                                    <Checkbox
                                        size="small"
                                        checked={form.data.permissions.includes(
                                            permission.name,
                                        )}
                                        onChange={() => {
                                            toggle(permission.name);
                                        }}
                                    />
                                }
                                label={
                                    <Typography
                                        variant="body2"
                                        sx={{ fontFamily: "monospace" }}
                                    >
                                        {permission.name}
                                    </Typography>
                                }
                            />
                        ))}
                    </Box>

                    {permissions.length === 0 && (
                        <Typography variant="body2" color="text.secondary">
                            No permissions exist yet.
                        </Typography>
                    )}
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
                        Save Permissions
                    </Button>
                </DialogActions>
            </form>
        </Dialog>
    );
}
