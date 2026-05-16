import { Head, Link as InertiaLink, router, useForm } from "@inertiajs/react";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";

import Form from "./Form";

import type { FormData } from "./Form";
import type { AiChatBot } from "@/types";
import type { SyntheticEvent } from "react";

import AvailableMcpTools from "@/admin/components/AvailableMcpTools";
import ConfirmDialog from "@/admin/components/ConfirmDialog";
import PageHeader from "@/admin/components/PageHeader";
import AdminLayout from "@/admin/layouts/AdminLayout";
import useConfirmDialog from "@/hooks/useConfirmDialog";

interface EditProps {
    bot: Omit<AiChatBot, "ai_system"> & { ai_system_id: number };
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

export default function Edit({ bot, systems, roles }: EditProps) {
    const form = useForm<FormData>({
        name: bot.name,
        slug: bot.slug,
        access_path: bot.access_path,
        description: bot.description ?? "",
        ai_system_id: bot.ai_system_id,
        context_length: bot.context_length ?? null,
        temperature: bot.temperature?.toString() ?? "",
        prompt_template: bot.prompt_template ?? "",
        allowed_roles: bot.allowed_roles,
        is_active: bot.is_active,
        is_public: bot.is_public,
        require_visitor_identity: bot.require_visitor_identity,
        tools_enabled: bot.tools_enabled,
    });

    const selectedSystem = systems.find(
        (system) => system.id === form.data.ai_system_id,
    );
    const shouldShowMcpTools =
        selectedSystem?.supports_tools === true || form.data.tools_enabled;
    const { dialogProps, confirm } = useConfirmDialog();

    const handleSubmit = (event: SyntheticEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(`/admin/ai/chat-bots/${bot.slug}`);
    };

    const handleDelete = () => {
        confirm(`Delete AI chat bot "${bot.name}"?`, () => {
            router.delete(`/admin/ai/chat-bots/${bot.slug}`);
        });
    };

    return (
        <AdminLayout>
            <Head title={`${bot.name} | Chat Bots`} />
            <PageHeader
                title={`Edit: ${bot.name}`}
                backHref="/admin/ai/chat-bots"
                backLabel="Back to AI Chat Bots"
            />

            <Box sx={{ display: "flex", gap: 1, mb: 2 }}>
                <Button
                    component={InertiaLink}
                    href={`/admin/ai/conversations?ai_chat_bot_id=${bot.id}`}
                    variant="outlined"
                    size="small"
                >
                    Sessions ({bot.conversations_count ?? 0})
                </Button>
            </Box>

            <Card>
                <CardContent>
                    <Box component="form" onSubmit={handleSubmit}>
                        <Form
                            data={form.data}
                            setData={form.setData}
                            errors={form.errors}
                            systems={systems}
                            roles={roles}
                            originalName={bot.name}
                        />

                        <Box
                            sx={{
                                display: "flex",
                                justifyContent: "space-between",
                                alignItems: "center",
                                mt: 3,
                            }}
                        >
                            <Button color="error" onClick={handleDelete}>
                                Delete
                            </Button>

                            <Box sx={{ display: "flex", gap: 2 }}>
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
                                    Update Bot
                                </Button>
                            </Box>
                        </Box>
                    </Box>
                </CardContent>
            </Card>

            <Box sx={{ mt: 2 }}>
                <AvailableMcpTools enabled={shouldShowMcpTools} />
            </Box>
            <ConfirmDialog {...dialogProps} />
        </AdminLayout>
    );
}
