import { Head, Link as InertiaLink, router } from "@inertiajs/react";
import ChairIcon from "@mui/icons-material/Chair";
import DataObjectIcon from "@mui/icons-material/DataObject";
import DeleteOutlineIcon from "@mui/icons-material/DeleteOutline";
import EditIcon from "@mui/icons-material/Edit";
import FileCopyIcon from "@mui/icons-material/FileCopy";
import HandymanIcon from "@mui/icons-material/Handyman";
import HistoryIcon from "@mui/icons-material/History";
import PsychologyAltIcon from "@mui/icons-material/PsychologyAlt";
import VisibilityIcon from "@mui/icons-material/Visibility";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Chip from "@mui/material/Chip";
import IconButton from "@mui/material/IconButton";
import Link from "@mui/material/Link";
import Tooltip from "@mui/material/Tooltip";
import Typography from "@mui/material/Typography";

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

const columns: ColumnDef<AiSystem>[] = [
    {
        key: "name",
        label: "Name",
        render: (row) => (
            <Link
                component={InertiaLink}
                href={`/admin/ai/systems/${row.id}`}
                underline="hover"
                color="primary"
                sx={{ fontWeight: 600 }}
            >
                {row.name}
            </Link>
        ),
    },
    { key: "provider", label: "Provider" },
    {
        key: "model",
        label: "Model",
        render: (row) => (
            <Typography variant="body2" fontFamily="monospace">
                {row.model}
            </Typography>
        ),
    },
    {
        key: "feature_defaults_list",
        label: "Default For",
        render: (row) =>
            row.feature_defaults_list.length > 0
                ? row.feature_defaults_list.map((f) => (
                      <Chip key={f} label={f} size="small" sx={{ mr: 0.5 }} />
                  ))
                : "-",
    },
    {
        key: "model_capabilities",
        label: "Features",
        render: (row) => (
            <Box sx={{ display: "flex", gap: 1, flexWrap: "wrap" }}>
                {row.model_capabilities?.reasoning && (
                    <Tooltip title="Model supports reasoning">
                        <PsychologyAltIcon fontSize="small" color="primary" />
                    </Tooltip>
                )}
                {row.model_capabilities?.vision && (
                    <Tooltip title="Model supports vision">
                        <VisibilityIcon fontSize="small" color="primary" />
                    </Tooltip>
                )}
                {row.model_capabilities?.tools && (
                    <Tooltip title="Model is trained for tool use">
                        <HandymanIcon fontSize="small" color="primary" />
                    </Tooltip>
                )}
                {row.supports_tools && (
                    <Tooltip title="System can expose MCP tools">
                        <HandymanIcon fontSize="small" />
                    </Tooltip>
                )}
                {row.supports_json_mode && (
                    <Tooltip title="System supports JSON mode">
                        <DataObjectIcon fontSize="small" />
                    </Tooltip>
                )}
                {row.is_local_endpoint && (
                    <Tooltip title="Local endpoint">
                        <ChairIcon fontSize="small" color="primary" />
                    </Tooltip>
                )}
            </Box>
        ),
    },
    {
        key: "interaction_logs_count",
        label: "API Calls",
        render: (row) =>
            row.interaction_logs_count > 0 ? (
                <Link
                    component={InertiaLink}
                    href={`/admin/ai/systems/${row.id}/logs`}
                    underline="hover"
                >
                    {row.interaction_logs_count}
                </Link>
            ) : (
                "-"
            ),
    },
];

export default function Index({ systems }: IndexProps) {
    const { dialogProps, confirm } = useConfirmDialog();

    const handleDelete = (system: AiSystem) => {
        const botCount = system.chat_bots_count;
        const message =
            botCount > 0
                ? `"${system.name}" is used by ${botCount} agent(s). Deleting it will deactivate those agents. The system data will be preserved. Continue?`
                : `Delete AI system "${system.name}"? This cannot be undone.`;

        confirm(
            message,
            () => {
                router.delete(`/admin/ai/systems/${system.id}`);
            },
            { title: botCount > 0 ? "System In Use" : "Confirm Delete" },
        );
    };

    const handleDuplicate = (id: number) => {
        confirm(
            "Duplicate this AI system?",
            () => {
                router.post(`/admin/ai/systems/${id}/duplicate`);
            },
            { confirmLabel: "Duplicate", confirmColor: "primary" },
        );
    };

    return (
        <AdminLayout>
            <Head title="AI Systems | AI Tools" />
            <PageHeader
                title="AI Systems"
                backHref="/admin/ai"
                backLabel="Back to AI Tools"
            />

            <Box
                sx={{
                    display: "flex",
                    justifyContent: "space-between",
                    alignItems: "center",
                    mb: 2,
                }}
            >
                <Box sx={{ display: "flex", gap: 1 }}>
                    <Button
                        component={InertiaLink}
                        href="/admin/ai/chat-bots"
                        variant="outlined"
                    >
                        Agents
                    </Button>
                    <Button
                        component={InertiaLink}
                        href="/admin/ai/conversations"
                        variant="outlined"
                    >
                        AI Conversations
                    </Button>
                    <Button
                        component={InertiaLink}
                        href="/admin/ai/memories"
                        variant="outlined"
                    >
                        AI Memories
                    </Button>
                    <Button
                        component={InertiaLink}
                        href="/admin/ai/system-prompts"
                        variant="outlined"
                    >
                        System Prompts
                    </Button>
                </Box>
                <Button
                    component={InertiaLink}
                    href="/admin/ai/systems/new"
                    variant="contained"
                >
                    Add System
                </Button>
            </Box>

            <DataTable
                columns={columns}
                data={systems}
                rowSx={(system) => ({ opacity: system.is_active ? 1 : 0.5 })}
                emptyState={
                    <Box sx={{ textAlign: "center", py: 4 }}>
                        <Typography color="text.secondary">
                            No AI systems configured yet.
                        </Typography>
                        <Typography variant="body2" sx={{ mt: 0.5 }}>
                            <Link
                                component={InertiaLink}
                                href="/admin/ai/systems/new"
                                underline="hover"
                            >
                                Add your first one
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
                            href={`/admin/ai/systems/${system.id}`}
                            size="small"
                            color="primary"
                            title="Edit"
                            aria-label="Edit"
                        >
                            <EditIcon fontSize="small" />
                        </IconButton>
                        <IconButton
                            component={InertiaLink}
                            href={`/admin/ai/systems/${system.id}/logs`}
                            size="small"
                            color="primary"
                            title="View Logs"
                            aria-label="View Logs"
                        >
                            <HistoryIcon fontSize="small" />
                        </IconButton>
                        <IconButton
                            size="small"
                            color="primary"
                            onClick={() => {
                                handleDuplicate(system.id);
                            }}
                            title="Duplicate"
                            aria-label="Duplicate"
                        >
                            <FileCopyIcon fontSize="small" />
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
