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
        prompt_template: "You are {{bot_name}}. {{bot_description}}",
        allowed_roles: [],
        is_active: true,
        is_public: false,
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
            <Head title="New | Chat Bots" />
            <PageHeader
                title="Add AI Chat Bot"
                backHref="/admin/ai/chat-bots"
                backLabel="Back to AI Chat Bots"
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
                                Save Bot
                            </Button>
                        </Box>
                    </Box>
                </CardContent>
            </Card>

            <Box sx={{ mt: 2 }}>
                <AvailableMcpTools enabled={shouldShowMcpTools} />
            </Box>
        </AdminLayout>
    );
}
