import { Head, Link as InertiaLink, router } from "@inertiajs/react";
import ChatIcon from "@mui/icons-material/Chat";
import DeleteOutlineIcon from "@mui/icons-material/DeleteOutline";
import OpenInNewIcon from "@mui/icons-material/OpenInNew";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import IconButton from "@mui/material/IconButton";
import Link from "@mui/material/Link";
import MenuItem from "@mui/material/MenuItem";
import TextField from "@mui/material/TextField";

import type { ColumnDef } from "@/admin/components/DataTable";
import type { Conversation, PaginatedResponse } from "@/types";

import ConfirmDialog from "@/admin/components/ConfirmDialog";
import DataTable from "@/admin/components/DataTable";
import PageHeader from "@/admin/components/PageHeader";
import StatusChip from "@/admin/components/StatusChip";
import UsageChip from "@/admin/components/UsageChip";
import AdminLayout from "@/admin/layouts/AdminLayout";
import useConfirmDialog from "@/hooks/useConfirmDialog";

interface Filters {
    feature?: string;
    status?: string;
    ai_system_id?: string;
    ai_chat_bot_id?: string;
    search?: string;
}

interface IndexProps {
    conversations: PaginatedResponse<Conversation>;
    features: string[];
    systems: { id: number; name: string }[];
    bots: { id: number; name: string }[];
    filters: Filters;
}

const columns: ColumnDef<Conversation>[] = [
    {
        key: "title",
        label: "Conversation",
        render: (row) => (
            <Link
                component={InertiaLink}
                href={`/admin/ai/conversations/${row.id}`}
                style={{ fontWeight: 500 }}
                underline="hover"
            >
                {row.title ?? `Conversation #${row.id}`}
            </Link>
        ),
    },
    { key: "feature", label: "Feature" },
    {
        key: "participant",
        label: "Participant",
        render: (row) => row.user_name ?? row.visitor_name ?? "Unknown",
    },
    {
        key: "system_bot",
        label: "System / Bot",
        render: (row) => (
            <Box>
                <Box>{row.ai_system_name ?? "-"}</Box>
                <Box sx={{ color: "text.secondary", fontSize: 12 }}>
                    {row.ai_chat_bot_name ?? "No bot"}
                </Box>
            </Box>
        ),
    },
    {
        key: "status",
        label: "Status",
        render: (row) => <StatusChip status={row.status} />,
    },
    {
        key: "usage",
        label: "Usage",
        render: (row) => <UsageChip usage={row.usage} />,
    },
    {
        key: "messages_count",
        label: "Messages",
        render: (row) => row.messages_count ?? 0,
    },
    {
        key: "updated_at",
        label: "Updated",
        render: (row) => row.updated_at ?? "-",
    },
];

