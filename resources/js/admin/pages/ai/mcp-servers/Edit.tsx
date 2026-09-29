import { Head, Link as InertiaLink, router, useForm } from "@inertiajs/react";
import SyncIcon from "@mui/icons-material/Sync";
import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import Chip from "@mui/material/Chip";
import Typography from "@mui/material/Typography";
import { useState } from "react";

import Form, { toRequestPayload } from "./Form";

import type { FormData } from "./Form";
import type { ColumnDef } from "@/admin/components/DataTable";
import type { McpServer, McpServerAuthType, McpServerTool } from "@/types";
import type { SyntheticEvent } from "react";

import ConfirmDialog from "@/admin/components/ConfirmDialog";
import DataTable from "@/admin/components/DataTable";
import { emptyAuthDraft } from "@/admin/components/McpServerAuthEditor";
import PageHeader from "@/admin/components/PageHeader";
import AdminLayout from "@/admin/layouts/AdminLayout";
import useConfirmDialog from "@/hooks/useConfirmDialog";

interface EditProps {
    server: McpServer;
    authTypes: McpServerAuthType[];
}

type ToolRow = McpServerTool & { id: string };

const toolColumns: ColumnDef<ToolRow>[] = [
    {
        key: "exposed_name",
        label: "Tool",
        render: (row) => (
            <Box>
                <Typography
                    variant="body2"
                    sx={{ fontFamily: "monospace", fontWeight: 500 }}
                >
                    {row.exposed_name ?? "—"}
                </Typography>
                <Typography variant="caption" color="text.secondary">
                    remote: {row.remote_name}
                </Typography>
            </Box>
        ),
    },
    {
        key: "description",
        label: "Description",
        render: (row) => (
            <Typography variant="body2" color="text.secondary">
                {row.description ?? ""}
            </Typography>
        ),
    },
    {
        key: "available",
        label: "Availability",
        render: (row) =>
            row.available ? (
                <Chip
                    label="Available"
                    size="small"
                    color="success"
                    variant="outlined"
                />
            ) : (
                <Box>
                    <Chip
                        label="Unavailable"
                        size="small"
                        color="warning"
                        variant="outlined"
                    />
                    {row.unavailable_reason ? (
                        <Typography
                            variant="caption"
                            color="text.secondary"
                            component="div"
                            sx={{ mt: 0.5 }}
                        >
                            {row.unavailable_reason}
                        </Typography>
                    ) : null}
                </Box>
            ),
    },
];

export default function Edit({ server, authTypes }: EditProps) {
    // Stored credentials never reach the browser, so auth is sent only once
    // the administrator has touched it — otherwise the stored auth is kept.
    const [authDirty, setAuthDirty] = useState(false);
    const [syncing, setSyncing] = useState(false);

    const form = useForm<FormData>({
        slug: server.slug,
        name: server.name,
        url: server.url,
        timeout_seconds: server.timeout_seconds?.toString() ?? "",
        enabled: server.enabled,
        auth: emptyAuthDraft(server.auth_type),
    });

    const { dialogProps, confirm } = useConfirmDialog();

    const handleSubmit = (e: SyntheticEvent<HTMLFormElement>) => {
        e.preventDefault();
        form.transform((data) => toRequestPayload(data, authDirty));
        form.put(`/admin/ai/mcp-servers/${server.id}`, {
            onSuccess: () => {
                setAuthDirty(false);
                form.setData("auth", emptyAuthDraft(form.data.auth.type));
            },
        });
    };

    const handleSync = () => {
        router.post(
            `/admin/ai/mcp-servers/${server.id}/sync`,
            {},
            {
                preserveScroll: true,
                onStart: () => {
                    setSyncing(true);
                },
                onFinish: () => {
                    setSyncing(false);
                },
            },
        );
    };

    const handleDelete = () => {
        confirm(
            `Delete "${server.name}"? Its tools will be withdrawn from every AI system that granted them.`,
            () => {
                router.delete(`/admin/ai/mcp-servers/${server.id}`);
            },
        );
    };

    const toolRows: ToolRow[] = server.tools.map((tool) => ({
        ...tool,
        id: tool.remote_name,
    }));

    return (
        <AdminLayout>
            <Head title={`${server.name} | MCP Servers`} />
            <PageHeader
                title={`Edit: ${server.name}`}
                backHref="/admin/ai/mcp-servers"
                backLabel="Back to MCP Servers"
            >
                <Button
                    onClick={handleSync}
                    variant="outlined"
                    size="small"
                    startIcon={<SyncIcon />}
                    disabled={syncing}
                >
                    {syncing ? "Syncing…" : "Sync now"}
                </Button>
            </PageHeader>

            {server.last_sync_error !== null ? (
                <Alert severity="error" sx={{ mb: 2 }}>
                    Last sync failed
                    {server.last_synced_at
                        ? ` (${new Date(server.last_synced_at).toLocaleString()})`
                        : ""}
                    : {server.last_sync_error}
                </Alert>
            ) : server.last_synced_at !== null ? (
                <Alert severity="success" sx={{ mb: 2 }}>
                    Last synced{" "}
                    {new Date(server.last_synced_at).toLocaleString()} —{" "}
                    {server.tool_count} tool
                    {server.tool_count !== 1 ? "s" : ""}.
                </Alert>
            ) : (
                <Alert severity="info" sx={{ mb: 2 }}>
                    This server has not been synced yet.
                </Alert>
            )}

            <Card>
                <CardContent>
                    <Box component="form" onSubmit={handleSubmit}>
                        <Form
                            data={form.data}
                            setData={form.setData}
                            errors={form.errors}
                            authTypes={authTypes}
                            isEdit
                            storedAuthType={server.auth_type}
                            onAuthChange={() => {
                                setAuthDirty(true);
                            }}
                        />

                        <Box
                            sx={{
                                display: "flex",
                                justifyContent: "space-between",
                                alignItems: "center",
                                mt: 3,
                            }}
                        >
                            <Button color="error" onClick={handleDelete}>
                                Delete
                            </Button>

                            <Box sx={{ display: "flex", gap: 2 }}>
                                <Button
                                    component={InertiaLink}
                                    href="/admin/ai/mcp-servers"
                                    color="inherit"
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    variant="contained"
                                    disabled={form.processing}
                                >
                                    {form.processing
                                        ? "Saving and syncing…"
                                        : "Update Server"}
                                </Button>
                            </Box>
                        </Box>
                    </Box>
                </CardContent>
            </Card>

            <Typography variant="h6" sx={{ mt: 3, mb: 1 }}>
                Tool catalog
            </Typography>
            <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
                Grant these to an AI system from its edit page. A tool added to
                the server later is not granted until you check it there.
            </Typography>
            <DataTable
                columns={toolColumns}
                data={toolRows}
                emptyMessage="No tools synced from this server."
            />

            <ConfirmDialog {...dialogProps} />
        </AdminLayout>
    );
}
