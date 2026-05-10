import { Head, Link as InertiaLink, router } from "@inertiajs/react";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import Chip from "@mui/material/Chip";
import Dialog from "@mui/material/Dialog";
import DialogActions from "@mui/material/DialogActions";
import DialogContent from "@mui/material/DialogContent";
import DialogTitle from "@mui/material/DialogTitle";
import MenuItem from "@mui/material/MenuItem";
import Table from "@mui/material/Table";
import TableBody from "@mui/material/TableBody";
import TableCell from "@mui/material/TableCell";
import TableContainer from "@mui/material/TableContainer";
import TableHead from "@mui/material/TableHead";
import TableRow from "@mui/material/TableRow";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";
import { useState } from "react";

import type { Memory, PaginatedResponse } from "@/types";

import ConfirmDialog from "@/admin/components/ConfirmDialog";
import EmptyTableRow from "@/admin/components/EmptyTableRow";
import PageHeader from "@/admin/components/PageHeader";
import Pagination from "@/admin/components/Pagination";
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

            <Card>
                <TableContainer>
                    <Table size="small">
                        <TableHead>
                            <TableRow>
                                <TableCell>Key</TableCell>
                                <TableCell>Feature</TableCell>
                                <TableCell>Category</TableCell>
                                <TableCell>Confidence</TableCell>
                                <TableCell>Status</TableCell>
                                <TableCell align="right">Actions</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {memories.data.length === 0 ? (
                                <EmptyTableRow
                                    colSpan={6}
                                    message="No memory entries found."
                                />
                            ) : (
                                memories.data.map((memory) => (
                                    <TableRow
                                        key={memory.id}
                                        hover
                                        sx={{
                                            opacity: memory.is_active ? 1 : 0.5,
                                        }}
                                    >
                                        <TableCell>
                                            <Typography
                                                variant="body2"
                                                fontFamily="monospace"
                                                fontWeight={600}
                                            >
                                                {memory.key}
                                            </Typography>
                                        </TableCell>
                                        <TableCell>{memory.feature}</TableCell>
                                        <TableCell>{memory.category}</TableCell>
                                        <TableCell>
                                            {memory.confidence}
                                        </TableCell>
                                        <TableCell>
                                            <Chip
                                                label={
                                                    memory.is_active
                                                        ? "Active"
                                                        : "Inactive"
                                                }
                                                size="small"
                                                color={
                                                    memory.is_active
                                                        ? "success"
                                                        : "default"
                                                }
                                                variant="outlined"
                                            />
                                        </TableCell>
                                        <TableCell align="right">
                                            <Box
                                                sx={{
                                                    display: "flex",
                                                    justifyContent: "flex-end",
                                                    gap: 1,
                                                }}
                                            >
                                                <Button
                                                    component={InertiaLink}
                                                    href={`/admin/ai/memories/${memory.id}`}
                                                    size="small"
                                                >
                                                    Edit
                                                </Button>
                                                <Button
                                                    size="small"
                                                    color="error"
                                                    onClick={() => {
                                                        handleDelete(memory.id);
                                                    }}
                                                >
                                                    Delete
                                                </Button>
                                            </Box>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </TableContainer>

                <Pagination
                    links={memories.links}
                    lastPage={memories.last_page}
                />
            </Card>
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
                        Choose which feature's memories you want to rebuild.
                        Existing memories for the selected feature will be
                        deactivated and regenerated.
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