export default function Index({
    conversations,
    features,
    systems,
    bots,
    filters,
}: IndexProps) {
    const { dialogProps, confirm } = useConfirmDialog();

    const updateFilters = (next: Filters) => {
        const merged: { [key: string]: string } = { ...filters, ...next };
        const params = Object.fromEntries(
            Object.entries(merged).filter(([, v]) => Boolean(v)),
        );
        router.get("/admin/ai/conversations", params, { preserveState: true });
    };

    const handleDelete = (conversation: Conversation) => {
        confirm("Delete this AI conversation?", () => {
            router.delete(`/admin/ai/conversations/${conversation.id}`);
        });
    };

    const handleBackfillUsage = () => {
        confirm(
            "Queue usage backfill for conversations missing usage?",
            () => {
                router.post("/admin/ai/conversations/backfill-usage");
            },
            {
                confirmLabel: "Queue Backfill",
            },
        );
    };

    const handleRecomputeAllUsage = () => {
        confirm(
            "Recompute usage for all conversations? This may take a while.",
            () => {
                router.post("/admin/ai/conversations/backfill-usage", {
                    all: true,
                });
            },
            {
                confirmLabel: "Queue Recompute",
                confirmColor: "warning",
            },
        );
    };

    return (
        <AdminLayout>
            <Head title="Conversations | AI Tools" />
            <PageHeader
                title="AI Conversations"
                backHref="/admin/ai"
                backLabel="Back to AI Tools"
            />

            <Box
                sx={{
                    display: "flex",
                    justifyContent: "flex-end",
                    gap: 1,
                    mb: 2,
                }}
            >
                <Button
                    variant="outlined"
                    size="small"
                    onClick={handleBackfillUsage}
                >
                    Backfill Usage
                </Button>
                <Button
                    variant="outlined"
                    color="warning"
                    size="small"
                    onClick={handleRecomputeAllUsage}
                >
                    Recompute All Usage
                </Button>
            </Box>

            <Box
                sx={{
                    display: "grid",
                    gap: 2,
                    gridTemplateColumns: {
                        xs: "1fr",
                        md: "repeat(5, minmax(0, 1fr))",
                    },
                    mb: 2,
                }}
            >
                <TextField
                    label="Search"
                    size="small"
                    value={filters.search ?? ""}
                    onChange={(event) => {
                        updateFilters({ search: event.target.value });
                    }}
                />
                <TextField
                    label="Feature"
                    select
                    size="small"
                    value={filters.feature ?? ""}
                    onChange={(event) => {
                        updateFilters({ feature: event.target.value });
                    }}
                >
                    <MenuItem value="">All Features</MenuItem>
                    {features.map((feature) => (
                        <MenuItem key={feature} value={feature}>
                            {feature}
                        </MenuItem>
                    ))}
                </TextField>
                <TextField
                    label="Status"
                    select
                    size="small"
                    value={filters.status ?? ""}
                    onChange={(event) => {
                        updateFilters({ status: event.target.value });
                    }}
                >
                    <MenuItem value="">All Statuses</MenuItem>
                    <MenuItem value="active">active</MenuItem>
                    <MenuItem value="completed">completed</MenuItem>
                    <MenuItem value="pass">pass</MenuItem>
                </TextField>
                <TextField
                    label="AI System"
                    select
                    size="small"
                    value={filters.ai_system_id ?? ""}
                    onChange={(event) => {
                        updateFilters({ ai_system_id: event.target.value });
                    }}
                >
                    <MenuItem value="">All Systems</MenuItem>
                    {systems.map((system) => (
                        <MenuItem key={system.id} value={String(system.id)}>
                            {system.name}
                        </MenuItem>
                    ))}
                </TextField>
                <TextField
                    label="Bot"
                    select
                    size="small"
                    value={filters.ai_chat_bot_id ?? ""}
                    onChange={(event) => {
                        updateFilters({ ai_chat_bot_id: event.target.value });
                    }}
                >
                    <MenuItem value="">All Bots</MenuItem>
                    {bots.map((bot) => (
                        <MenuItem key={bot.id} value={String(bot.id)}>
                            {bot.name}
                        </MenuItem>
                    ))}
                </TextField>
            </Box>

            <DataTable
                columns={columns}
                data={conversations.data}
                emptyMessage="No AI conversations found."
                pagination={conversations}
                rowActions={(conversation) => (
                    <Box
                        sx={{
                            display: "flex",
                            justifyContent: "flex-end",
                            gap: 1,
                        }}
                    >
                        {conversation.chat_hash && (
                            <IconButton
                                component={InertiaLink}
                                href={`/chat/${conversation.ai_chat_bot_slug}/${conversation.chat_hash}`}
                                size="small"
                                color="success"
                                title="Continue Chat"
                                aria-label="Continue Chat"
                                target="_blank"
                            >
                                <ChatIcon fontSize="small" />
                            </IconButton>
                        )}
                        <IconButton
                            component={InertiaLink}
                            href={`/admin/ai/conversations/${conversation.id}`}
                            size="small"
                            color="primary"
                            title="View Details"
                            aria-label="View Details"
                        >
                            <OpenInNewIcon fontSize="small" />
                        </IconButton>
                        <IconButton
                            size="small"
                            color="error"
                            onClick={() => {
                                handleDelete(conversation);
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
