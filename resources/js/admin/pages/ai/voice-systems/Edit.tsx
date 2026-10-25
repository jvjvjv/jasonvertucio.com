import { Head, Link as InertiaLink, useForm } from "@inertiajs/react";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";

import VoiceSystemForm from "./Form";

import type { VoiceFormData, VoiceRole } from "./Form";
import type { AiSystem } from "@/types";
import type { SyntheticEvent } from "react";

import PageHeader from "@/admin/components/PageHeader";
import AdminLayout from "@/admin/layouts/AdminLayout";

interface EditProps {
    aiSystem: AiSystem;
    existingDefaults: string[];
    pendingFirstEdit: boolean;
}

export default function Edit({
    aiSystem,
    existingDefaults,
    pendingFirstEdit,
}: EditProps) {
    const storedVoice = aiSystem.config?.voice;

    // `config` is left out on purpose: the server keeps the stored config and
    // only sets or clears its `voice` from `default_voice`.
    const form = useForm<VoiceFormData>({
        name: aiSystem.name,
        role: aiSystem.role as VoiceRole,
        provider: aiSystem.provider,
        api_key: "",
        clear_api_key: false,
        model: aiSystem.model,
        base_url: aiSystem.base_url ?? "",
        default_voice: typeof storedVoice === "string" ? storedVoice : "",
        is_active: aiSystem.is_active,
        feature_defaults: aiSystem.feature_defaults_list,
    });

    const handleSubmit = (e: SyntheticEvent<HTMLFormElement>) => {
        e.preventDefault();
        form.put(`/admin/ai/systems/${aiSystem.id}`);
    };

    return (
        <AdminLayout>
            <Head title={`${aiSystem.name} | Voice Systems`} />
            <PageHeader
                title={`Edit: ${aiSystem.name}`}
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
                            isEdit
                            pendingFirstEdit={pendingFirstEdit}
                            aiSystemId={aiSystem.id}
                            hasApiKey={aiSystem.has_api_key}
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
                                Update System
                            </Button>
                        </Box>
                    </Box>
                </CardContent>
            </Card>
        </AdminLayout>
    );
}
