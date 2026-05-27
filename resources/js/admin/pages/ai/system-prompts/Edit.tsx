import { Head, Link as InertiaLink, useForm } from "@inertiajs/react";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import Typography from "@mui/material/Typography";

import Form from "./Form";

import type { FormData } from "./Form";
import type { AiSystemPrompt } from "@/types";
import type { SyntheticEvent } from "react";

import PageHeader from "@/admin/components/PageHeader";
import AdminLayout from "@/admin/layouts/AdminLayout";

interface EditPrompt extends AiSystemPrompt {
    ai_systems_count: number;
}

interface EditProps {
    prompt: EditPrompt;
}

export default function Edit({ prompt }: EditProps) {
    const form = useForm<FormData>({
        title: prompt.title,
        description: prompt.description,
        content: prompt.content,
    });

    const handleSubmit = (e: SyntheticEvent<HTMLFormElement>) => {
        e.preventDefault();
        form.put(`/admin/ai/system-prompts/${prompt.id}`);
    };

    return (
        <AdminLayout>
            <Head title={`${prompt.title} | System Prompts`} />
            <PageHeader
                title={`Edit: ${prompt.title}`}
                backHref="/admin/ai/system-prompts"
                backLabel="Back to System Prompts"
            />

            {prompt.ai_systems_count > 0 && (
                <Card
                    sx={{
                        mb: 2,
                        bgcolor: "warning.50",
                        border: "1px solid",
                        borderColor: "warning.200",
                    }}
                >
                    <CardContent sx={{ py: 1.5, "&:last-child": { pb: 1.5 } }}>
                        <Typography variant="body2" color="warning.dark">
                            This prompt is referenced by{" "}
                            <strong>{prompt.ai_systems_count}</strong> AI system
                            {prompt.ai_systems_count !== 1 ? "s" : ""}. Editing
                            it will affect all systems that use it.
                        </Typography>
                    </CardContent>
                </Card>
            )}

            <Card>
                <CardContent>
                    <Box component="form" onSubmit={handleSubmit}>
                        <Form
                            data={form.data}
                            setData={form.setData}
                            errors={form.errors}
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
                                href="/admin/ai/system-prompts"
                                color="inherit"
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                variant="contained"
                                disabled={form.processing}
                            >
                                Save Changes
                            </Button>
                        </Box>
                    </Box>
                </CardContent>
            </Card>
        </AdminLayout>
    );
}
