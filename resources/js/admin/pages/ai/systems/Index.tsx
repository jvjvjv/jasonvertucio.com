import { Head, Link as InertiaLink, router } from "@inertiajs/react";
import DeleteOutlineIcon from "@mui/icons-material/DeleteOutline";
import EditIcon from "@mui/icons-material/Edit";
import FileCopyIcon from "@mui/icons-material/FileCopy";
import HistoryIcon from "@mui/icons-material/History";
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
import Typography from "@mui/material/Typography";

import type { AiSystem } from "@/types";

import ConfirmDialog from "@/admin/components/ConfirmDialog";
import EmptyTableRow from "@/admin/components/EmptyTableRow";
import PageHeader from "@/admin/components/PageHeader";
import AdminLayout from "@/admin/layouts/AdminLayout";
import useConfirmDialog from "@/hooks/useConfirmDialog";

interface IndexProps {
    systems: AiSystem[];
}

export default function Index({ systems }: IndexProps) {
    const { dialogProps, confirm } = useConfirmDialog();

    const handleDelete = (system: AiSystem) => {
        const botCount = system.chat_bots_count;
        const message =
            botCount > 0
                ? `"${system.name}" is used by ${botCount} chat bot(s). Deleting it will deactivate those bots. The system data will be preserved. Continue?`
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
                        AI Chat Bots
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
                </Box>
                <Button
                    component={InertiaLink}
                    href="/admin/ai/systems/new"
                    variant="contained"
                >
                    Add System
                </Button>
            </Box>

            <Card>
                <TableContainer>
                    <Table size="small">
                        <TableHead>
                            <TableRow>
                                <TableCell>Name</TableCell>
                                <TableCell>Provider</TableCell>
                                <TableCell>Model</TableCell>
                                <TableCell>Default For</TableCell>
                                <TableCell>API Calls</TableCell>
                                <TableCell align="right">Actions</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {systems.length === 0 ? (
                                <EmptyTableRow
                                    colSpan={6}
                                    message="No AI systems configured yet."
                                    actionLabel="Add your first one"
                                    actionHref="/admin/ai/systems/new"
                                />
                            ) : (
                                systems.map((system) => (
                                    <TableRow
                                        key={system.id}
                                        hover
                                        sx={{
                                            opacity: system.is_active ? 1 : 0.5,
                                        }}
                                    >
                                        <TableCell>
                                            <Link
                                                component={InertiaLink}
                                                href={`/admin/ai/systems/${system.id}`}
                                                underline="hover"
                                                color="inherit"
                                                sx={{ fontWeight: 500 }}
                                            >
                                                {system.name}
                                            </Link>
                                        </TableCell>
                                        <TableCell>{system.provider}</TableCell>
                                        <TableCell>
                                            <Typography
                                                variant="body2"
                                                fontFamily="monospace"
                                            >
                                                {system.model}
                                            </Typography>
                                        </TableCell>
                                        <TableCell>
                                            {system.feature_defaults_list
                                                .length > 0
                                                ? system.feature_defaults_list.map(
                                                      (f) => (
                                                          <Chip
                                                              key={f}
                                                              label={f}
                                                              size="small"
                                                              sx={{ mr: 0.5 }}
                                                          />
                                                      ),
                                                  )
                                                : "-"}
                                        </TableCell>
                                        <TableCell>
                                            {system.interaction_logs_count >
                                            0 ? (
                                                <Link
                                                    component={InertiaLink}
                                                    href={`/admin/ai/systems/${system.id}/logs`}
                                                    underline="hover"
                                                >
                                                    {
                                                        system.interaction_logs_count
                                                    }
                                                </Link>
                                            ) : (
                                                "-"
                                            )}
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
                                                        handleDuplicate(
                                                            system.id,
                                                        );
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
