import { Head, Link as InertiaLink, router } from "@inertiajs/react";
import DeleteOutlineIcon from "@mui/icons-material/DeleteOutline";
import EditIcon from "@mui/icons-material/Edit";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Chip from "@mui/material/Chip";
import Dialog from "@mui/material/Dialog";
import DialogActions from "@mui/material/DialogActions";
import DialogContent from "@mui/material/DialogContent";
import DialogTitle from "@mui/material/DialogTitle";
import IconButton from "@mui/material/IconButton";
import MenuItem from "@mui/material/MenuItem";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";
import { useState } from "react";

import type { ColumnDef } from "@/admin/components/DataTable";
import type { Memory, PaginatedResponse } from "@/types";

import ConfirmDialog from "@/admin/components/ConfirmDialog";
import DataTable from "@/admin/components/DataTable";
import PageHeader from "@/admin/components/PageHeader";
import AdminLayout from "@/admin/layouts/AdminLayout";
import useConfirmDialog from "@/hooks/useConfirmDialog";

interface Filters {
    feature?: string;
    category?: string;
    status?: string;
}

interface IndexProps {
    memories: PaginatedResponse<Memory>;
    features: string[];
    filters: Filters;
}

const columns: ColumnDef<Memory>[] = [
    {
        key: "key",
        label: "Key",
        render: (row) => (
            <Typography variant="body2" fontFamily="monospace" fontWeight={600}>
                {row.key}
            </Typography>
        ),
    },
    { key: "feature", label: "Feature" },
    { key: "category", label: "Category" },
    { key: "confidence", label: "Confidence" },
    {
        key: "is_active",
        label: "Status",
        render: (row) => (
            <Chip
                label={row.is_active ? "Active" : "Inactive"}
                size="small"
                color={row.is_active ? "success" : "default"}
                variant="outlined"
            />
        ),
    },
];

