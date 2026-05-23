import { Head, Link as InertiaLink, router } from "@inertiajs/react";
import AddCommentIcon from "@mui/icons-material/AddComment";
import ChairIcon from "@mui/icons-material/Chair";
import DataObjectIcon from "@mui/icons-material/DataObject";
import DeleteOutlineIcon from "@mui/icons-material/DeleteOutline";
import EditIcon from "@mui/icons-material/Edit";
import HandymanIcon from "@mui/icons-material/Handyman";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import Chip from "@mui/material/Chip";
import IconButton from "@mui/material/IconButton";
import Link from "@mui/material/Link";
import Table from "@mui/material/Table";
import TableBody from "@mui/material/TableBody";
import TableCell from "@mui/material/TableCell";
import TableContainer from "@mui/material/TableContainer";
import TableHead from "@mui/material/TableHead";
import TableRow from "@mui/material/TableRow";
import Tooltip from "@mui/material/Tooltip";
import Typography from "@mui/material/Typography";

import type { AiChatBot } from "@/types";

import ConfirmDialog from "@/admin/components/ConfirmDialog";
import EmptyTableRow from "@/admin/components/EmptyTableRow";
import PageHeader from "@/admin/components/PageHeader";
import UsageChip from "@/admin/components/UsageChip";
import AdminLayout from "@/admin/layouts/AdminLayout";
import useConfirmDialog from "@/hooks/useConfirmDialog";

interface IndexProps {
    bots: AiChatBot[];
    filters?: { ai_system_id?: string | null };
}

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

            <Card>
                <TableContainer>
                    <Table size="small">
                        <TableHead>
                            <TableRow>
                                <TableCell>Name</TableCell>
                                <TableCell>Slug</TableCell>
                                <TableCell>AI System</TableCell>
                                <TableCell>Status</TableCell>
                                <TableCell>Features</TableCell>
                                <TableCell>Access</TableCell>
                                <TableCell>Sessions</TableCell>
                                <TableCell>Usage</TableCell>
                                <TableCell align="right">Actions</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {bots.length === 0 ? (
                                <EmptyTableRow
                                    colSpan={8}
                                    message="No AI chat bots configured yet."
                                    actionLabel="Add your first one"
                                    actionHref="/admin/ai/chat-bots/new"
                                />
                            ) : (
                                bots.map((bot) => {
                                    const botStatusOpacity =
                                        bot.ai_system.is_active && bot.is_active
                                            ? 1
                                            : bot.ai_system.is_active
                                              ? 0.75
                                              : 0.4;
                                    const toolsEnabled =
                                        bot.tools_enabled ||
                                        bot.ai_system.supports_tools;
                                    return (
                                        <TableRow
                                            key={bot.id}
                                            hover
                                            sx={{
                                                opacity: botStatusOpacity,
                                            }}
                                        >
                                            <TableCell>
                                                <Link
                                                    component={InertiaLink}
                                                    href={`/admin/ai/chat-bots/${bot.slug}`}
                                                    underline="hover"
                                                    color="primary"
                                                    sx={{ fontWeight: 600 }}
                                                >
                                                    {bot.name}
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                {bot.public_url ??
                                                    (bot.access_path === "root"
                                                        ? `/${bot.slug}`
                                                        : `/chat/${bot.slug}`)}
                                            </TableCell>
                                            <TableCell>
                                                <Link
                                                    component={InertiaLink}
                                                    href={`/admin/ai/systems/${bot.ai_system.id}`}
                                                    underline="hover"
                                                    color="primary"
                                                >
                                                    {bot.ai_system.name}
                                                </Link>
                                            </TableCell>
                                            <TableCell>Status</TableCell>
                                            <TableCell>
                                                <Box
                                                    sx={{
                                                        display: "flex",
                                                        gap: 1,
                                                        flexWrap: "wrap",
                                                    }}
                                                >
                                                    {toolsEnabled && (
                                                        <HandymanIcon
                                                            fontSize="small"
                                                            sx={
                                                                bot.ai_system
                                                                    .supports_tools
                                                                    ? {
                                                                          opacity: 0.75,
                                                                      }
                                                                    : {
                                                                          color: "primary",
                                                                      }
                                                            }
                                                        />
                                                    )}

                                                    {bot.ai_system
                                                        .supports_json_mode && (
                                                        <DataObjectIcon
                                                            fontSize="small"
                                                            sx={{
                                                                opacity: 0.75,
                                                            }}
                                                        />
                                                    )}

                                                    {bot.ai_system
                                                        .is_local_endpoint && (
                                                        <ChairIcon
                                                            fontSize="small"
                                                            color="primary"
                                                        />
                                                    )}
                                                </Box>
                                            </TableCell>
                                            <TableCell>
                                                <Box
                                                    sx={{
                                                        display: "flex",
                                                        gap: 0.5,
                                                        flexWrap: "wrap",
                                                    }}
                                                >
                                                    {bot.is_public && (
                                                        <Chip
                                                            label="Public"
                                                            size="small"
                                                            color="success"
                                                            variant="outlined"
                                                        />
                                                    )}
                                                    {bot.allowed_roles
                                                        .length && (
                                                        <Tooltip
                                                            title={
                                                                bot
                                                                    .allowed_roles
                                                                    .length > 0
                                                                    ? "Roles: " +
                                                                      bot.allowed_roles.join(
                                                                          ", ",
                                                                      )
                                                                    : "Any role"
                                                            }
                                                        >
                                                            <Chip
                                                                label="Role-based"
                                                                size="small"
                                                                color="info"
                                                                variant="outlined"
                                                                sx={{
                                                                    cursor: "pointer",
                                                                }}
                                                            />
                                                        </Tooltip>
                                                    )}
                                                </Box>
                                            </TableCell>
                                            <TableCell>
                                                {(bot.conversations_count ??
                                                    0) > 0 ? (
                                                    <Link
                                                        component={InertiaLink}
                                                        href={`/admin/ai/conversations?ai_chat_bot_id=${bot.id}`}
                                                        underline="hover"
                                                    >
                                                        {
                                                            bot.conversations_count
                                                        }
                                                    </Link>
                                                ) : (
                                                    "0"
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <UsageChip usage={bot.usage} />
                                            </TableCell>
                                            <TableCell align="right">
                                                <Box
                                                    sx={{
                                                        display: "flex",
                                                        justifyContent:
                                                            "flex-end",
                                                        gap: 1,
                                                    }}
                                                >
                                                    <IconButton
                                                        component={InertiaLink}
                                                        href={
                                                            bot.access_path ===
                                                            "root"
                                                                ? `/${bot.slug}/new`
                                                                : `/chat/${bot.slug}/new`
                                                        }
                                                        size="small"
                                                        color="primary"
                                                        title="Start New Chat"
                                                        aria-label="Start New Chat"
                                                        disabled={
                                                            !bot.is_active ||
                                                            !bot.ai_system
                                                                .is_active
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
                                            </TableCell>
                                        </TableRow>
                                    );
                                })
                            )}
                        </TableBody>
                    </Table>
                </TableContainer>
            </Card>
            <ConfirmDialog {...dialogProps} />
        </AdminLayout>
    );
}
