import { Head, Link as InertiaLink, router } from "@inertiajs/react";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import Link from "@mui/material/Link";
import MenuItem from "@mui/material/MenuItem";
import Table from "@mui/material/Table";
import TableBody from "@mui/material/TableBody";
import TableCell from "@mui/material/TableCell";
import TableContainer from "@mui/material/TableContainer";
import TableHead from "@mui/material/TableHead";
import TableRow from "@mui/material/TableRow";
import TextField from "@mui/material/TextField";

import type { Conversation, PaginatedResponse } from "@/types";

import ConfirmDialog from "@/admin/components/ConfirmDialog";
import EmptyTableRow from "@/admin/components/EmptyTableRow";
import PageHeader from "@/admin/components/PageHeader";
import Pagination from "@/admin/components/Pagination";
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

            <Card>
                <TableContainer>
                    <Table size="small">
                        <TableHead>
                            <TableRow>
                                <TableCell>Conversation</TableCell>
                                <TableCell>Feature</TableCell>
                                <TableCell>Participant</TableCell>
                                <TableCell>System / Bot</TableCell>
                                <TableCell>Status</TableCell>
                                <TableCell>Usage</TableCell>
                                <TableCell>Messages</TableCell>
                                <TableCell>Updated</TableCell>
                                <TableCell align="right">Actions</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {conversations.data.length === 0 ? (
                                <EmptyTableRow
                                    colSpan={9}
                                    message="No AI conversations found."
                                />
                            ) : (
                                conversations.data.map((conversation) => (
                                    <TableRow key={conversation.id} hover>
                                        <TableCell>
                                            <Link
                                                component={InertiaLink}
                                                href={`/admin/ai/conversations/${conversation.id}`}
                                                style={{
                                                    fontWeight: 500,
                                                }}
                                                underline="hover"
                                            >
                                                {conversation.title ??
                                                    `Conversation #${conversation.id}`}
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            {conversation.feature}
                                        </TableCell>
                                        <TableCell>
                                            {conversation.user_name ??
                                                conversation.visitor_name ??
                                                "Unknown"}
                                        </TableCell>
                                        <TableCell>
                                            <Box>
                                                <Box>
                                                    {conversation.ai_system_name ??
                                                        "-"}
                                                </Box>
                                                <Box
                                                    sx={{
                                                        color: "text.secondary",
                                                        fontSize: 12,
                                                    }}
                                                >
                                                    {conversation.ai_chat_bot_name ??
                                                        "No bot"}
                                                </Box>
                                            </Box>
                                        </TableCell>
                                        <TableCell>
                                            <StatusChip
                                                status={conversation.status}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <UsageChip
                                                usage={conversation.usage}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            {conversation.messages_count ?? 0}
                                        </TableCell>
                                        <TableCell>
                                            {conversation.updated_at ?? "-"}
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
                                                    href={`/admin/ai/conversations/${conversation.id}`}
                                                    size="small"
                                                >
                                                    View
                                                </Button>
                                                <Button
                                                    size="small"
                                                    color="error"
                                                    onClick={() => {
                                                        handleDelete(
                                                            conversation,
                                                        );
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
                    links={conversations.links}
                    lastPage={conversations.last_page}
                />
            </Card>
            <ConfirmDialog {...dialogProps} />
        </AdminLayout>
    );
}
