import { Head, Link as InertiaLink, router, useForm } from "@inertiajs/react";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";

import AiSystemForm from "./Form";

import type { FormData } from "./Form";
import type { AiSystem } from "@/types";
import type { SyntheticEvent } from "react";

import ConfirmDialog from "@/admin/components/ConfirmDialog";
import PageHeader from "@/admin/components/PageHeader";
import AdminLayout from "@/admin/layouts/AdminLayout";
import useConfirmDialog from "@/hooks/useConfirmDialog";

interface EditProps {
    aiSystem: AiSystem;
    existingDefaults: string[];
}

export default function Edit({ aiSystem, existingDefaults }: EditProps) {
    const form = useForm<FormData>({
        name: aiSystem.name,
        provider: aiSystem.provider,
        api_key: aiSystem.api_key,
        model: aiSystem.model,
        base_url: aiSystem.base_url ?? "",
        api_version: aiSystem.api_version ?? "",
        max_tokens: aiSystem.max_tokens,
        temperature: aiSystem.temperature?.toString() ?? "",
        system_prompt: aiSystem.system_prompt ?? "",
        config: aiSystem.config ? JSON.stringify(aiSystem.config, null, 2) : "",
        credentials: aiSystem.credentials
            ? JSON.stringify(aiSystem.credentials, null, 2)
            : "",
        auth_type: aiSystem.auth_type ?? "",
        endpoint_type: aiSystem.endpoint_type ?? "",
        stream_protocol: aiSystem.stream_protocol ?? "",
        system_prompt_mode: aiSystem.system_prompt_mode ?? "",
        supports_tools: aiSystem.supports_tools ?? false,
        supports_json_mode: aiSystem.supports_json_mode ?? false,
        is_local_endpoint: aiSystem.is_local_endpoint ?? false,
        pricing_profile: aiSystem.pricing_profile
            ? JSON.stringify(aiSystem.pricing_profile, null, 2)
            : "",
        is_active: aiSystem.is_active,
        feature_defaults: aiSystem.feature_defaults_list,
    });

    const { dialogProps, confirm } = useConfirmDialog();

    const handleSubmit = (e: SyntheticEvent<HTMLFormElement>) => {
        e.preventDefault();
        form.put(`/admin/ai/systems/${aiSystem.id}`);
    };

    const handleDuplicate = () => {
        confirm(
            "Duplicate this AI system?",
            () => {
                router.post(`/admin/ai/systems/${aiSystem.id}/duplicate`);
            },
            { confirmLabel: "Duplicate", confirmColor: "primary" },
        );
    };

    return (
        <AdminLayout>
            <Head title={`${aiSystem.name} | AI Systems`} />
            <PageHeader
                title={`Edit: ${aiSystem.name}`}
                backHref="/admin/ai/systems"
                backLabel="Back to AI Systems"
            />

            <Box sx={{ display: "flex", gap: 1, mb: 2 }}>
                <Button
                    component={InertiaLink}
                    href={`/admin/ai/chat-bots?ai_system_id=${aiSystem.id}`}
                    variant="outlined"
                    size="small"
                >
                    Chat Bots ({aiSystem.chat_bots_count || 0})
                </Button>
                <Button
                    component={InertiaLink}
                    href={`/admin/ai/conversations?ai_system_id=${aiSystem.id}`}
                    variant="outlined"
                    size="small"
                >
                    Conversations
                </Button>
                <Button
                    component={InertiaLink}
                    href={`/admin/ai/systems/${aiSystem.id}/logs`}
                    variant="outlined"
                    size="small"
                >
                    Interaction Logs ({aiSystem.interaction_logs_count || 0})
                </Button>
            </Box>

            <Card>
                <CardContent>
                    <Box component="form" onSubmit={handleSubmit}>
                        <AiSystemForm
                            data={form.data}
                            setData={form.setData}
                            errors={form.errors}
                            existingDefaults={existingDefaults}
                            isEdit
                        />

                        <Box
                            sx={{
                                display: "flex",
                                justifyContent: "space-between",
                                alignItems: "center",
                                mt: 3,
                            }}
                        >
                            <Button onClick={handleDuplicate}>Duplicate</Button>

                            <Box sx={{ display: "flex", gap: 2 }}>
                                <Button
                                    component={InertiaLink}
                                    href="/admin/ai/systems"
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
                    </Box>
                </CardContent>
            </Card>
            <ConfirmDialog {...dialogProps} />
        </AdminLayout>
    );
}
