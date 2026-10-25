import { Head, Link as InertiaLink, router } from "@inertiajs/react";
import DeleteOutlineIcon from "@mui/icons-material/DeleteOutline";
import EditIcon from "@mui/icons-material/Edit";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Chip from "@mui/material/Chip";
import IconButton from "@mui/material/IconButton";
import Link from "@mui/material/Link";
import Typography from "@mui/material/Typography";

import { VOICE_ROLES } from "./Form";

import type { VoiceRole } from "./Form";
import type { ColumnDef } from "@/admin/components/DataTable";
import type { AiSystem } from "@/types";

import ConfirmDialog from "@/admin/components/ConfirmDialog";
import DataTable from "@/admin/components/DataTable";
import PageHeader from "@/admin/components/PageHeader";
import AdminLayout from "@/admin/layouts/AdminLayout";
import useConfirmDialog from "@/hooks/useConfirmDialog";

interface IndexProps {
    systems: AiSystem[];
}

function roleOf(system: AiSystem) {
    return VOICE_ROLES[system.role as VoiceRole];
}

const columns: ColumnDef<AiSystem>[] = [
    {
        key: "name",
        label: "Name",
        render: (row) => (
            <Link
                component={InertiaLink}
                href={`/admin/ai/voice-systems/${row.id}`}
                underline="hover"
                color="primary"
                sx={{ fontWeight: 600 }}
            >
                {row.name}
            </Link>
        ),
    },
    {
        key: "role",
        label: "Used for",
        render: (row) => (
            <Box sx={{ display: "flex", alignItems: "center", gap: 1 }}>
                {roleOf(row).title}
                {row.feature_defaults_list.includes(roleOf(row).feature) ? (
                    <Chip
                        size="small"
                        color="primary"
                        variant="outlined"
                        label="In use"
                    />
                ) : null}
            </Box>
        ),
    },
    { key: "provider", label: "Provider" },
    {
        key: "model",
        label: "Model",
        render: (row) => (
            <Typography variant="body2" fontFamily="monospace">
                {row.model || "Provider default"}
            </Typography>
        ),
    },
    {
        key: "base_url",
        label: "Server",
        render: (row) => (
            <Typography variant="body2" fontFamily="monospace">
                {row.base_url ?? "Provider's service"}
            </Typography>
        ),
    },
];

export default function Index({ systems }: IndexProps) {
    const { dialogProps, confirm } = useConfirmDialog();

    const handleDelete = (system: AiSystem) => {
        confirm(
            `Delete voice system "${system.name}"? This cannot be undone.`,
            () => {
                router.delete(`/admin/ai/systems/${system.id}`);
            },
            { title: "Confirm Delete" },
        );
    };

    return (
        <AdminLayout>
            <Head title="Voice Systems | AI Tools" />
            <PageHeader
                title="Voice Systems"
                backHref="/admin/ai"
                backLabel="Back to AI Tools"
            />

            <Box
                sx={{
                    display: "flex",
                    justifyContent: "space-between",
                    alignItems: "center",
                    flexWrap: "wrap",
                    gap: 2,
                    mb: 2,
                }}
            >
                <Typography
                    variant="body2"
                    color="text.secondary"
                    sx={{ maxWidth: "70ch" }}
                >
                    The speech servers behind dictation in chat and reading
                    replies and documents aloud. Mark one system in use for
                    each. Who can use voice is set by the use-voice permission.
                </Typography>
                <Button
                    component={InertiaLink}
                    href="/admin/ai/voice-systems/new"
                    variant="contained"
                >
                    Add Voice System
                </Button>
            </Box>

            <DataTable
                columns={columns}
                data={systems}
                rowSx={(system) => ({ opacity: system.is_active ? 1 : 0.5 })}
                emptyState={
                    <Box sx={{ textAlign: "center", py: 4 }}>
                        <Typography color="text.secondary">
                            No voice systems yet, so dictation and read-aloud
                            are off.
                        </Typography>
                        <Typography variant="body2" sx={{ mt: 0.5 }}>
                            <Link
                                component={InertiaLink}
                                href="/admin/ai/voice-systems/new"
                                underline="hover"
                            >
                                Add one for each
                            </Link>
                        </Typography>
                    </Box>
                }
                rowActions={(system) => (
                    <Box
                        sx={{
                            display: "flex",
                            justifyContent: "flex-end",
                            gap: 1,
                        }}
                    >
                        <IconButton
                            component={InertiaLink}
                            href={`/admin/ai/voice-systems/${system.id}`}
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
                                handleDelete(system);
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
        </AdminLayout>
    );
}
