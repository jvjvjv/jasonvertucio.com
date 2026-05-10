import { Head, Link as InertiaLink, router } from "@inertiajs/react";
import AddCommentIcon from "@mui/icons-material/AddComment";
import DeleteOutlineIcon from "@mui/icons-material/DeleteOutline";
import EditIcon from "@mui/icons-material/Edit";
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

import type { AiChatBot } from "@/types";

import ConfirmDialog from "@/admin/components/ConfirmDialog";
import EmptyTableRow from "@/admin/components/EmptyTableRow";
import PageHeader from "@/admin/components/PageHeader";
import UsageChip from "@/admin/components/UsageChip";
import AdminLayout from "@/admin/layouts/AdminLayout";
import useConfirmDialog from "@/hooks/useConfirmDialog";

interface IndexProps {
    bots: AiChatBot[];
}

export default function Index({ bots }: IndexProps) {
    const { dialogProps, confirm } = useConfirmDialog();

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

            <Card>
                <TableContainer>
                    <Table size="small">
                        <TableHead>
                            <TableRow>
                                <TableCell>Name</TableCell>
                                <TableCell>Slug</TableCell>
                                <TableCell>AI System</TableCell>
                                <TableCell>Access</TableCell>
                                <TableCell>Sessions</TableCell>
                                <TableCell>Usage</TableCell>
                                <TableCell align="right">Actions</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {bots.length === 0 ? (
                                <EmptyTableRow
                                    colSpan={7}
                                    message="No AI chat bots configured yet."
                                    actionLabel="Add your first one"
                                    actionHref="/admin/ai/chat-bots/new"
                                />
                            ) : (
                                bots.map((bot) => (
                                    <TableRow
                                        key={bot.id}
                                        hover
                                        sx={{
                                            opacity: bot.is_active ? 1 : 0.5,
                                        }}
                                    >
                                        <TableCell>
                                            <Link
                                                component={InertiaLink}
                                                href={`/admin/ai/chat-bots/${bot.slug}`}
                                                underline="hover"
                                                color="inherit"
                                                sx={{ fontWeight: 500 }}
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
                                            {bot.ai_system_name ?? "-"}
                                        </TableCell>
                                        <TableCell>
                                            <Box
                                                sx={{
                                                    display: "flex",
                                                    gap: 0.5,
                                                    flexWrap: "wrap",
                                                }}
                                            >
                                                <Chip
                                                    label={
                                                        bot.access_path ===
                                                        "root"
                                                            ? "Root Path"
                                                            : "Chat Path"
                                                    }
                                                    size="small"
                                                    variant="outlined"
                                                />
                                                <Chip
                                                    label={
                                                        bot.is_public
                                                            ? "Public"
                                                            : "Role-based"
                                                    }
                                                    size="small"
                                                    color={
                                                        bot.is_public
                                                            ? "success"
                                                            : "default"
                                                    }
                                                    variant="outlined"
                                                />
                                                {bot.require_visitor_identity ? (
                                                    <Chip
                                                        label="Identity Required"
                                                        size="small"
                                                        color="warning"
                                                        variant="outlined"
                                                    />
                                                ) : null}
                                            </Box>
                                        </TableCell>
                                        <TableCell>
                                            {(bot.conversations_count ?? 0) >
                                            0 ? (
                                                <Link
                                                    component={InertiaLink}
                                                    href={`/admin/ai/conversations?ai_chat_bot_id=${bot.id}`}
                                                    underline="hover"
                                                >
                                                    {bot.conversations_count}
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
                                                    justifyContent: "flex-end",
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
                                                    target="_blank"
                                                >
                                                    <AddCommentIcon fontSize="small" />
                                                </IconButton>
                                                <IconButton
                                                    component={InertiaLink}
                                                    href={`/admin/ai/chat-bots/${bot.slug}`}
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
                                                        handleDelete(bot);
                                                    }}
                                                    title="Delete"
                                                    aria-label="Delete"
                                                >
                                                    <DeleteOutlineIcon fontSize="small" />
                                                </IconButton>
                                            </Box>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </TableContainer>
            </Card>
            <ConfirmDialog {...dialogProps} />
        </AdminLayout>
    );
}
