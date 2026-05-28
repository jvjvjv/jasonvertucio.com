import { Head, Link as InertiaLink, router } from "@inertiajs/react";
import AddCommentIcon from "@mui/icons-material/AddComment";
import ChairIcon from "@mui/icons-material/Chair";
import DataObjectIcon from "@mui/icons-material/DataObject";
import DeleteOutlineIcon from "@mui/icons-material/DeleteOutline";
import EditIcon from "@mui/icons-material/Edit";
import HandymanIcon from "@mui/icons-material/Handyman";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Chip from "@mui/material/Chip";
import IconButton from "@mui/material/IconButton";
import Link from "@mui/material/Link";
import Tooltip from "@mui/material/Tooltip";
import Typography from "@mui/material/Typography";

import type { ColumnDef } from "@/admin/components/DataTable";
import type { AiChatBot } from "@/types";

import ConfirmDialog from "@/admin/components/ConfirmDialog";
import DataTable from "@/admin/components/DataTable";
import PageHeader from "@/admin/components/PageHeader";
import UsageChip from "@/admin/components/UsageChip";
import AdminLayout from "@/admin/layouts/AdminLayout";
import useConfirmDialog from "@/hooks/useConfirmDialog";

interface IndexProps {
    bots: AiChatBot[];
    filters?: { ai_system_id?: string | null };
}

const columns: ColumnDef<AiChatBot>[] = [
    {
        key: "name",
        label: "Name",
        render: (row) => (
            <Link
                component={InertiaLink}
                href={`/admin/ai/chat-bots/${row.slug}`}
                underline="hover"
                color="primary"
                sx={{ fontWeight: 600 }}
            >
                {row.name}
            </Link>
        ),
    },
    {
        key: "slug",
        label: "Slug",
        render: (row) =>
            row.public_url ??
            (row.access_path === "root" ? `/${row.slug}` : `/chat/${row.slug}`),
    },
    {
        key: "ai_system",
        label: "AI System",
        render: (row) => (
            <Link
                component={InertiaLink}
                href={`/admin/ai/systems/${row.ai_system.id}`}
                underline="hover"
                color="primary"
            >
                {row.ai_system.name}
            </Link>
        ),
    },
    { key: "status", label: "Status", render: () => "Status" },
    {
        key: "features",
        label: "Features",
        render: (row) => {
            const toolsEnabled =
                row.tools_enabled || row.ai_system.supports_tools;
            return (
                <Box sx={{ display: "flex", gap: 1, flexWrap: "wrap" }}>
                    {toolsEnabled && (
                        <HandymanIcon
                            fontSize="small"
                            sx={
                                row.ai_system.supports_tools
                                    ? { opacity: 0.75 }
                                    : { color: "primary" }
                            }
                        />
                    )}
                    {row.ai_system.supports_json_mode && (
                        <DataObjectIcon
                            fontSize="small"
                            sx={{ opacity: 0.75 }}
                        />
                    )}
                    {row.ai_system.is_local_endpoint && (
                        <ChairIcon fontSize="small" color="primary" />
                    )}
                </Box>
            );
        },
    },
    {
        key: "access",
        label: "Access",
        render: (row) => (
            <Box sx={{ display: "flex", gap: 0.5, flexWrap: "wrap" }}>
                {row.is_public && (
                    <Chip
                        label="Public"
                        size="small"
                        color="success"
                        variant="outlined"
                    />
                )}
                {row.allowed_roles.length > 0 && (
                    <Tooltip
                        title={
                            row.allowed_roles.length > 0
                                ? "Roles: " + row.allowed_roles.join(", ")
                                : "Any role"
                        }
                    >
                        <Chip
                            label="Role-based"
                            size="small"
                            color="info"
                            variant="outlined"
                            sx={{ cursor: "pointer" }}
                        />
                    </Tooltip>
                )}
            </Box>
        ),
    },
    {
        key: "conversations_count",
        label: "Sessions",
        render: (row) =>
            (row.conversations_count ?? 0) > 0 ? (
                <Link
                    component={InertiaLink}
                    href={`/admin/ai/conversations?ai_chat_bot_id=${row.id}`}
                    underline="hover"
                >
                    {row.conversations_count}
                </Link>
            ) : (
                "0"
            ),
    },
    {
        key: "usage",
        label: "Usage",
        render: (row) => <UsageChip usage={row.usage} />,
    },
];

export default function Index({ bots, filters }: IndexProps) {
    const { dialogProps, confirm } = useConfirmDialog();
    const aiSystemId = filters?.ai_system_id;

    const handleDelete = (bot: AiChatBot) => {
        confirm(`Delete AI chat bot "${bot.name}"?`, () => {
            router.delete(`/admin/ai/chat-bots/${bot.slug}`);
        });
    };

    return (
        <AdminLayout>
            <Head title="Chat Bots | AI Tools" />
            <PageHeader
                title="AI Chat Bots"
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
                </Box>
                <Button
                    component={InertiaLink}
                    href="/admin/ai/chat-bots/new"
                    variant="contained"
                >
                    Add Bot
                </Button>
            </Box>

            {aiSystemId ? (
                <Box
                    sx={{
                        display: "flex",
                        alignItems: "center",
                        gap: 1,
                        mb: 2,
                    }}
                >
                    <Typography variant="body2" color="text.secondary">
                        Filtered by AI System
                    </Typography>
                    <Button
                        component={InertiaLink}
                        href="/admin/ai/chat-bots"
                        size="small"
                        variant="outlined"
                        color="inherit"
                    >
                        Clear filter
                    </Button>
                </Box>
            ) : null}

            <DataTable
                columns={columns}
                data={bots}
                rowSx={(bot) => ({
                    opacity:
                        bot.ai_system.is_active && bot.is_active
                            ? 1
                            : bot.ai_system.is_active
                              ? 0.75
                              : 0.4,
                })}
                emptyState={
                    <Box sx={{ textAlign: "center", py: 4 }}>
                        <Typography color="text.secondary">
                            No AI chat bots configured yet.
                        </Typography>
                        <Typography variant="body2" sx={{ mt: 0.5 }}>
                            <Link
                                component={InertiaLink}
                                href="/admin/ai/chat-bots/new"
                                underline="hover"
                            >
                                Add your first one
                            </Link>
                        </Typography>
                    </Box>
                }
                rowActions={(bot) => (
                    <Box
                        sx={{
                            display: "flex",
                            justifyContent: "flex-end",
                            gap: 1,
                        }}
                    >
                        <IconButton
                            component={InertiaLink}
                            href={
                                bot.access_path === "root"
                                    ? `/${bot.slug}/new`
                                    : `/chat/${bot.slug}/new`
                            }
                            size="small"
                            color="primary"
                            title="Start New Chat"
                            aria-label="Start New Chat"
                            disabled={
                                !bot.is_active || !bot.ai_system.is_active
                            }
                            target="_blank"
                        >
                            <AddCommentIcon />
                        </IconButton>
                        <IconButton
                            component={InertiaLink}
                            href={`/admin/ai/chat-bots/${bot.slug}`}
                            size="small"
                            color="primary"
                            title="Edit"
                            aria-label="Edit"
                        >
                            <EditIcon />
                        </IconButton>
                        <IconButton
                            size="small"
                            color="error"
                            onClick={() => {
                                handleDelete(bot);
                            }}
                            title="Delete"
                            aria-label="Delete"
                        >
                            <DeleteOutlineIcon />
                        </IconButton>
                    </Box>
                )}
            />

            <ConfirmDialog {...dialogProps} />
        </AdminLayout>
    );
}
