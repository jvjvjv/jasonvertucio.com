import { Head, Link as InertiaLink, useForm } from "@inertiajs/react";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";

import Form from "./Form";

import type { FormData } from "./Form";
import type { SyntheticEvent } from "react";

import AvailableMcpTools from "@/admin/components/AvailableMcpTools";
import PageHeader from "@/admin/components/PageHeader";
import AdminLayout from "@/admin/layouts/AdminLayout";

interface CreateProps {
    systems: {
        id: number;
        name: string;
        model: string;
        context_length: number | null;
        temperature: number | null;
        supports_tools: boolean;
    }[];
    roles: string[];
}

export default function Create({ systems, roles }: CreateProps) {
    const form = useForm<FormData>({
        name: "",
        slug: "",
        access_path: "chat",
        description: "",
        ai_system_id: "",
        context_length: null,
        temperature: "",
        prompt_template: "You are {{bot_name}}. {{bot_description}}",
        allowed_roles: [],
        is_active: true,
        require_visitor_identity: false,
        tools_enabled: false,
    });

    const selectedSystem = systems.find(
        (system) => system.id === form.data.ai_system_id,
    );
    const shouldShowMcpTools =
        selectedSystem?.supports_tools === true || form.data.tools_enabled;
    const handleSubmit = (event: SyntheticEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post("/admin/ai/chat-bots");
    };

    return (
        <AdminLayout>
            <Head title="New | Agents" />
            <PageHeader
                title="Add Agent"
                backHref="/admin/ai/chat-bots"
                backLabel="Back to Agents"
            />

            <Card>
                <CardContent>
                    <Box component="form" onSubmit={handleSubmit}>
                        <Form
                            data={form.data}
                            setData={form.setData}
                            errors={form.errors}
                            systems={systems}
                            roles={roles}
                            originalName=""
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
                                href="/admin/ai/chat-bots"
                                color="inherit"
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                variant="contained"
                                disabled={form.processing}
                            >
                                Save Agent
                            </Button>
                        </Box>
                    </Box>
                </CardContent>
            </Card>

            <Box sx={{ mt: 2 }}>
                <AvailableMcpTools
                    enabled={shouldShowMcpTools}
                    aiSystemId={form.data.ai_system_id}
                    description="These are the MCP tools allowed by the selected system and available to this bot when tool use is enabled."
                />
            </Box>
        </AdminLayout>
    );
}
