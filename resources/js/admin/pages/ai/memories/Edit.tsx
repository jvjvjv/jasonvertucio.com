import { Head, Link as InertiaLink, useForm } from "@inertiajs/react";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import Typography from "@mui/material/Typography";
import AdminLayout from "../../../layouts/AdminLayout";
import PageHeader from "../../../components/PageHeader";
import MemoryForm from "./Form";
import type { FormData } from "./Form";
import type { Memory } from "../../../types";

interface EditMemory extends Memory, FormData {
    times_reinforced: number;
    last_reinforced_at: string | null;
    source_conversation_id: number | null;
}

interface EditProps {
    memory: EditMemory;
}

export default function Edit({ memory }: EditProps) {
    const form = useForm<FormData>({
        feature: memory.feature,
        category: memory.category,
        key: memory.key,
        content: memory.content,
        confidence: memory.confidence,
        is_active: memory.is_active,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(`/admin/ai/memories/${memory.id}`);
    };

    return (
        <AdminLayout>
            <Head title={`${memory.key} | Memories`} />
            <PageHeader
                title={`Edit: ${memory.key}`}
                backHref="/admin/ai/memories"
                backLabel="Back to AI Memories"
            />

            {/* Info box */}
            <Card sx={{ mb: 2, bgcolor: "grey.50" }}>
                <CardContent sx={{ py: 1.5, "&:last-child": { pb: 1.5 } }}>
                    <Box sx={{ display: "flex", gap: 3, flexWrap: "wrap" }}>
                        <Typography variant="body2" color="text.secondary">
                            Feature: <strong>{memory.feature}</strong>
                        </Typography>
                        <Typography variant="body2" color="text.secondary">
                            Reinforced:{" "}
                            <strong>{memory.times_reinforced}x</strong>
                        </Typography>
                        {memory.last_reinforced_at && (
                            <Typography variant="body2" color="text.secondary">
                                Last reinforced:{" "}
                                <strong>{memory.last_reinforced_at}</strong>
                            </Typography>
                        )}
                        {memory.source_conversation_id && (
                            <Typography variant="body2" color="text.secondary">
                                Source conversation:{" "}
                                <strong>
                                    #{memory.source_conversation_id}
                                </strong>
                            </Typography>
                        )}
                    </Box>
                </CardContent>
            </Card>

            <Card>
                <CardContent>
                    <Box component="form" onSubmit={handleSubmit}>
                        <MemoryForm
                            data={form.data}
                            setData={form.setData}
                            errors={form.errors}
                            isEdit
                        />

                        <Box
                            sx={{
                                display: "flex",
                                justifyContent: "flex-end",
                                gap: 2,
                                mt: 3,
                            }}
                        >
                            <Button
                                component={InertiaLink}
                                href="/admin/ai/memories"
                                color="inherit"
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                variant="contained"
                                disabled={form.processing}
                            >
                                Update Memory
                            </Button>
                        </Box>
                    </Box>
                </CardContent>
            </Card>
        </AdminLayout>
    );
}
