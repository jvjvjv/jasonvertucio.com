import type { SyntheticEvent } from "react";
import { Head, Link as InertiaLink, router, useForm } from "@inertiajs/react";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import AdminLayout from "@/admin/layouts/AdminLayout";
import PageHeader from "@/admin/components/PageHeader";
import AiSystemForm from "./Form";
import type { FormData } from "./Form";
import ConfirmDialog from "@/admin/components/ConfirmDialog";
import useConfirmDialog from "@/hooks/useConfirmDialog";
import type { AiSystem } from "@/types";

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