export default function Index({ memories, features, filters }: IndexProps) {
    const { dialogProps, confirm } = useConfirmDialog();
    const [rebuildDialogOpen, setRebuildDialogOpen] = useState(false);
    const [selectedFeature, setSelectedFeature] = useState<string>("");

    const handleFilter = (key: string, value: string) => {
        const merged: { [key: string]: string } = { ...filters, [key]: value };
        const params = Object.fromEntries(
            Object.entries(merged).filter(([, v]) => Boolean(v)),
        );
        router.get("/admin/ai/memories", params, { preserveState: true });
    };

    const handleDelete = (id: number) => {
        confirm("Delete this memory entry?", () => {
            router.delete(`/admin/ai/memories/${id}`);
        });
    };

    const openRebuildDialog = () => {
        setSelectedFeature("");
        setRebuildDialogOpen(true);
    };

    const closeRebuildDialog = () => {
        setRebuildDialogOpen(false);
        setSelectedFeature("");
    };

    const handleRebuildWithSelection = () => {
        if (selectedFeature) {
            closeRebuildDialog();
            confirm(
                `Rebuild all memories for "${selectedFeature}"? Existing memories will be deactivated and regenerated.`,
                () => {
                    router.post(
                        `/admin/ai/memories/rebuild/${selectedFeature}`,
                    );
                },
                { confirmLabel: "Rebuild", confirmColor: "warning" },
            );
        }
    };

    return (
        <AdminLayout>
            <Head title="Memories | AI Tools" />
            <PageHeader
                title="AI Memories"
                backHref="/admin/ai"
                backLabel="Back to AI Tools"
            />

            {/* Filters */}
            <Box
                sx={{
                    display: "flex",
                    gap: 2,
                    mb: 2,
                    flexWrap: "wrap",
                    alignItems: "center",
                }}
            >
                <TextField
                    label="Feature"
                    select
                    size="small"
                    value={filters.feature ?? ""}
                    onChange={(e) => {
                        handleFilter("feature", e.target.value);
                    }}
                    sx={{ minWidth: 160 }}
                >
                    <MenuItem value="">All Features</MenuItem>
                    {features.map((f) => (
                        <MenuItem key={f} value={f}>
                            {f}
                        </MenuItem>
                    ))}
                </TextField>

                <TextField
                    label="Category"
                    select
                    size="small"
                    value={filters.category ?? ""}
                    onChange={(e) => {
                        handleFilter("category", e.target.value);
                    }}
                    sx={{ minWidth: 160 }}
                >
                    <MenuItem value="">All Categories</MenuItem>
                    <MenuItem value="user_preferences">
                        User Preferences
                    </MenuItem>
                    <MenuItem value="domain_knowledge">
                        Domain Knowledge
                    </MenuItem>
                    <MenuItem value="system_tuning">System Tuning</MenuItem>
                </TextField>

                <TextField
                    label="Status"
                    select
                    size="small"
                    value={filters.status ?? ""}
                    onChange={(e) => {
                        handleFilter("status", e.target.value);
                    }}
                    sx={{ minWidth: 120 }}
                >
                    <MenuItem value="">All</MenuItem>
                    <MenuItem value="active">Active</MenuItem>
                    <MenuItem value="inactive">Inactive</MenuItem>
                </TextField>

                <Box sx={{ flexGrow: 1 }} />

                <Button
                    size="small"
                    variant="outlined"
                    onClick={openRebuildDialog}
                >
                    Rebuild
                </Button>

                <Button
                    component={InertiaLink}
                    href="/admin/ai/memories/new"
                    variant="contained"
                >
                    Add Memory
                </Button>
            </Box>

            <DataTable
                columns={columns}
                data={memories.data}
                emptyMessage="No memory entries found."
                pagination={memories}
                rowSx={(memory) => ({ opacity: memory.is_active ? 1 : 0.5 })}
                rowActions={(memory) => (
                    <Box
                        sx={{
                            display: "flex",
                            justifyContent: "flex-end",
                            gap: 1,
                        }}
                    >
                        <IconButton
                            component={InertiaLink}
                            href={`/admin/ai/memories/${memory.id}`}
                            size="small"
                            color="primary"
                            title="Edit"
                            aria-label="Edit"
                        >
                            <EditIcon fontSize="small" />
                        </IconButton>
                        <IconButton
                            size="small"
                            color="error"
                            onClick={() => {
                                handleDelete(memory.id);
                            }}
                            title="Delete"
                            aria-label="Delete"
                        >
                            <DeleteOutlineIcon fontSize="small" />
                        </IconButton>
                    </Box>
                )}
            />

            <ConfirmDialog {...dialogProps} />

            <Dialog
                open={rebuildDialogOpen}
                onClose={closeRebuildDialog}
                maxWidth="sm"
                fullWidth
            >
                <DialogTitle>Select Feature to Rebuild</DialogTitle>
                <DialogContent>
                    <Typography
                        variant="body2"
                        color="text.secondary"
                        sx={{ mb: 2 }}
                    >
                        Choose which feature&apos;s memories you want to
                        rebuild. Existing memories for the selected feature will
                        be deactivated and regenerated.
                    </Typography>
                    <TextField
                        select
                        fullWidth
                        label="Feature"
                        size="small"
                        value={selectedFeature}
                        onChange={(e) => {
                            setSelectedFeature(e.target.value);
                        }}
                        sx={{ mt: 1 }}
                    >
                        <MenuItem value="">
                            <em>Select a feature...</em>
                        </MenuItem>
                        {features.map((f) => (
                            <MenuItem key={f} value={f}>
                                {f}
                            </MenuItem>
                        ))}
                    </TextField>
                </DialogContent>
                <DialogActions>
                    <Button onClick={closeRebuildDialog}>Cancel</Button>
                    <Button
                        onClick={handleRebuildWithSelection}
                        disabled={!selectedFeature}
                        variant="contained"
                        color="warning"
                    >
                        Rebuild
                    </Button>
                </DialogActions>
            </Dialog>
        </AdminLayout>
    );
}
