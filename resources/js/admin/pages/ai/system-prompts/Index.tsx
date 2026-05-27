import { Head, Link as InertiaLink, router } from "@inertiajs/react";
import DeleteOutlineIcon from "@mui/icons-material/DeleteOutline";
import EditIcon from "@mui/icons-material/Edit";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import Chip from "@mui/material/Chip";
import IconButton from "@mui/material/IconButton";
import Table from "@mui/material/Table";
import TableBody from "@mui/material/TableBody";
import TableCell from "@mui/material/TableCell";
import TableContainer from "@mui/material/TableContainer";
import TableHead from "@mui/material/TableHead";
import TableRow from "@mui/material/TableRow";
import Tooltip from "@mui/material/Tooltip";
import Typography from "@mui/material/Typography";

import type { AiSystemPrompt } from "@/types";

import ConfirmDialog from "@/admin/components/ConfirmDialog";
import EmptyTableRow from "@/admin/components/EmptyTableRow";
import PageHeader from "@/admin/components/PageHeader";
import AdminLayout from "@/admin/layouts/AdminLayout";
import useConfirmDialog from "@/hooks/useConfirmDialog";

interface PromptWithCount extends AiSystemPrompt {
    ai_systems_count: number;
}

interface IndexProps {
    prompts: PromptWithCount[];
}

export default function Index({ prompts }: IndexProps) {
    const { dialogProps, confirm } = useConfirmDialog();

    const handleDelete = (prompt: PromptWithCount) => {
        const message =
            prompt.ai_systems_count > 0
                ? `Delete "${prompt.title}"? ${prompt.ai_systems_count} AI system(s) will lose their prompt assignment.`
                : `Delete "${prompt.title}"? This cannot be undone.`;

        confirm(message, () => {
            router.delete(`/admin/ai/system-prompts/${prompt.id}`);
        });
    };

    return (
        <AdminLayout>
            <Head title="System Prompts" />
            <PageHeader
                title="System Prompts"
                backHref="/admin/ai"
                backLabel="Back to AI Tools"
            >
                <Button
                    component={InertiaLink}
                    href="/admin/ai/system-prompts/new"
                    variant="contained"
                    size="small"
                >
                    Create New Prompt
                </Button>
            </PageHeader>

            <Card>
                <TableContainer>
                    <Table size="small">
                        <TableHead>
                            <TableRow>
                                <TableCell>Title</TableCell>
                                <TableCell>Description</TableCell>
                                <TableCell align="center">Used by</TableCell>
                                <TableCell align="right">Actions</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {prompts.length === 0 && (
                                <EmptyTableRow
                                    colSpan={4}
                                    message="No system prompts found."
                                />
                            )}
                            {prompts.map((prompt) => (
                                <TableRow key={prompt.id} hover>
                                    <TableCell>
                                        <Typography
                                            variant="body2"
                                            fontWeight={500}
                                        >
                                            {prompt.title}
                                        </Typography>
                                    </TableCell>
                                    <TableCell>
                                        <Typography
                                            variant="body2"
                                            color="text.secondary"
                                        >
                                            {prompt.description}
                                        </Typography>
                                    </TableCell>
                                    <TableCell align="center">
                                        {prompt.ai_systems_count > 0 ? (
                                            <Chip
                                                label={`${prompt.ai_systems_count} system${prompt.ai_systems_count !== 1 ? "s" : ""}`}
                                                size="small"
                                                color="primary"
                                                variant="outlined"
                                            />
                                        ) : (
                                            <Typography
                                                variant="body2"
                                                color="text.disabled"
                                            >
                                                —
                                            </Typography>
                                        )}
                                    </TableCell>
                                    <TableCell align="right">
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
                                                    href={`/admin/ai/system-prompts/${prompt.id}`}
                                                >
                                                    <EditIcon fontSize="small" />
                                                </IconButton>
                                            </Tooltip>
                                            <Tooltip title="Delete">
                                                <IconButton
                                                    size="small"
                                                    color="error"
                                                    onClick={() => {
                                                        handleDelete(prompt);
                                                    }}
                                                >
                                                    <DeleteOutlineIcon fontSize="small" />
                                                </IconButton>
                                            </Tooltip>
                                        </Box>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </TableContainer>
            </Card>

            <ConfirmDialog {...dialogProps} />
        </AdminLayout>
    );
}
