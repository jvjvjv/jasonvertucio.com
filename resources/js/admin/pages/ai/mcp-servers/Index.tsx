import { Head, Link as InertiaLink, router } from "@inertiajs/react";
import DeleteOutlineIcon from "@mui/icons-material/DeleteOutline";
import EditIcon from "@mui/icons-material/Edit";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Chip from "@mui/material/Chip";
import IconButton from "@mui/material/IconButton";
import Tooltip from "@mui/material/Tooltip";
import Typography from "@mui/material/Typography";

import type { ColumnDef } from "@/admin/components/DataTable";
import type { McpServer } from "@/types";

import ConfirmDialog from "@/admin/components/ConfirmDialog";
import DataTable from "@/admin/components/DataTable";
import PageHeader from "@/admin/components/PageHeader";
import AdminLayout from "@/admin/layouts/AdminLayout";
import useConfirmDialog from "@/hooks/useConfirmDialog";

interface IndexProps {
    servers: McpServer[];
}

const AUTH_LABELS: { [key: string]: string } = {
    none: "None",
    bearer: "Bearer",
    headers: "Headers",
    client_credentials: "OAuth",
};

const columns: ColumnDef<McpServer>[] = [
    {
        key: "name",
        label: "Server",
        render: (row) => (
            <Box>
                <Typography variant="body2" fontWeight={500}>
                    {row.name}
                </Typography>
                <Typography
                    variant="caption"
                    color="text.secondary"
                    sx={{ fontFamily: "monospace" }}
                >
                    {row.slug}
                </Typography>
            </Box>
        ),
    },
    {
        key: "url",
        label: "URL",
        render: (row) => (
            <Typography
                variant="body2"
                color="text.secondary"
                sx={{ wordBreak: "break-all" }}
            >
                {row.url}
            </Typography>
        ),
    },
    {
        key: "auth_type",
        label: "Auth",
        render: (row) => (
            <Typography variant="body2">
                {AUTH_LABELS[row.auth_type] ?? row.auth_type}
            </Typography>
        ),
    },
    {
        key: "enabled",
        label: "Status",
        render: (row) => (
            <Chip
                label={row.enabled ? "Enabled" : "Disabled"}
                size="small"
                color={row.enabled ? "success" : "default"}
                variant="outlined"
            />
        ),
    },
    {
        key: "last_synced_at",
        label: "Last sync",
        render: (row) => (
            <Box>
                <Typography variant="body2">
                    {row.last_synced_at
                        ? new Date(row.last_synced_at).toLocaleString()
                        : "Never"}
                </Typography>
                {row.last_sync_error !== null ? (
                    <Typography variant="caption" color="error">
                        {row.last_sync_error}
                    </Typography>
                ) : null}
            </Box>
        ),
    },
    {
        key: "tool_count",
        label: "Tools",
        align: "center",
        render: (row) => (
            <Typography variant="body2">{row.tool_count}</Typography>
        ),
    },
];

export default function Index({ servers }: IndexProps) {
    const { dialogProps, confirm } = useConfirmDialog();

    const handleDelete = (server: McpServer) => {
        confirm(
            `Delete "${server.name}"? Its tools will be withdrawn from every AI system that granted them.`,
            () => {
                router.delete(`/admin/ai/mcp-servers/${server.id}`);
            },
        );
    };

    return (
        <AdminLayout>
            <Head title="MCP Servers" />
            <PageHeader
                title="MCP Servers"
                backHref="/admin/ai"
                backLabel="Back to AI Tools"
            >
                <Button
                    component={InertiaLink}
                    href="/admin/ai/mcp-servers/new"
                    variant="contained"
                    size="small"
                >
                    Add Server
                </Button>
            </PageHeader>

            <DataTable
                columns={columns}
                data={servers}
                emptyMessage="No MCP servers yet."
                rowActions={(server) => (
                    <Box
                        sx={{
                            display: "flex",
                            justifyContent: "flex-end",
                            gap: 0.5,
                        }}
                    >
                        <Tooltip title="Edit">
                            <IconButton
                                size="small"
                                component={InertiaLink}
                                href={`/admin/ai/mcp-servers/${server.id}`}
                            >
                                <EditIcon fontSize="small" />
                            </IconButton>
                        </Tooltip>
                        <Tooltip title="Delete">
                            <IconButton
                                size="small"
                                color="error"
                                onClick={() => {
                                    handleDelete(server);
                                }}
                            >
                                <DeleteOutlineIcon fontSize="small" />
                            </IconButton>
                        </Tooltip>
                    </Box>
                )}
            />

            <ConfirmDialog {...dialogProps} />
        </AdminLayout>
    );
}
