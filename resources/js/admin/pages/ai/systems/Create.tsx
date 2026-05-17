import { Head, Link as InertiaLink, useForm } from "@inertiajs/react";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";

import AiSystemForm from "./Form";

import type { FormData } from "./Form";
import type { SyntheticEvent } from "react";

import AvailableMcpTools from "@/admin/components/AvailableMcpTools";
import PageHeader from "@/admin/components/PageHeader";
import AdminLayout from "@/admin/layouts/AdminLayout";

interface CreateProps {
    existingDefaults: string[];
}

export default function Create({ existingDefaults }: CreateProps) {
    const form = useForm<FormData>({
        name: "",
        provider: "anthropic",
        api_key: "",
        model: "",
        base_url: "",
        api_version: "",
        max_tokens: 4096,
        context_length: null,
        temperature: "",
        system_prompt: "",
        config: "",
        credentials: "",
        auth_type: "",
        endpoint_type: "",
        stream_protocol: "",
        system_prompt_mode: "",
        supports_tools: false,
        allowed_tools: [],
        supports_json_mode: false,
        is_local_endpoint: false,
        pricing_profile: "",
        is_active: true,
        feature_defaults: [],
    });

    const handleSubmit = (e: SyntheticEvent<HTMLFormElement>) => {
        e.preventDefault();
        form.post("/admin/ai/systems");
    };

    return (
        <AdminLayout>
            <Head title="New | AI Systems" />
            <PageHeader
                title="Add AI System"
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
                                Save System
                            </Button>
                        </Box>
                    </Box>
                </CardContent>
            </Card>

            <Box sx={{ mt: 2 }}>
                <AvailableMcpTools
                    enabled={form.data.supports_tools}
                    selectable
                    selectedToolNames={form.data.allowed_tools}
                    onToggleTool={(toolName) => {
                        const nextTools = form.data.allowed_tools.includes(toolName)
                            ? form.data.allowed_tools.filter((name) => name !== toolName)
                            : [...form.data.allowed_tools, toolName];

                        form.setData("allowed_tools", nextTools);
                    }}
                    description="Select the MCP tools this system may expose. If none are selected, chat bots on this system cannot use MCP tools."
                />
            </Box>
        </AdminLayout>
    );
}
