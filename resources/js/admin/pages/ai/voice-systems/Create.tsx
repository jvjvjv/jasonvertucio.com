import { Head, Link as InertiaLink, useForm } from "@inertiajs/react";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";

import VoiceSystemForm from "./Form";

import type { VoiceFormData } from "./Form";
import type { SyntheticEvent } from "react";

import PageHeader from "@/admin/components/PageHeader";
import AdminLayout from "@/admin/layouts/AdminLayout";

interface CreateProps {
    existingDefaults: string[];
}

export default function Create({ existingDefaults }: CreateProps) {
    const form = useForm<VoiceFormData>({
        name: "",
        role: "transcription",
        provider: "openai",
        api_key: "",
        clear_api_key: false,
        model: "",
        base_url: "",
        default_voice: "",
        is_active: true,
        feature_defaults: [],
    });

    const handleSubmit = (e: SyntheticEvent<HTMLFormElement>) => {
        e.preventDefault();
        form.post("/admin/ai/systems");
    };

    return (
        <AdminLayout>
            <Head title="New | Voice Systems" />
            <PageHeader
                title="Add Voice System"
                backHref="/admin/ai/voice-systems"
                backLabel="Back to Voice Systems"
            />

            <Card>
                <CardContent>
                    <Box component="form" onSubmit={handleSubmit}>
                        <VoiceSystemForm
                            data={form.data}
                            setData={form.setData}
                            errors={form.errors}
                            existingDefaults={existingDefaults}
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
                                href="/admin/ai/voice-systems"
                                color="inherit"
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                variant="contained"
                                disabled={form.processing}
                            >
                                Save System
                            </Button>
                        </Box>
                    </Box>
                </CardContent>
            </Card>
        </AdminLayout>
    );
}
