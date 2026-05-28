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
import type { AiSystemPrompt } from "@/types";

import ConfirmDialog from "@/admin/components/ConfirmDialog";
import DataTable from "@/admin/components/DataTable";
import PageHeader from "@/admin/components/PageHeader";
import AdminLayout from "@/admin/layouts/AdminLayout";
import useConfirmDialog from "@/hooks/useConfirmDialog";

interface PromptWithCount extends AiSystemPrompt {
    ai_systems_count: number;
}

interface IndexProps {
    prompts: PromptWithCount[];
}

const columns: ColumnDef<PromptWithCount>[] = [
    {
        key: "title",
        label: "Title",
        render: (row) => (
            <Typography variant="body2" fontWeight={500}>
                {row.title}
            </Typography>
        ),
    },
    {
        key: "description",
        label: "Description",
        render: (row) => (
            <Typography variant="body2" color="text.secondary">
                {row.description}
            </Typography>
        ),
    },
    {
        key: "ai_systems_count",
        label: "Used by",
        align: "center",
        render: (row) =>
            row.ai_systems_count > 0 ? (
                <Chip
                    label={`${row.ai_systems_count} system${row.ai_systems_count !== 1 ? "s" : ""}`}
                    size="small"
                    color="primary"
                    variant="outlined"
                />
            ) : (
                <Typography variant="body2" color="text.disabled">
                    —
                </Typography>
            ),
    },
];

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

            <DataTable
                columns={columns}
                data={prompts}
                emptyMessage="No system prompts found."
                rowActions={(prompt) => (
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
                )}
            />

            <ConfirmDialog {...dialogProps} />
        </AdminLayout>
    );
}
